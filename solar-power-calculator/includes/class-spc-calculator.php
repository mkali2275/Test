<?php
/**
 * Off-grid sizing engine.
 *
 * Mirrors the arithmetic in assets/js/solar-calculator.js, which runs the same
 * model in the browser so results update as the visitor types. Every constant
 * comes from SPC_Data, so the two implementations can only ever differ in the
 * formulas themselves — keep them in step when editing either file.
 *
 * @package Solar_Power_Calculator
 */

defined( 'ABSPATH' ) || exit;

class SPC_Calculator {

	/**
	 * Size a system from a load list and a set of system choices.
	 *
	 * @param array $items  Load list. Each row: watts, qty, hours, duty, surge, dc, name.
	 * @param array $config System choices (peak sun hours, battery type, voltage, ...).
	 * @return array Sizing results.
	 */
	public static function calculate( array $items, array $config ) {
		$const     = SPC_Data::constants();
		$batteries = SPC_Data::battery_types();

		$config = wp_parse_args(
			$config,
			array(
				'psh'           => 4.5,
				'autonomy'      => 1,
				'battery_type'  => 'lithium',
				'voltage'       => 'auto',
				'inverter_type' => 'pure_sine',
				'controller'    => 'mppt',
				'panel_watts'   => 550,
				'battery_unit'  => '12v200',
				'simultaneity'  => $const['simultaneity'],
			)
		);

		$battery = isset( $batteries[ $config['battery_type'] ] ) ? $batteries[ $config['battery_type'] ] : reset( $batteries );

		$inverter_eff   = isset( $const['inverter_efficiency'][ $config['inverter_type'] ] )
			? $const['inverter_efficiency'][ $config['inverter_type'] ]
			: $const['inverter_efficiency']['pure_sine'];
		$controller_eff = isset( $const['controller_efficiency'][ $config['controller'] ] )
			? $const['controller_efficiency'][ $config['controller'] ]
			: $const['controller_efficiency']['mppt'];

		$psh      = max( 0.5, (float) $config['psh'] );
		$autonomy = max( 0.5, (float) $config['autonomy'] );

		/* ------------------------------------------------- 1. Daily energy */

		$ac_wh          = 0.0; // Energy consumed by mains appliances.
		$dc_wh          = 0.0; // Energy consumed by appliances wired to the battery.
		$ac_connected_w = 0.0; // Nameplate watts of every AC appliance.
		$dc_connected_w = 0.0;
		$largest_surge  = 0.0; // Biggest single starting load, for inverter surge.
		$largest_ac_w   = 0.0; // Biggest single AC appliance, an inverter floor.
		$breakdown      = array();

		foreach ( $items as $item ) {
			$watts = max( 0.0, (float) $item['watts'] );
			$qty   = max( 0, (int) $item['qty'] );
			$hours = min( 24.0, max( 0.0, (float) $item['hours'] ) );
			$duty  = min( 1.0, max( 0.01, isset( $item['duty'] ) ? (float) $item['duty'] : 1.0 ) );
			$surge = max( 1.0, isset( $item['surge'] ) ? (float) $item['surge'] : 1.0 );
			$is_dc = ! empty( $item['dc'] );

			if ( $qty < 1 || $watts <= 0 ) {
				continue;
			}

			$connected = $watts * $qty;
			$energy    = $connected * $hours * $duty;

			if ( $is_dc ) {
				$dc_wh          += $energy;
				$dc_connected_w += $connected;
			} else {
				$ac_wh          += $energy;
				$ac_connected_w += $connected;
				$largest_ac_w    = max( $largest_ac_w, $watts );
				// Only one appliance realistically starts at a time; the surge
				// allowance is the worst single start on top of everything else.
				$largest_surge = max( $largest_surge, $watts * $surge - $watts );
			}

			$breakdown[] = array(
				'name'      => isset( $item['name'] ) ? (string) $item['name'] : 'Load',
				'qty'       => $qty,
				'watts'     => $watts,
				'hours'     => $hours,
				'wh'        => $energy,
				'connected' => $connected,
				'dc'        => $is_dc,
			);
		}

		$daily_wh = $ac_wh + $dc_wh;

		// Energy actually pulled out of the battery: AC loads pay the inverter
		// conversion penalty, DC loads do not.
		$battery_draw_wh = ( $inverter_eff > 0 ? $ac_wh / $inverter_eff : 0 ) + $dc_wh;

		/* -------------------------------------------------- 2. Solar array */

		// Everything the array must replace each day, grossed up for controller,
		// array and battery round-trip losses. Independent of system voltage,
		// so it can be worked out before the voltage is chosen.
		$system_efficiency = $controller_eff * $const['array_derate'] * $battery['efficiency'];
		$array_watts       = 0.0;
		if ( $battery_draw_wh > 0 && $system_efficiency > 0 ) {
			$array_watts = $battery_draw_wh / ( $psh * $system_efficiency );
		}

		$panel_watts     = max( 10, (float) $config['panel_watts'] );
		$panel_qty       = $array_watts > 0 ? (int) ceil( $array_watts / $panel_watts ) : 0;
		$array_installed = $panel_qty * $panel_watts;

		/* ---------------------------------------------- 3. System voltage */

		// Voltage is a current problem, not an energy one: it is picked so that
		// neither the inverter draw nor the array charge current needs cable
		// and fusing that stops being practical.
		if ( 'auto' === $config['voltage'] ) {
			if ( $ac_connected_w <= 1200 && $array_installed <= 800 && $battery_draw_wh <= 2000 ) {
				$system_voltage = 12;
			} elseif ( $ac_connected_w <= 3000 && $array_installed <= 2500 && $battery_draw_wh <= 6000 ) {
				$system_voltage = 24;
			} else {
				$system_voltage = 48;
			}
		} else {
			$system_voltage = (int) $config['voltage'];
		}

		/* ----------------------------------------------- 4. Battery bank */

		// Oversize for depth of discharge and round-trip losses so the usable
		// capacity — not the nameplate — covers the autonomy period.
		$bank_wh = 0.0;
		if ( $battery_draw_wh > 0 ) {
			$bank_wh = ( $battery_draw_wh * $autonomy ) / ( $battery['dod'] * $battery['efficiency'] );
		}
		$bank_ah    = $system_voltage > 0 ? $bank_wh / $system_voltage : 0;
		$usable_kwh = $bank_wh * $battery['dod'] / 1000;

		$unit = self::find_battery_unit( $config['battery_unit'], $const['battery_units'] );
		// Series count sets the bank voltage; parallel strings add capacity.
		$series   = max( 1, (int) ceil( $system_voltage / max( 1, $unit['volts'] ) ) );
		$strings  = $bank_ah > 0 ? max( 1, (int) ceil( $bank_ah / max( 1, $unit['ah'] ) ) ) : 0;
		$unit_qty = $series * $strings;

		/* ----------------------------------------------------- 5. Inverter */

		$simultaneity = min( 1.0, max( 0.1, (float) $config['simultaneity'] ) );
		// Not everything runs at once, but the inverter must still be able to
		// carry the single biggest appliance on its own.
		$running_w      = max( $ac_connected_w * $simultaneity, $largest_ac_w );
		$inverter_w     = $running_w * $const['safety_factor'];
		$inverter_rated = $running_w > 0 ? self::round_up_to( $inverter_w, $const['inverter_sizes'] ) : 0.0;
		$inverter_va    = $const['power_factor'] > 0 ? $inverter_w / $const['power_factor'] : 0;
		$surge_w        = $running_w + $largest_surge;

		/* -------------------------------------------- 6. Charge controller */

		$controller_a = 0.0;
		if ( $array_installed > 0 && $system_voltage > 0 ) {
			$controller_a = ( $array_installed / $system_voltage ) * $const['safety_factor'];
		}
		$controller_rated = $controller_a > 0 ? self::round_up_to( $controller_a, $const['controller_sizes'] ) : 0.0;

		/* ------------------------------------------------------- 7. Runtime */

		// How long a full bank carries the load with no sun at all.
		$backup_hours = 0.0;
		if ( $battery_draw_wh > 0 ) {
			$backup_hours = ( $bank_wh * $battery['dod'] ) / ( $battery_draw_wh / 24 );
		}

		/* -------------------------------------------- 8. Cost and impact */

		$settings   = SPC_Data::settings();
		$annual_kwh = $daily_wh * 365 / 1000;

		$cost_pv         = $array_installed * (float) $settings['cost_pv_watt'];
		$cost_battery    = ( $bank_wh / 1000 ) * (float) $battery['cost_kwh'];
		$cost_inverter   = ( $inverter_rated / 1000 ) * (float) $settings['cost_inverter_kw'];
		$cost_hardware   = $cost_pv + $cost_battery + $cost_inverter;
		$cost_install    = $cost_hardware * ( (float) $settings['cost_install_pct'] / 100 );

		$results = array(
			'daily_wh'          => $daily_wh,
			'daily_kwh'         => $daily_wh / 1000,
			'monthly_kwh'       => $daily_wh * 30 / 1000,
			'annual_kwh'        => $annual_kwh,
			'ac_wh'             => $ac_wh,
			'dc_wh'             => $dc_wh,
			'battery_draw_wh'   => $battery_draw_wh,
			'connected_w'       => $ac_connected_w + $dc_connected_w,
			'running_w'         => $running_w,
			'surge_w'           => $surge_w,

			'system_voltage'    => $system_voltage,
			'psh'               => $psh,
			'autonomy'          => $autonomy,

			'array_watts'       => $array_watts,
			'array_installed'   => $array_installed,
			'panel_watts'       => $panel_watts,
			'panel_qty'         => $panel_qty,
			'daily_harvest_wh'  => $array_installed * $psh * $controller_eff * $const['array_derate'],

			'bank_wh'           => $bank_wh,
			'bank_kwh'          => $bank_wh / 1000,
			'bank_ah'           => $bank_ah,
			'usable_kwh'        => $usable_kwh,
			'battery_label'     => $battery['label'],
			'battery_dod'       => $battery['dod'],
			'battery_unit'      => $unit['label'],
			'battery_series'    => $series,
			'battery_strings'   => $strings,
			'battery_qty'       => $unit_qty,
			'backup_hours'      => $backup_hours,

			'inverter_w'        => $inverter_w,
			'inverter_rated'    => $inverter_rated,
			'inverter_va'       => $inverter_va,

			'controller_a'      => $controller_a,
			'controller_rated'  => $controller_rated,
			'controller_type'   => strtoupper( $config['controller'] ),

			'cost_pv'           => $cost_pv,
			'cost_battery'      => $cost_battery,
			'cost_inverter'     => $cost_inverter,
			'cost_install'      => $cost_install,
			'cost_total'        => $cost_hardware + $cost_install,

			'co2_saved_kg'      => $annual_kwh * $const['co2_per_kwh'],
			'trees_equivalent'  => $const['co2_per_tree_year'] > 0 ? ( $annual_kwh * $const['co2_per_kwh'] ) / $const['co2_per_tree_year'] : 0,
			'generator_litres'  => $annual_kwh * $const['generator_l_per_kwh'],

			'breakdown'         => $breakdown,
			'warnings'          => array(),
		);

		$results['warnings'] = self::warnings( $results, $config );

		return $results;
	}

	/**
	 * Practical cautions worth showing next to the numbers.
	 *
	 * @param array $r      Results.
	 * @param array $config System choices.
	 * @return array
	 */
	private static function warnings( array $r, array $config ) {
		$warnings = array();

		if ( $r['daily_wh'] <= 0 ) {
			return $warnings;
		}

		if ( 12 === $r['system_voltage'] && $r['running_w'] > 1200 ) {
			$warnings[] = 'At 12V this load draws over 100A. Move to 24V or 48V to keep cable sizes and losses sensible.';
		}

		if ( $r['controller_rated'] > 100 ) {
			$warnings[] = 'The array needs more charge current than a single controller usually handles. Split it across two or more controllers.';
		}

		if ( $r['surge_w'] > $r['inverter_rated'] * 2 ) {
			$warnings[] = 'Startup surge is high. Check that your inverter can deliver ' . number_format_i18n( $r['surge_w'] ) . 'W for a few seconds, or stagger the motor loads.';
		}

		if ( 'pwm' === $config['controller'] && $r['array_installed'] > 800 ) {
			$warnings[] = 'PWM controllers waste 15-20% of the harvest on arrays this size. MPPT pays for itself here.';
		}

		if ( $r['autonomy'] < 2 ) {
			$warnings[] = 'Sized for ' . $r['autonomy'] . ' day of autonomy. Add a day if you get long overcast spells and have no generator or grid backup.';
		}

		if ( $r['bank_kwh'] > 0 && $r['array_installed'] > 0 ) {
			$c_rate = $r['array_installed'] / $r['bank_wh'];
			if ( $c_rate < 0.1 ) {
				$warnings[] = 'The array is small relative to the bank, so a deeply discharged bank will take more than a day to recover.';
			}
		}

		return $warnings;
	}

	/**
	 * Round a value up to the next off-the-shelf size.
	 *
	 * @param float $value Value to round.
	 * @param array $sizes Ascending list of available sizes.
	 * @return float
	 */
	private static function round_up_to( $value, array $sizes ) {
		foreach ( $sizes as $size ) {
			if ( $value <= $size ) {
				return (float) $size;
			}
		}

		return (float) ceil( $value );
	}

	/**
	 * Look up a battery unit by id.
	 *
	 * @param string $id    Unit id.
	 * @param array  $units Available units.
	 * @return array
	 */
	private static function find_battery_unit( $id, array $units ) {
		foreach ( $units as $unit ) {
			if ( $unit['id'] === $id ) {
				return $unit;
			}
		}

		return $units[2]; // 12V 200Ah.
	}
}
