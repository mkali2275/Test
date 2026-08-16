/**
 * Check the browser sizing engine against the PHP one.
 *
 * Runs every scenario in scenarios.json through assets/js/solar-calculator.js
 * and compares the numbers with the output of tools/php-results.php. The two
 * implementations must agree, because the browser shows one set of numbers and
 * the emailed copy is produced by the other.
 *
 * Usage: php tools/php-results.php > /tmp/php.json && node tools/test-parity.js /tmp/php.json
 */

'use strict';

const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const root = path.join(__dirname, '..');
const pluginDir = path.join(root, 'solar-power-calculator');

/* Load the scenarios and the PHP results. */
const scenarios = JSON.parse(fs.readFileSync(path.join(__dirname, 'scenarios.json'), 'utf8'));

const phpJson = process.argv[2]
	? fs.readFileSync(process.argv[2], 'utf8')
	: execFileSync('php', [path.join(__dirname, 'php-results.php')], { encoding: 'utf8' });
const phpResults = JSON.parse(phpJson);

/* Build the window.SPC_DATA payload the script expects. */
const dataDump = execFileSync('php', ['-r', `
	define('ABSPATH', __DIR__);
	function apply_filters($h, $v) { return $v; }
	function get_option($n, $d = false) { return $d; }
	function wp_parse_args($a, $d = array()) { return array_merge($d, is_array($a) ? $a : array()); }
	require_once '${pluginDir}/includes/class-spc-data.php';
	echo json_encode(array(
		'constants' => SPC_Data::constants(),
		'batteries' => SPC_Data::battery_types(),
		'appliances' => SPC_Data::appliances(),
		'profiles' => SPC_Data::profiles(),
		'regions' => SPC_Data::regions(),
		'settings' => array(
			'currency' => '$', 'showCosts' => true, 'costPvWatt' => 0.9,
			'costInverterKw' => 300, 'costInstallPct' => 25
		)
	));
`], { encoding: 'utf8' });

/* Minimal browser globals so the IIFE can load. */
global.window = { SPC_DATA: JSON.parse(dataDump) };
global.document = {
	readyState: 'complete',
	addEventListener() {},
	querySelectorAll: () => [],
	createElement: () => ({ set textContent(v) { this._v = v; }, get innerHTML() { return this._v; } })
};

const engine = require(path.join(pluginDir, 'assets/js/solar-calculator.js'));

/* Map the snake_case PHP keys onto the camelCase JS ones. */
const FIELDS = {
	daily_wh: 'dailyWh',
	battery_draw_wh: 'batteryDrawWh',
	system_voltage: 'systemVoltage',
	array_watts: 'arrayWatts',
	array_installed: 'arrayInstalled',
	panel_qty: 'panelQty',
	daily_harvest_wh: 'dailyHarvestWh',
	bank_wh: 'bankWh',
	bank_ah: 'bankAh',
	usable_kwh: 'usableKwh',
	battery_qty: 'batteryQty',
	battery_series: 'batterySeries',
	battery_strings: 'batteryStrings',
	backup_hours: 'backupHours',
	running_w: 'runningW',
	surge_w: 'surgeW',
	inverter_rated: 'inverterRated',
	inverter_va: 'inverterVa',
	controller_a: 'controllerA',
	controller_rated: 'controllerRated',
	annual_kwh: 'annualKwh',
	co2_saved_kg: 'co2SavedKg',
	cost_total: 'costTotal'
};

const TOLERANCE = 0.01; // 1% - covers float rounding between the two runtimes.

let failures = 0;
let checks = 0;

scenarios.forEach((scenario, index) => {
	const php = phpResults[index].results;
	const js = engine.calculate(scenario.items, {
		psh: scenario.config.psh,
		autonomy: scenario.config.autonomy,
		batteryType: scenario.config.battery_type,
		voltage: scenario.config.voltage,
		inverterType: scenario.config.inverter_type,
		controller: scenario.config.controller,
		panelWatts: scenario.config.panel_watts,
		batteryUnit: scenario.config.battery_unit,
		simultaneity: scenario.config.simultaneity
	});

	const problems = [];

	Object.keys(FIELDS).forEach((phpKey) => {
		const jsKey = FIELDS[phpKey];
		const a = Number(php[phpKey]);
		const b = Number(js[jsKey]);
		checks++;

		const diff = Math.abs(a - b);
		const scale = Math.max(Math.abs(a), Math.abs(b), 1);

		if (diff / scale > TOLERANCE) {
			problems.push(`  ${phpKey}: php=${a} js=${b}`);
		}
	});

	// Warning text must match too - it is shown on the page and in the email.
	const phpWarnings = php.warnings.slice().sort();
	const jsWarnings = js.warnings.slice().sort();
	checks++;
	if (JSON.stringify(phpWarnings) !== JSON.stringify(jsWarnings)) {
		problems.push('  warnings differ:');
		problems.push(`    php: ${JSON.stringify(phpWarnings)}`);
		problems.push(`    js:  ${JSON.stringify(jsWarnings)}`);
	}

	if (problems.length) {
		failures++;
		console.log(`FAIL  ${scenario.name}`);
		problems.forEach((line) => console.log(line));
	} else {
		console.log(`ok    ${scenario.name}`);
		console.log(`        ${Math.round(php.daily_wh)} Wh/day -> ${php.panel_qty}x panels (${php.array_installed}W), `
			+ `${php.battery_qty}x battery (${php.bank_kwh.toFixed(2)} kWh), `
			+ `${php.inverter_rated}W inverter, ${php.controller_rated}A ${php.controller_type}, ${php.system_voltage}V`);
	}
});

console.log(`\n${checks} comparisons across ${scenarios.length} scenarios, ${failures} scenario(s) failed.`);
process.exit(failures ? 1 : 0);
