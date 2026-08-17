/**
 * Solar Power Calculator - front-end.
 *
 * Runs the same sizing model as includes/class-spc-calculator.php so results
 * update instantly as the visitor changes anything. All constants come from
 * window.SPC_DATA (printed by PHP), so only the formulas live in both places.
 * If you change a formula here, change it there too.
 */
(function () {
	'use strict';

	var DATA = window.SPC_DATA;
	if (!DATA) {
		return;
	}

	var C = DATA.constants;
	var S = DATA.settings;

	/* ------------------------------------------------------------ helpers */

	function num(value, decimals) {
		var n = isFinite(value) ? value : 0;
		return n.toLocaleString(undefined, {
			minimumFractionDigits: decimals || 0,
			maximumFractionDigits: decimals || 0
		});
	}

	function money(value) {
		return S.currency + num(Math.round(value));
	}

	function roundUpTo(value, sizes) {
		for (var i = 0; i < sizes.length; i++) {
			if (value <= sizes[i]) {
				return sizes[i];
			}
		}
		return Math.ceil(value);
	}

	function clamp(value, min, max) {
		return Math.min(max, Math.max(min, value));
	}

	function findUnit(id) {
		for (var i = 0; i < C.battery_units.length; i++) {
			if (C.battery_units[i].id === id) {
				return C.battery_units[i];
			}
		}
		return C.battery_units[2];
	}

	/**
	 * Decide whether the calculator sits on a light or a dark page.
	 *
	 * Walks up from the calculator looking for the first ancestor that actually
	 * paints a background, and measures how bright it is. This is checked
	 * against the page rather than the device's dark-mode preference, because
	 * most WordPress themes ignore that preference — trusting it turned the
	 * calculator dark inside a light theme, leaving the theme's dark text
	 * sitting on dark input boxes.
	 *
	 * @param {Element} root The .spc element.
	 * @return {string} 'dark' or 'light'.
	 */
	function detectTheme(root) {
		// Start at the parent: .spc paints its own background from the palette
		// we are trying to choose, so measuring itself would be circular.
		var node = root.parentElement;

		while (node) {
			var background = window.getComputedStyle(node).backgroundColor;
			var rgb = parseColor(background);

			// Skip transparent ancestors - they show whatever is behind them.
			if (rgb && rgb.a > 0.1) {
				return luminance(rgb) < 0.5 ? 'dark' : 'light';
			}
			node = node.parentElement;
		}

		// Nothing in the chain paints a background: assume a white page, which
		// is what a browser renders by default.
		return 'light';
	}

	/**
	 * Parse an rgb()/rgba() colour as returned by getComputedStyle.
	 *
	 * @param {string} value Computed colour.
	 * @return {Object|null} {r, g, b, a} or null when unparseable.
	 */
	function parseColor(value) {
		var match = /^rgba?\(([^)]+)\)$/.exec((value || '').trim());
		if (!match) {
			return null;
		}

		var parts = match[1].split(/[,\s/]+/).filter(function (part) {
			return part !== '';
		}).map(parseFloat);

		if (parts.length < 3 || parts.some(isNaN)) {
			return null;
		}

		return { r: parts[0], g: parts[1], b: parts[2], a: parts.length > 3 ? parts[3] : 1 };
	}

	/**
	 * Relative luminance, 0 (black) to 1 (white).
	 *
	 * @param {Object} rgb Colour channels.
	 * @return {number}
	 */
	function luminance(rgb) {
		var channels = [rgb.r, rgb.g, rgb.b].map(function (channel) {
			var c = channel / 255;
			return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
		});

		return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
	}

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text == null ? '' : String(text);
		return div.innerHTML;
	}

	/* --------------------------------------------------------- the model */

	/**
	 * Size a system. Mirror of SPC_Calculator::calculate().
	 *
	 * @param {Array}  items  Load list.
	 * @param {Object} config System choices.
	 * @return {Object} Sizing results.
	 */
	function calculate(items, config) {
		var battery = DATA.batteries[config.batteryType] || DATA.batteries.lithium;
		var inverterEff = C.inverter_efficiency[config.inverterType] || C.inverter_efficiency.pure_sine;
		var controllerEff = C.controller_efficiency[config.controller] || C.controller_efficiency.mppt;

		var psh = Math.max(0.5, config.psh);

		// Backup in hours so intermittent-mains setups can ask for "cover the
		// 10-hour outage". The older autonomy-in-days input still works.
		var backupHours = config.backupHours != null
			? clamp(config.backupHours, 1, 168)
			: Math.max(0.5, config.autonomy) * 24;

		var gridHours = clamp(config.gridHours || 0, 0, 24);
		var gridCharges = gridHours > 0 && !!config.gridCharges;
		var solarShare = clamp(config.solarShare == null ? 1 : config.solarShare, 0.05, 1);

		/* 1. Daily energy */
		var acWh = 0;
		var dcWh = 0;
		var acConnectedW = 0;
		var dcConnectedW = 0;
		var largestSurge = 0;
		var largestAcW = 0;

		items.forEach(function (item) {
			var watts = Math.max(0, item.watts);
			var qty = Math.max(0, Math.round(item.qty));
			var hours = clamp(item.hours, 0, 24);
			var duty = clamp(item.duty == null ? 1 : item.duty, 0.01, 1);
			var surge = Math.max(1, item.surge == null ? 1 : item.surge);

			if (qty < 1 || watts <= 0) {
				return;
			}

			var connected = watts * qty;
			var energy = connected * hours * duty;

			if (item.dc) {
				dcWh += energy;
				dcConnectedW += connected;
			} else {
				acWh += energy;
				acConnectedW += connected;
				largestAcW = Math.max(largestAcW, watts);
				// Worst single motor start, on top of everything already running.
				largestSurge = Math.max(largestSurge, watts * surge - watts);
			}
		});

		var dailyWh = acWh + dcWh;
		// AC loads pay the inverter conversion penalty; DC loads do not.
		var batteryDrawWh = (inverterEff > 0 ? acWh / inverterEff : 0) + dcWh;

		/* 2. Solar array - independent of system voltage, so it comes first.
		 * Only covers the share of the load the owner wants from solar; mains
		 * carries the rest where it is available.
		 */
		var solarDrawWh = batteryDrawWh * solarShare;
		var systemEfficiency = controllerEff * C.array_derate * battery.efficiency;
		var arrayWatts = solarDrawWh > 0 && systemEfficiency > 0
			? solarDrawWh / (psh * systemEfficiency)
			: 0;
		var panelWatts = Math.max(10, config.panelWatts);
		var panelQty = arrayWatts > 0 ? Math.ceil(arrayWatts / panelWatts) : 0;
		var arrayInstalled = panelQty * panelWatts;

		/* 3. System voltage
		 * A current problem, not an energy one: picked so neither the inverter
		 * draw nor the array charge current needs impractical cable and fusing.
		 */
		var systemVoltage;
		if (config.voltage === 'auto') {
			if (acConnectedW <= 1200 && arrayInstalled <= 800 && batteryDrawWh <= 2000) {
				systemVoltage = 12;
			} else if (acConnectedW <= 3000 && arrayInstalled <= 2500 && batteryDrawWh <= 6000) {
				systemVoltage = 24;
			} else {
				systemVoltage = 48;
			}
		} else {
			systemVoltage = parseInt(config.voltage, 10);
		}

		/* 4. Battery bank */
		var bankWh = batteryDrawWh > 0
			? (batteryDrawWh * (backupHours / 24)) / (battery.dod * battery.efficiency)
			: 0;
		var bankAh = systemVoltage > 0 ? bankWh / systemVoltage : 0;
		var usableKwh = (bankWh * battery.dod) / 1000;

		var unit = findUnit(config.batteryUnit);
		var series = Math.max(1, Math.ceil(systemVoltage / Math.max(1, unit.volts)));
		var strings = bankAh > 0 ? Math.max(1, Math.ceil(bankAh / Math.max(1, unit.ah))) : 0;
		var batteryQty = series * strings;

		/* 5. Inverter */
		var simultaneity = clamp(config.simultaneity, 0.1, 1);
		// Not everything runs at once, but the inverter must still be able to
		// carry the single biggest appliance on its own.
		var runningW = Math.max(acConnectedW * simultaneity, largestAcW);
		var inverterW = runningW * C.safety_factor;
		var inverterRated = runningW > 0 ? roundUpTo(inverterW, C.inverter_sizes) : 0;
		var inverterVa = C.power_factor > 0 ? inverterW / C.power_factor : 0;
		var surgeW = runningW + largestSurge;

		/* 6. Charge controller */
		var controllerA = arrayInstalled > 0 && systemVoltage > 0
			? (arrayInstalled / systemVoltage) * C.safety_factor
			: 0;
		var controllerRated = controllerA > 0 ? roundUpTo(controllerA, C.controller_sizes) : 0;

		/* 7. Runtime with no sun and no mains */
		var runtimeHours = batteryDrawWh > 0
			? (bankWh * battery.dod) / (batteryDrawWh / 24)
			: 0;

		/* 8. Charging from mains
		 * Where mains or a generator runs for part of the day it can refill the
		 * bank as well as carry the load. The question is whether the window is
		 * long enough to do it at a rate the chemistry accepts.
		 */
		var usableWh = bankWh * battery.dod;
		var chargerANeeded = 0;
		var chargerASafe = 0;
		var chargerARated = 0;
		var refillHours = 0;
		var gridDailyWh = 0;
		var solarDailyWh = dailyWh;

		if (gridHours > 0) {
			gridDailyWh = dailyWh * (1 - solarShare);
			solarDailyWh = dailyWh * solarShare;
		}

		if (gridCharges && usableWh > 0 && systemVoltage > 0) {
			chargerANeeded = usableWh / (gridHours * C.charger_efficiency * systemVoltage);
			chargerASafe = bankAh * battery.max_charge_c;
			// Never recommend a charger the batteries cannot absorb.
			chargerARated = Math.min(
				roundUpTo(Math.min(chargerANeeded, chargerASafe), C.controller_sizes),
				chargerASafe
			);
			if (chargerARated > 0) {
				refillHours = usableWh / (chargerARated * systemVoltage * C.charger_efficiency);
			}
		}

		/* 9. Cost and impact */
		var annualKwh = (dailyWh * 365) / 1000;
		// Only the solar share displaces grid or generator energy.
		var annualSolarKwh = (solarDailyWh * 365) / 1000;
		var costPv = arrayInstalled * S.costPvWatt;
		var costBattery = (bankWh / 1000) * battery.cost_kwh;
		var costInverter = (inverterRated / 1000) * S.costInverterKw;
		var costHardware = costPv + costBattery + costInverter;
		var costInstall = costHardware * (S.costInstallPct / 100);

		var results = {
			dailyWh: dailyWh,
			dailyKwh: dailyWh / 1000,
			annualKwh: annualKwh,
			annualSolarKwh: annualSolarKwh,
			solarDailyWh: solarDailyWh,
			gridDailyWh: gridDailyWh,
			solarShare: solarShare,
			batteryDrawWh: batteryDrawWh,
			runningW: runningW,
			surgeW: surgeW,

			systemVoltage: systemVoltage,
			psh: psh,
			backupHours: backupHours,
			autonomy: backupHours / 24,
			gridHours: gridHours,
			gridCharges: gridCharges,

			arrayWatts: arrayWatts,
			arrayInstalled: arrayInstalled,
			panelWatts: panelWatts,
			panelQty: panelQty,
			dailyHarvestWh: arrayInstalled * psh * controllerEff * C.array_derate,

			bankWh: bankWh,
			bankKwh: bankWh / 1000,
			bankAh: bankAh,
			usableKwh: usableKwh,
			batteryLabel: battery.label,
			batteryUnit: unit.label,
			batterySeries: series,
			batteryStrings: strings,
			batteryQty: batteryQty,
			runtimeHours: runtimeHours,

			chargerANeeded: chargerANeeded,
			chargerASafe: chargerASafe,
			chargerARated: chargerARated,
			chargerWatts: chargerARated * systemVoltage,
			refillHours: refillHours,

			inverterW: inverterW,
			inverterRated: inverterRated,
			inverterVa: inverterVa,

			controllerA: controllerA,
			controllerRated: controllerRated,
			controllerType: config.controller.toUpperCase(),

			costPv: costPv,
			costBattery: costBattery,
			costInverter: costInverter,
			costInstall: costInstall,
			costTotal: costHardware + costInstall,

			co2SavedKg: annualSolarKwh * C.co2_per_kwh,
			treesEquivalent: C.co2_per_tree_year > 0 ? (annualSolarKwh * C.co2_per_kwh) / C.co2_per_tree_year : 0,
			generatorLitres: annualSolarKwh * C.generator_l_per_kwh
		};

		results.warnings = warnings(results, config);

		return results;
	}

	/**
	 * Practical cautions. Mirror of SPC_Calculator::warnings().
	 */
	function warnings(r, config) {
		var list = [];

		if (r.dailyWh <= 0) {
			return list;
		}

		if (r.systemVoltage === 12 && r.runningW > 1200) {
			list.push('At 12V this load draws over 100A. Move to 24V or 48V to keep cable sizes and losses sensible.');
		}
		if (r.controllerRated > 100) {
			list.push('The array needs more charge current than a single controller usually handles. Split it across two or more controllers.');
		}
		if (r.inverterRated > 0 && r.surgeW > r.inverterRated * 2) {
			list.push('Startup surge is high. Check that your inverter can deliver ' + num(r.surgeW) + 'W for a few seconds, or stagger the motor loads.');
		}
		if (config.controller === 'pwm' && r.arrayInstalled > 800) {
			list.push('PWM controllers waste 15-20% of the harvest on arrays this size. MPPT pays for itself here.');
		}
		if (r.gridHours === 0 && r.backupHours < 24) {
			list.push('The battery is sized for only ' + Math.round(r.backupHours) + ' hours and you have no mains or generator to fall back on. Consider a full day.');
		}
		// The heart of an intermittent-mains system: a short window may not be
		// long enough to put the energy back at a safe charge rate.
		if (r.gridCharges && r.refillHours > r.gridHours) {
			list.push('Your ' + Math.round(r.gridHours) + ' hours of mains is not long enough to fully recharge this bank — a safe charge rate needs about '
				+ Math.round(r.refillHours) + ' hours. Expect to start some days part-charged, so lean on solar for the difference or fit a smaller bank.');
		}
		if (r.gridHours > 0 && !r.gridCharges) {
			list.push('Mains is available but is not charging the batteries, so solar has to do all the recharging. A hybrid inverter/charger would let the mains share the work.');
		}
		if (r.gridHours >= 20 && r.solarShare <= 0.5) {
			list.push('With mains available nearly all day and solar covering part of the load, this is a bill-reduction system rather than a backup one. Size the battery for the outages you actually get, not for a full day.');
		}
		if (r.bankWh > 0 && r.arrayInstalled > 0 && r.arrayInstalled / r.bankWh < 0.1) {
			list.push('The array is small relative to the bank, so a deeply discharged bank will take more than a day to recover.');
		}

		return list;
	}

	/* ----------------------------------------------------------- the UI */

	function Calculator(root) {
		this.root = root;
		this.profile = root.getAttribute('data-profile') || 'home';
		this.items = [];
		this.customId = 0;
		this.q = function (selector) {
			return root.querySelector(selector);
		};
		this.qa = function (selector) {
			return Array.prototype.slice.call(root.querySelectorAll(selector));
		};

		// Match the surrounding page unless the shortcode pinned a theme.
		if (!root.hasAttribute('data-theme')) {
			var applyTheme = function () {
				root.setAttribute('data-theme', detectTheme(root));
			};
			applyTheme();
			// Caching and optimisation plugins often load the theme's CSS
			// asynchronously, so the page can still be unstyled at this point.
			// Measure again once everything has arrived.
			if (document.readyState !== 'complete') {
				window.addEventListener('load', applyTheme, { once: true });
			}
		}

		this.bind();
		this.renderLibrary();
		this.update();

		var body = this.q('.spc-body');
		if (body) {
			body.hidden = false;
		}
	}

	Calculator.prototype.bind = function () {
		var self = this;

		// Profile tabs.
		this.qa('.spc-profile').forEach(function (button) {
			button.addEventListener('click', function () {
				self.profile = button.getAttribute('data-profile');
				self.qa('.spc-profile').forEach(function (other) {
					var active = other === button;
					other.classList.toggle('is-active', active);
					other.setAttribute('aria-checked', active ? 'true' : 'false');
				});
				var profile = DATA.profiles[self.profile];
				if (profile && !self.backupTouched) {
					self.q('.spc-backup').value = String(profile.backup_hours);
				}
				self.renderLibrary();
				self.update();
			});
		});

		// Appliance search.
		this.q('.spc-search-input').addEventListener('input', function () {
			self.renderLibrary(this.value);
		});

		// Load list edits (delegated - rows come and go).
		this.q('.spc-table tbody').addEventListener('input', function (event) {
			var input = event.target;
			var row = input.closest('tr');
			if (!row) {
				return;
			}
			var item = self.items[parseInt(row.getAttribute('data-index'), 10)];
			if (!item) {
				return;
			}
			var field = input.getAttribute('data-field');
			var value = parseFloat(input.value);
			if (isNaN(value)) {
				return;
			}
			if (field === 'qty') {
				item.qty = clamp(Math.round(value), 0, 999);
			} else if (field === 'watts') {
				item.watts = clamp(value, 0, 50000);
			} else if (field === 'hours') {
				item.hours = clamp(value, 0, 24);
			}
			self.update();
		});

		this.q('.spc-table tbody').addEventListener('click', function (event) {
			var button = event.target.closest('.spc-remove');
			if (!button) {
				return;
			}
			var index = parseInt(button.closest('tr').getAttribute('data-index'), 10);
			self.items.splice(index, 1);
			self.renderItems();
			self.renderLibrary(self.q('.spc-search-input').value);
			self.update();
		});

		this.q('.spc-clear').addEventListener('click', function () {
			self.items = [];
			self.renderItems();
			self.renderLibrary(self.q('.spc-search-input').value);
			self.update();
		});

		// Custom appliance.
		this.q('.spc-custom-add').addEventListener('click', function () {
			self.addCustom();
		});
		this.q('.spc-custom-name').addEventListener('keydown', function (event) {
			if (event.key === 'Enter') {
				event.preventDefault();
				self.addCustom();
			}
		});

		// Region select swaps in a manual peak-sun-hours field.
		this.q('.spc-region').addEventListener('change', function () {
			var custom = this.value === 'custom';
			self.q('.spc-field-psh').hidden = !custom;
			if (!custom) {
				var option = this.options[this.selectedIndex];
				self.q('.spc-psh').value = option.getAttribute('data-psh');
			}
			self.update();
		});

		// Mains hours: reveal the charging option and suggest a backup target.
		this.q('.spc-grid-hours').addEventListener('change', function () {
			var hours = parseFloat(this.value) || 0;
			self.q('.spc-field-gridcharge').hidden = hours <= 0;

			if (!self.backupTouched) {
				// Default to covering the outage, which is what is left of the day.
				self.q('.spc-backup').value = self.nearestBackup(hours > 0 ? 24 - hours : 24);
			}
			self.update();
		});

		this.q('.spc-grid-charges').addEventListener('change', function () {
			self.update();
		});

		// Once the visitor picks a backup figure, stop overwriting it.
		this.q('.spc-backup').addEventListener('change', function () {
			self.backupTouched = true;
			self.update();
		});

		// Simultaneity slider label.
		var slider = this.q('.spc-simultaneity');
		slider.addEventListener('input', function () {
			self.q('.spc-simultaneity-out').textContent = this.value + '%';
			self.update();
		});

		// Every other control just recalculates.
		['.spc-psh', '.spc-battery-type', '.spc-panel', '.spc-battery-unit', '.spc-solar-share',
			'.spc-voltage', '.spc-inverter-type', '.spc-controller'].forEach(function (selector) {
			var field = self.q(selector);
			if (field) {
				field.addEventListener('change', function () {
					self.update();
				});
			}
		});

		this.q('.spc-print').addEventListener('click', function () {
			window.print();
		});

		this.bindEmail();

		// Seed peak sun hours from the preselected region.
		var region = this.q('.spc-region');
		var selected = region.options[region.selectedIndex];
		if (selected && selected.getAttribute('data-psh')) {
			this.q('.spc-psh').value = selected.getAttribute('data-psh');
		}
	};

	Calculator.prototype.bindEmail = function () {
		var self = this;
		var toggle = this.q('.spc-email-toggle');
		var form = this.q('.spc-email-form');

		if (!toggle || !form) {
			return;
		}

		toggle.addEventListener('click', function () {
			form.hidden = !form.hidden;
			if (!form.hidden) {
				self.q('.spc-email-input').focus();
			}
		});

		form.addEventListener('submit', function (event) {
			event.preventDefault();

			var status = self.q('.spc-email-status');
			var button = self.q('.spc-email-send');
			var payload = new FormData();

			payload.append('action', 'spc_email_results');
			payload.append('nonce', DATA.nonce);
			payload.append('email', self.q('.spc-email-input').value);
			payload.append('name', self.q('.spc-email-name').value);
			payload.append('items', JSON.stringify(self.items));
			payload.append('config', JSON.stringify(self.serverConfig()));

			button.disabled = true;
			status.textContent = 'Sending...';
			status.className = 'spc-email-status';

			fetch(DATA.ajaxUrl, { method: 'POST', body: payload, credentials: 'same-origin' })
				.then(function (response) {
					return response.json();
				})
				.then(function (json) {
					var ok = json && json.success;
					status.textContent = (json && json.data && json.data.message) || (ok ? 'Sent.' : 'Something went wrong.');
					status.className = 'spc-email-status ' + (ok ? 'is-ok' : 'is-error');
					if (ok) {
						form.reset();
					}
				})
				.catch(function () {
					status.textContent = 'Network error. Please try again.';
					status.className = 'spc-email-status is-error';
				})
				.finally(function () {
					button.disabled = false;
				});
		});
	};

	/**
	 * Current system choices, in the shape the model expects.
	 */
	Calculator.prototype.config = function () {
		return {
			psh: parseFloat(this.q('.spc-psh').value) || 4.5,
			backupHours: parseFloat(this.q('.spc-backup').value) || 24,
			gridHours: parseFloat(this.q('.spc-grid-hours').value) || 0,
			gridCharges: this.q('.spc-grid-charges').checked,
			solarShare: (parseFloat(this.q('.spc-solar-share').value) || 100) / 100,
			batteryType: this.q('.spc-battery-type').value,
			voltage: this.q('.spc-voltage').value,
			inverterType: this.q('.spc-inverter-type').value,
			controller: this.q('.spc-controller').value,
			panelWatts: parseFloat(this.q('.spc-panel').value) || 550,
			batteryUnit: this.q('.spc-battery-unit').value,
			simultaneity: (parseFloat(this.q('.spc-simultaneity').value) || 70) / 100
		};
	};

	/**
	 * Same choices, keyed the way the PHP endpoint reads them.
	 */
	Calculator.prototype.serverConfig = function () {
		var c = this.config();
		return {
			psh: c.psh,
			backup_hours: c.backupHours,
			grid_hours: c.gridHours,
			grid_charges: c.gridCharges,
			solar_share: c.solarShare,
			battery_type: c.batteryType,
			voltage: c.voltage,
			inverter_type: c.inverterType,
			controller: c.controller,
			panel_watts: c.panelWatts,
			battery_unit: c.batteryUnit,
			simultaneity: c.simultaneity
		};
	};

	/**
	 * Pick the smallest backup option that still covers the given outage.
	 *
	 * Rounds up rather than to the nearest: choosing 12 hours to cover a
	 * 16-hour outage would leave the visitor short, which is the one direction
	 * the suggestion must never err in.
	 *
	 * @param {number} hours Hours the battery needs to carry the load.
	 * @return {string} The option value to select.
	 */
	Calculator.prototype.nearestBackup = function (hours) {
		var options = Array.prototype.map.call(this.q('.spc-backup').options, function (option) {
			return parseFloat(option.value);
		}).sort(function (a, b) {
			return a - b;
		});

		for (var i = 0; i < options.length; i++) {
			if (options[i] >= hours) {
				return String(options[i]);
			}
		}

		return String(options[options.length - 1]);
	};

	/**
	 * Draw the appliance picker for the current profile and search term.
	 */
	Calculator.prototype.renderLibrary = function (search) {
		var self = this;
		var term = (search || '').trim().toLowerCase();
		var chosen = {};

		this.items.forEach(function (item) {
			chosen[item.id] = true;
		});

		var matches = DATA.appliances.filter(function (appliance) {
			var inProfile = appliance.profiles.indexOf(self.profile) !== -1;
			if (term) {
				// A search looks across every profile, not just the active tab.
				return appliance.name.toLowerCase().indexOf(term) !== -1;
			}
			return inProfile;
		});

		var groups = {};
		var order = [];
		matches.forEach(function (appliance) {
			if (!groups[appliance.group]) {
				groups[appliance.group] = [];
				order.push(appliance.group);
			}
			groups[appliance.group].push(appliance);
		});

		var html = '';
		if (!matches.length) {
			html = '<p class="spc-no-match">No appliance matches "' + escapeHtml(term) + '". Add it as a custom appliance below.</p>';
		}

		order.forEach(function (group) {
			html += '<div class="spc-group"><h5 class="spc-group-title">' + escapeHtml(group) + '</h5><div class="spc-chips">';
			groups[group].forEach(function (appliance) {
				var isChosen = !!chosen[appliance.id];
				html += '<button type="button" class="spc-chip' + (isChosen ? ' is-chosen' : '') + '"'
					+ ' data-id="' + escapeHtml(appliance.id) + '"'
					+ ' aria-pressed="' + (isChosen ? 'true' : 'false') + '">'
					+ '<span class="spc-chip-name">' + escapeHtml(appliance.name) + '</span>'
					+ '<span class="spc-chip-watts">' + num(appliance.watts) + 'W'
					+ (appliance.dc ? ' &middot; DC' : '') + '</span>'
					+ '</button>';
			});
			html += '</div></div>';
		});

		var library = this.q('.spc-library');
		library.innerHTML = html;

		library.querySelectorAll('.spc-chip').forEach(function (chip) {
			chip.addEventListener('click', function () {
				self.toggleAppliance(chip.getAttribute('data-id'));
			});
		});
	};

	/**
	 * Add an appliance to the load list, or take it back off.
	 */
	Calculator.prototype.toggleAppliance = function (id) {
		var existing = -1;
		this.items.forEach(function (item, index) {
			if (item.id === id) {
				existing = index;
			}
		});

		if (existing > -1) {
			this.items.splice(existing, 1);
		} else {
			var appliance = null;
			DATA.appliances.forEach(function (candidate) {
				if (candidate.id === id) {
					appliance = candidate;
				}
			});
			if (!appliance) {
				return;
			}
			this.items.push({
				id: appliance.id,
				name: appliance.name,
				watts: appliance.watts,
				qty: 1,
				hours: appliance.hours,
				duty: appliance.duty,
				surge: appliance.surge,
				dc: !!appliance.dc
			});
		}

		this.renderItems();
		this.renderLibrary(this.q('.spc-search-input').value);
		this.update();
	};

	/**
	 * Add a load the library does not cover.
	 */
	Calculator.prototype.addCustom = function () {
		var nameField = this.q('.spc-custom-name');
		var wattsField = this.q('.spc-custom-watts');
		var hoursField = this.q('.spc-custom-hours');
		var error = this.q('.spc-custom-error');

		var name = nameField.value.trim();
		var watts = parseFloat(wattsField.value);
		var hours = parseFloat(hoursField.value);

		if (!name || !(watts > 0) || !(hours >= 0)) {
			error.textContent = 'Give the appliance a name, its wattage and how many hours a day it runs.';
			error.hidden = false;
			return;
		}

		error.hidden = true;
		this.customId++;

		this.items.push({
			id: 'custom-' + this.customId,
			name: name,
			watts: clamp(watts, 1, 50000),
			qty: 1,
			hours: clamp(hours, 0, 24),
			duty: 1,
			surge: 1,
			dc: this.q('.spc-custom-dc').checked,
			custom: true
		});

		nameField.value = '';
		wattsField.value = '';
		hoursField.value = '';
		this.q('.spc-custom-dc').checked = false;
		nameField.focus();

		this.renderItems();
		this.update();
	};

	/**
	 * Draw the editable load list.
	 */
	Calculator.prototype.renderItems = function () {
		var body = this.q('.spc-table tbody');
		var table = this.q('.spc-table');
		var empty = this.q('.spc-empty');
		var clear = this.q('.spc-clear');

		var hasItems = this.items.length > 0;
		table.hidden = !hasItems;
		empty.hidden = hasItems;
		clear.hidden = !hasItems;
		// Narrow screens scroll the table sideways, so the total gets its own
		// line underneath where it stays visible. CSS decides which one shows.
		this.q('.spc-total-mobile').hidden = !hasItems;

		var html = '';
		this.items.forEach(function (item, index) {
			var wh = item.watts * item.qty * item.hours * (item.duty == null ? 1 : item.duty);
			html += '<tr data-index="' + index + '">'
				+ '<td class="spc-col-name">'
				+ '<span class="spc-item-name">' + escapeHtml(item.name) + '</span>'
				+ (item.dc ? '<span class="spc-tag">DC</span>' : '')
				+ (item.duty < 1 ? '<span class="spc-tag spc-tag-muted" title="Thermostat controlled - runs about '
					+ Math.round(item.duty * 100) + '% of the time">' + Math.round(item.duty * 100) + '% duty</span>' : '')
				+ '</td>'
				+ '<td class="spc-col-num"><input type="number" data-field="qty" min="0" max="999" step="1" value="' + item.qty + '" aria-label="Quantity of ' + escapeHtml(item.name) + '"></td>'
				+ '<td class="spc-col-num"><input type="number" data-field="watts" min="0" max="50000" step="1" value="' + item.watts + '" aria-label="Watts for ' + escapeHtml(item.name) + '"></td>'
				+ '<td class="spc-col-num"><input type="number" data-field="hours" min="0" max="24" step="0.25" value="' + item.hours + '" aria-label="Hours per day for ' + escapeHtml(item.name) + '"></td>'
				+ '<td class="spc-col-num spc-item-wh">' + num(wh) + '</td>'
				+ '<td class="spc-col-action"><button type="button" class="spc-remove" aria-label="Remove ' + escapeHtml(item.name) + '">&times;</button></td>'
				+ '</tr>';
		});

		body.innerHTML = html;
	};

	/**
	 * Recalculate and paint the results.
	 */
	Calculator.prototype.update = function () {
		var self = this;
		var results = calculate(this.items, this.config());
		var hasLoad = results.dailyWh > 0;

		this.qa('.spc-total-wh').forEach(function (node) {
			node.textContent = num(results.dailyWh) + ' Wh';
		});
		this.q('.spc-results').hidden = !hasLoad;
		this.q('.spc-results-empty').hidden = hasLoad;

		// Keep the per-row energy figures in step with edited quantities.
		this.qa('.spc-table tbody tr').forEach(function (row) {
			var item = self.items[parseInt(row.getAttribute('data-index'), 10)];
			if (item) {
				var wh = item.watts * item.qty * item.hours * (item.duty == null ? 1 : item.duty);
				row.querySelector('.spc-item-wh').textContent = num(wh);
			}
		});

		if (!hasLoad) {
			return;
		}

		var set = function (selector, value) {
			self.qa(selector).forEach(function (node) {
				node.textContent = value;
			});
		};

		set('.spc-r-daily', num(results.dailyKwh, 2));
		set('.spc-r-array', num(results.arrayInstalled));
		set('.spc-r-bank', num(results.bankKwh, 1));

		set('.spc-r-panel-qty', num(results.panelQty));
		set('.spc-r-panel-size', num(results.panelWatts));
		set('.spc-r-array-min', num(Math.ceil(results.arrayWatts)) + ' W');
		set('.spc-r-array-installed', num(results.arrayInstalled) + ' W');
		set('.spc-r-harvest', num(results.dailyHarvestWh) + ' Wh/day');
		set('.spc-r-psh', num(results.psh, 1) + ' h');

		set('.spc-r-batt-qty', num(results.batteryQty));
		set('.spc-r-batt-unit', results.batteryUnit);
		set('.spc-r-bank-ah', num(results.bankAh) + ' Ah @ ' + results.systemVoltage + 'V');
		set('.spc-r-bank-kwh', num(results.bankKwh, 2) + ' kWh');
		set('.spc-r-usable', num(results.usableKwh, 2) + ' kWh');
		set('.spc-r-batt-config', results.batteryStrings > 1
			? results.batterySeries + ' in series × ' + results.batteryStrings + ' parallel'
			: results.batterySeries + ' in series');
		set('.spc-r-runtime', num(results.runtimeHours, 1) + ' h at average draw');

		set('.spc-r-inverter', num(results.inverterRated));
		set('.spc-r-running', num(results.runningW) + ' W');
		set('.spc-r-surge', num(results.surgeW) + ' W');
		set('.spc-r-va', num(results.inverterVa) + ' VA');
		set('.spc-r-voltage', results.systemVoltage + 'V');
		set('.spc-r-voltage-2', results.systemVoltage + 'V');

		set('.spc-r-controller', num(results.controllerRated));
		set('.spc-r-controller-type', results.controllerType);
		set('.spc-r-controller-calc', num(results.controllerA, 1) + ' A');

		set('.spc-r-annual', num(results.annualSolarKwh) + ' kWh');
		set('.spc-r-co2', num(results.co2SavedKg) + ' kg');
		set('.spc-r-trees', num(results.treesEquivalent) + ' trees');
		set('.spc-r-fuel', num(results.generatorLitres) + ' L');

		if (S.showCosts && this.root.getAttribute('data-costs') === '1') {
			set('.spc-r-cost-total', money(results.costTotal));
			set('.spc-r-cost-pv', money(results.costPv));
			set('.spc-r-cost-batt', money(results.costBattery));
			set('.spc-r-cost-inv', money(results.costInverter));
			set('.spc-r-cost-install', money(results.costInstall));
		}

		// The mains card only makes sense once mains hours are set.
		var mainsCard = this.q('.spc-card-mains');
		mainsCard.hidden = results.gridHours <= 0;
		if (results.gridHours > 0) {
			set('.spc-r-charger', num(results.chargerARated));
			set('.spc-r-grid-hours', num(results.gridHours) + ' h/day');
			set('.spc-r-refill', results.refillHours > 0 ? num(results.refillHours, 1) + ' h' : 'not charging');
			set('.spc-r-charger-safe', num(results.chargerASafe) + ' A');
			set('.spc-r-mix-solar', num(results.solarDailyWh / 1000, 2) + ' kWh/day');
			// With solar covering the whole load, mains contributes nothing to the
			// daily budget and is there purely as insurance. Say so.
			set('.spc-r-mix-grid', results.gridDailyWh > 0
				? num(results.gridDailyWh / 1000, 2) + ' kWh/day'
				: 'backup only');
		}

		var warningBox = this.q('.spc-warnings');
		var warningList = warningBox.querySelector('ul');
		warningBox.hidden = results.warnings.length === 0;
		warningList.innerHTML = results.warnings.map(function (warning) {
			return '<li>' + escapeHtml(warning) + '</li>';
		}).join('');
	};

	/* ------------------------------------------------------------- start */

	function boot() {
		Array.prototype.slice.call(document.querySelectorAll('.spc')).forEach(function (root) {
			if (root.getAttribute('data-spc-ready')) {
				return;
			}
			root.setAttribute('data-spc-ready', '1');
			new Calculator(root);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	// Exposed so tools/test-parity.js can check this model against the PHP one.
	// Harmless in the browser: WordPress never defines module.exports.
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = { calculate: calculate };
	}
})();
