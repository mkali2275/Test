=== Solar Power Calculator ===
Contributors: sustainaportal
Tags: solar, calculator, off-grid, battery, energy
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Off-grid solar sizing calculator. Visitors pick their appliances and get the panels, batteries, inverter and charge controller to run them.

== Description ==

Add a solar sizing calculator to any page with the `[solar_calculator]` shortcode.

Visitors choose a setting — home, office, or travel/RV — pick the appliances they
need to run, and get back a complete system: solar array wattage and panel count,
battery bank size and wiring, inverter rating with surge allowance, and charge
controller current. Results update as they type.

**What it works out**

* Daily energy use, accounting for duty cycles on thermostat-controlled loads
* The mains battery charger needed to refill the bank inside a limited grid window
* Solar array size for their region's peak sun hours, after controller and array losses
* Battery bank sized on usable capacity, not nameplate, for the chosen chemistry and days of backup
* System voltage (12V / 24V / 48V) chosen from current draw
* Inverter continuous rating and startup surge
* MPPT or PWM charge controller current
* An indicative budget, with prices you set
* Annual CO2 avoided and generator fuel saved

**Also included**

* Intermittent mains support: size for the outage, not a whole day, and check that a short grid window can actually refill the bank
* Over 60 appliances across home, office and travel, plus a custom appliance form
* 14 regional presets for peak sun hours, or a manual figure
* Lithium, AGM, tubular and flooded lead-acid battery models
* DC appliances handled separately, so van and boat builds are sized correctly
* Print / save as PDF
* Optional email capture, with a copy of every enquiry sent to you
* Works in the block editor, classic editor, Elementor and widgets
* No dependencies, no build step, assets only load on pages that use the shortcode

**Shortcode options**

`[solar_calculator profile="travel" title="Size your van's solar" costs="no"]`

* `profile` — `home`, `office` or `travel`. Which tab opens first.
* `title` / `subtitle` — set your own, or pass an empty string to hide.
* `costs` — `yes` or `no`, to override the budget card on a single page.
* `theme` — `auto` (default), `light` or `dark`. Auto matches the page's background.

**For developers**

Appliances, regions, battery chemistries and engineering constants are filterable
via `spc_appliances`, `spc_regions`, `spc_battery_types`, `spc_profiles` and
`spc_constants`. The `spc_results_emailed` action fires with each enquiry for CRM
or mailing list integration.

== Installation ==

1. Upload the plugin zip through Plugins → Add New → Upload Plugin, then activate it.
2. Add `[solar_calculator]` to any page or post.
3. Adjust defaults, currency and prices under Settings → Solar Calculator.

== Frequently Asked Questions ==

= Do I need any other plugin? =

No. It has no dependencies. The one exception is the optional email feature, which
needs a working mail setup — most hosts require an SMTP plugin for that.

= Can I put it on more than one page? =

Yes, and more than one on the same page. Each instance keeps its own state.

= Will it match my theme? =

It inherits your fonts and adapts to light and dark themes. Colours are set with
CSS custom properties on `.spc`, so you can override the palette from your theme
without fighting selectors.

= Are the results accurate enough to buy from? =

They will get you to the right size of system and a realistic budget. Cable sizing,
protection, earthing and mounting still need a qualified installer, which the
disclaimer under the results states.

= Is any visitor data stored? =

No. Nothing is written to the database unless you enable the optional email
feature, and even then results are emailed rather than stored.

== Screenshots ==

1. The calculator with a home load list and its sizing results.

== Changelog ==

= 1.1.0 =
* Added support for intermittent mains power, the normal situation across much of the Middle East, South Asia and Africa. Say how many hours of grid or generator you get, whether it also charges the batteries, and how much of your energy you want from solar. The battery is then sized for the outage rather than a whole day, and the array only covers the solar share.
* New "Charging from mains" result card: the charger current you need, how long a full recharge takes, the most the chosen chemistry will safely accept, and the daily split between solar and mains.
* Warns when the mains window is too short to refill the bank at a safe charge rate — the trap that catches most intermittent-supply systems.
* Battery backup is now chosen in hours rather than days, so a 16-hour outage can be sized directly.
* Environmental figures now count solar generation rather than total consumption, so they stay honest when part of the load comes from the grid.

= 1.0.1 =
* Fixed unreadable form text on themes that do not follow the device's dark mode setting. The palette now follows the page's own background colour instead of the visitor's device preference, and the calculator's own text and background colours are stated together so a theme cannot set one without the other.
* Added a `theme` shortcode attribute (`auto`, `light`, `dark`) to override the detected palette.
* Form controls now use a minimum 16px font on mobile so iOS stops zooming the page when a field is focused.
* Draw an explicit dropdown arrow, for themes that remove the native one.

= 1.0.0 =
* First release.
