/**
 * Drive the generated preview in a real browser and assert the calculator
 * behaves: appliances add and remove, edits recalculate, the profile tabs
 * swap the library, custom appliances validate, and the results reflect the
 * load list.
 *
 * Usage: php tools/build-preview.php && node tools/test-ui.js
 */

'use strict';

const path = require('path');
const { chromium } = require('playwright');

const PREVIEW = 'file://' + path.join(__dirname, '..', 'preview', 'index.html');

let passed = 0;
let failed = 0;

function check(label, condition, detail) {
	if (condition) {
		passed++;
		console.log(`ok    ${label}`);
	} else {
		failed++;
		console.log(`FAIL  ${label}${detail ? ' - ' + detail : ''}`);
	}
}

(async () => {
	// Honour a preinstalled browser when the runner pins one that does not
	// match this Playwright build.
	const browser = await chromium.launch(
		process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}
	);
	const page = await browser.newPage({ viewport: { width: 1280, height: 1000 } });

	const errors = [];
	page.on('pageerror', (error) => errors.push(error.message));
	page.on('console', (msg) => {
		if (msg.type() === 'error') {
			errors.push(msg.text());
		}
	});

	await page.goto(PREVIEW);
	await page.waitForSelector('.spc-body:not([hidden])', { timeout: 5000 });

	/* --- the form renders and starts empty --- */
	check('calculator becomes visible', await page.isVisible('.spc-body'));
	check('starts with the empty-state message', await page.isVisible('.spc-results-empty'));
	check('results are hidden with no load', !(await page.isVisible('.spc-results')));

	const chipCount = await page.locator('.spc-chip').count();
	check('home profile shows appliances', chipCount > 15, `${chipCount} chips`);

	/* --- adding an appliance --- */
	await page.locator('.spc-chip', { hasText: 'LED bulb' }).first().click();
	await page.waitForSelector('.spc-results:not([hidden])');
	check('results appear after first appliance', await page.isVisible('.spc-results'));
	check('chip marks itself chosen', await page.locator('.spc-chip.is-chosen').count() === 1);

	// One 10W bulb for 5 hours = 50 Wh.
	let total = await page.textContent('.spc-total-wh');
	check('one 10W bulb x 5h = 50 Wh', total.trim() === '50 Wh', total);

	/* --- quantity edits recalculate --- */
	await page.fill('.spc-table tbody tr:first-child input[data-field="qty"]', '6');
	await page.waitForTimeout(80);
	total = await page.textContent('.spc-total-wh');
	check('six bulbs = 300 Wh', total.trim() === '300 Wh', total);

	/* --- a duty-cycled appliance --- */
	await page.locator('.spc-chip', { hasText: 'Refrigerator (150-200L)' }).first().click();
	await page.waitForTimeout(80);
	// 100W x 24h x 0.35 duty = 840 Wh, plus the 300 Wh of bulbs.
	total = await page.textContent('.spc-total-wh');
	check('fridge duty cycle applied (1,140 Wh)', total.replace(/[,\s]/g, '') === '1140Wh', total);
	check('duty-cycle tag shown on the row', await page.locator('.spc-tag-muted').count() === 1);

	/* --- results are populated --- */
	const daily = await page.textContent('.spc-r-daily');
	check('headline daily energy is 1.14 kWh', daily.trim() === '1.14', daily);

	const panelQty = parseInt(await page.textContent('.spc-r-panel-qty'), 10);
	check('panel count is a positive whole number', panelQty > 0, String(panelQty));

	const battQty = parseInt(await page.textContent('.spc-r-batt-qty'), 10);
	check('battery count is a positive whole number', battQty > 0, String(battQty));

	const inverter = await page.textContent('.spc-r-inverter');
	check('inverter is sized', parseInt(inverter.replace(/,/g, ''), 10) > 0, inverter);

	/* --- system voltage responds to load size --- */
	// 160W of connected load and a sub-800W array stays comfortably at 12V.
	const voltageSmall = await page.textContent('.spc-r-voltage');
	check('small load stays at 12V', voltageSmall.trim() === '12V', voltageSmall);

	await page.locator('.spc-chip', { hasText: 'Air conditioner 1.5HP' }).first().click();
	await page.waitForTimeout(80);
	const voltageBig = await page.textContent('.spc-r-voltage');
	check('adding a 1.5HP AC moves the system to 48V', voltageBig.trim() === '48V', voltageBig);
	check('warnings surface for the bigger system', await page.isVisible('.spc-warnings'));

	/* --- search spans every profile --- */
	await page.fill('.spc-search-input', 'compressor fridge');
	await page.waitForTimeout(80);
	const searchHits = await page.locator('.spc-chip', { hasText: '12V compressor fridge' }).count();
	const searchTotal = await page.locator('.spc-chip').count();
	check('search finds a travel appliance from the home tab', searchHits === 1 && searchTotal === 1,
		`${searchTotal} hits`);

	await page.fill('.spc-search-input', 'zzzzz');
	await page.waitForTimeout(80);
	check('no-match message shown', await page.isVisible('.spc-no-match'));
	await page.fill('.spc-search-input', '');
	await page.waitForTimeout(80);

	/* --- custom appliances --- */
	await page.click('.spc-custom > summary');
	await page.click('.spc-custom-add');
	check('custom appliance validates empty input', await page.isVisible('.spc-custom-error'));

	const beforeRows = await page.locator('.spc-table tbody tr').count();
	await page.fill('.spc-custom-name', 'Borehole pump');
	await page.fill('.spc-custom-watts', '750');
	await page.fill('.spc-custom-hours', '2');
	await page.click('.spc-custom-add');
	await page.waitForTimeout(80);
	const afterRows = await page.locator('.spc-table tbody tr').count();
	check('custom appliance is added', afterRows === beforeRows + 1, `${beforeRows} -> ${afterRows}`);
	check('custom appliance name appears', await page.locator('.spc-item-name', { hasText: 'Borehole pump' }).count() === 1);
	check('error clears after a valid add', !(await page.isVisible('.spc-custom-error')));

	/* --- DC loads skip inverter losses --- */
	await page.click('.spc-clear');
	await page.waitForTimeout(80);
	await page.fill('.spc-custom-name', 'DC load');
	await page.fill('.spc-custom-watts', '100');
	await page.fill('.spc-custom-hours', '10');
	await page.check('.spc-custom-dc');
	await page.click('.spc-custom-add');
	await page.waitForTimeout(80);
	check('DC tag shown', await page.locator('.spc-tag', { hasText: 'DC' }).count() === 1);
	const dcInverter = await page.textContent('.spc-r-inverter');
	check('a DC-only system needs no inverter', dcInverter.trim() === '0', dcInverter);

	/* --- profile switching --- */
	await page.click('.spc-clear');
	await page.click('.spc-profile[data-profile="travel"]');
	await page.waitForTimeout(80);
	check('travel profile is active', await page.locator('.spc-profile[data-profile="travel"].is-active').count() === 1);
	check('travel profile sets 2 days autonomy', await page.inputValue('.spc-autonomy') === '2');
	const travelChips = await page.locator('.spc-chip', { hasText: '12V compressor fridge' }).count();
	check('travel library shows RV appliances', travelChips === 1);

	await page.click('.spc-profile[data-profile="office"]');
	await page.waitForTimeout(80);
	const officeChips = await page.locator('.spc-chip', { hasText: 'Photocopier' }).count();
	check('office library shows office appliances', officeChips === 1);

	/* --- region / peak sun hours --- */
	await page.selectOption('.spc-region', 'north_europe');
	await page.waitForTimeout(80);
	check('region sets peak sun hours', await page.inputValue('.spc-psh') === '2.6');

	await page.selectOption('.spc-region', 'custom');
	await page.waitForTimeout(80);
	check('custom region reveals the manual field', await page.isVisible('.spc-field-psh'));

	/* --- fewer sun hours means more panels --- */
	await page.selectOption('.spc-region', 'nafrica_me');
	await page.locator('.spc-chip', { hasText: 'Desktop PC + monitor' }).first().click();
	await page.waitForTimeout(80);
	const sunnyPanels = parseInt(await page.textContent('.spc-r-panel-qty'), 10);
	await page.selectOption('.spc-region', 'north_europe');
	await page.waitForTimeout(80);
	const cloudyPanels = parseInt(await page.textContent('.spc-r-panel-qty'), 10);
	check('poor sun needs more panels', cloudyPanels > sunnyPanels, `${sunnyPanels} -> ${cloudyPanels}`);

	/* --- more autonomy means more batteries --- */
	await page.selectOption('.spc-autonomy', '1');
	await page.waitForTimeout(80);
	const oneDay = parseInt(await page.textContent('.spc-r-batt-qty'), 10);
	await page.selectOption('.spc-autonomy', '3');
	await page.waitForTimeout(80);
	const threeDay = parseInt(await page.textContent('.spc-r-batt-qty'), 10);
	check('3 days of backup needs more batteries', threeDay > oneDay, `${oneDay} -> ${threeDay}`);

	/* --- lead acid needs more nameplate capacity than lithium --- */
	await page.selectOption('.spc-battery-type', 'lithium');
	await page.waitForTimeout(80);
	const lithiumAh = await page.textContent('.spc-r-bank-ah');
	await page.selectOption('.spc-battery-type', 'flooded');
	await page.waitForTimeout(80);
	const floodedAh = await page.textContent('.spc-r-bank-ah');
	const toNum = (text) => parseFloat(text.replace(/[^\d.]/g, ''));
	check('lead-acid bank is larger than lithium', toNum(floodedAh) > toNum(lithiumAh), `${lithiumAh} vs ${floodedAh}`);

	/* --- removing rows --- */
	const rowsBefore = await page.locator('.spc-table tbody tr').count();
	await page.click('.spc-table tbody tr:first-child .spc-remove');
	await page.waitForTimeout(80);
	const rowsAfter = await page.locator('.spc-table tbody tr').count();
	check('remove button drops the row', rowsAfter === rowsBefore - 1, `${rowsBefore} -> ${rowsAfter}`);

	// Make sure there is something to clear, then clear it.
	await page.locator('.spc-chip', { hasText: 'Monitor 24"' }).first().click();
	await page.waitForTimeout(80);
	await page.click('.spc-clear');
	await page.waitForTimeout(80);
	check('clear all empties the list', await page.isVisible('.spc-results-empty'));
	check('clear all resets the chips', await page.locator('.spc-chip.is-chosen').count() === 0);

	/* --- no runtime errors anywhere in that run --- */
	check('no JavaScript errors', errors.length === 0, errors.join(' | '));

	/* --- screenshots for the record --- */
	await page.click('.spc-profile[data-profile="home"]');
	await page.locator('.spc-chip', { hasText: 'LED bulb' }).first().click();
	await page.locator('.spc-chip', { hasText: 'Standing / table fan' }).first().click();
	await page.locator('.spc-chip', { hasText: 'Refrigerator (150-200L)' }).first().click();
	await page.locator('.spc-chip', { hasText: 'LED TV 32"' }).first().click();
	await page.locator('.spc-chip', { hasText: 'Wi-Fi router' }).first().click();
	await page.waitForTimeout(150);
	await page.screenshot({ path: path.join(__dirname, '..', 'preview', 'screenshot.png'), fullPage: true });

	/* --- mobile layout does not overflow --- */
	await page.setViewportSize({ width: 390, height: 844 });
	await page.waitForTimeout(150);
	const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
	check('no horizontal overflow on mobile', overflow <= 1, `${overflow}px`);
	await page.screenshot({ path: path.join(__dirname, '..', 'preview', 'screenshot-mobile.png'), fullPage: true });

	await browser.close();

	console.log(`\n${passed} passed, ${failed} failed.`);
	process.exit(failed ? 1 : 0);
})();
