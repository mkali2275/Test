/**
 * Regression test for theme contrast.
 *
 * The original bug: the calculator switched to its dark palette from the
 * device's dark-mode preference, but most WordPress themes ignore that
 * preference. On a light theme viewed on a dark-mode phone the input boxes
 * went dark while the theme kept forcing dark text on them, so the text became
 * invisible.
 *
 * This drives the preview inside simulated themes - light and dark, with and
 * without the device preferring dark - and asserts every form control keeps
 * readable contrast against its own background.
 *
 * Usage: php tools/build-preview.php && node tools/test-theme.js
 */

'use strict';

const path = require('path');
const { chromium } = require('playwright');

const PREVIEW = 'file://' + path.join(__dirname, '..', 'preview', 'index.html');

// WCAG AA for normal text. Form controls the visitor types into must clear it.
const MIN_CONTRAST = 4.5;

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

/**
 * Themes that restyle form controls, the way a real WordPress theme does.
 * Solio is a light theme, so it sets dark text on inputs unconditionally.
 */
const THEMES = {
	'solio-like light theme': {
		pageBackground: '#ffffff',
		css: `
			body { background: #ffffff; color: #21252b; }
			input, select, textarea { color: #21252b; background: #f4f6f8; border: 1px solid #dfe3e8; }
		`
	},
	'dark theme': {
		pageBackground: '#0e1418',
		css: `
			body { background: #0e1418; color: #e6e6e6; }
			input, select, textarea { color: #e6e6e6; background: #1b2229; border: 1px solid #2a323a; }
		`
	}
};

function contrastScript() {
	// Runs in the page: contrast of every form control against what is behind it.
	const parse = (value) => {
		const m = /^rgba?\(([^)]+)\)$/.exec((value || '').trim());
		if (!m) return null;
		const p = m[1].split(/[,\s/]+/).filter(Boolean).map(parseFloat);
		return p.length < 3 ? null : { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 };
	};
	const lum = (c) => {
		const ch = [c.r, c.g, c.b].map((v) => {
			v /= 255;
			return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
		});
		return 0.2126 * ch[0] + 0.7152 * ch[1] + 0.0722 * ch[2];
	};
	// Effective background: walk up until something opaque paints.
	const backgroundOf = (el) => {
		let node = el;
		while (node) {
			const c = parse(getComputedStyle(node).backgroundColor);
			if (c && c.a > 0.1) return c;
			node = node.parentElement;
		}
		return { r: 255, g: 255, b: 255, a: 1 };
	};

	const results = [];
	document.querySelectorAll('.spc input, .spc select, .spc textarea').forEach((el) => {
		if (el.type === 'checkbox' || el.type === 'range' || el.offsetParent === null) return;
		const style = getComputedStyle(el);
		const fg = parse(style.webkitTextFillColor && style.webkitTextFillColor !== 'currentcolor'
			? style.webkitTextFillColor
			: style.color);
		const bg = backgroundOf(el);
		if (!fg) return;
		const a = lum(fg);
		const b = lum(bg);
		const ratio = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
		results.push({
			label: (el.className || el.tagName).toString().split(' ')[0] || el.tagName,
			ratio: Math.round(ratio * 100) / 100,
			fg: style.color,
			bg: `rgb(${bg.r}, ${bg.g}, ${bg.b})`
		});
	});
	return {
		detected: document.querySelector('.spc').getAttribute('data-theme'),
		controls: results
	};
}

(async () => {
	const browser = await chromium.launch(
		process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}
	);

	for (const [themeName, theme] of Object.entries(THEMES)) {
		for (const colorScheme of ['light', 'dark']) {
			const page = await browser.newPage({ viewport: { width: 390, height: 844 }, colorScheme });

			// Apply the simulated theme before the calculator boots, so its
			// detection sees the page a real visitor would see.
			await page.addInitScript((css) => {
				document.addEventListener('DOMContentLoaded', () => {
					const style = document.createElement('style');
					style.textContent = css;
					document.head.appendChild(style);
				});
			}, theme.css);

			await page.goto(PREVIEW);
			await page.addStyleTag({ content: theme.css });
			await page.waitForSelector('.spc-body:not([hidden])');

			// Open every collapsed section so hidden controls get measured too.
			await page.evaluate(() => {
				document.querySelectorAll('.spc details').forEach((d) => { d.open = true; });
			});
			await page.locator('.spc-chip', { hasText: 'LED bulb' }).first().click();
			await page.waitForTimeout(150);

			const { detected, controls } = await page.evaluate(contrastScript);
			const label = `${themeName}, device prefers ${colorScheme}`;

			const expected = themeName.includes('dark') ? 'dark' : 'light';
			check(`${label}: palette follows the page (${detected})`, detected === expected,
				`expected ${expected}`);

			const worst = controls.reduce(
				(acc, c) => (c.ratio < acc.ratio ? c : acc),
				{ ratio: Infinity, label: 'none' }
			);
			check(`${label}: all ${controls.length} form controls readable`,
				controls.length > 0 && worst.ratio >= MIN_CONTRAST,
				`worst is ${worst.label} at ${worst.ratio}:1 (${worst.fg} on ${worst.bg})`);

			if (colorScheme === 'dark') {
				await page.screenshot({
					path: path.join(__dirname, '..', 'preview',
						`theme-${expected}-devicedark.png`),
					fullPage: false
				});
			}

			await page.close();
		}
	}

	await browser.close();
	console.log(`\n${passed} passed, ${failed} failed.`);
	process.exit(failed ? 1 : 0);
})();
