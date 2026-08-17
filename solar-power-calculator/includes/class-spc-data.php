<?php
/**
 * Appliance library and engineering defaults.
 *
 * This is the single source of truth for every constant used by the
 * calculator. The PHP calculator (class-spc-calculator.php) and the browser
 * calculator (assets/js/solar-calculator.js) both read from here, so changing
 * a derate factor or adding an appliance only ever happens in this file.
 *
 * @package Solar_Power_Calculator
 */

defined( 'ABSPATH' ) || exit;

class SPC_Data {

	/**
	 * Appliance library.
	 *
	 * watts  - running power draw in watts
	 * hours  - typical hours of use per day
	 * duty   - fraction of those hours the compressor/element is actually on
	 *          (thermostat-controlled loads only; 1 = runs continuously)
	 * surge  - startup current multiplier, used for inverter surge sizing
	 * dc     - true when the appliance runs straight off the battery bank and
	 *          therefore does not pay the inverter efficiency penalty
	 *
	 * @return array
	 */
	public static function appliances() {
		$appliances = array(

			/* ---------------------------------------------------------- Home */
			array( 'id' => 'led_bulb',       'name' => 'LED bulb',                    'profiles' => array( 'home', 'office' ), 'group' => 'Lighting',   'watts' => 10,   'hours' => 5,    'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'security_light', 'name' => 'Security flood light',        'profiles' => array( 'home', 'office' ), 'group' => 'Lighting',   'watts' => 30,   'hours' => 12,   'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'panel_light',    'name' => 'LED panel / tube light',      'profiles' => array( 'home', 'office' ), 'group' => 'Lighting',   'watts' => 40,   'hours' => 8,    'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'ceiling_fan',    'name' => 'Ceiling fan',                 'profiles' => array( 'home', 'office' ), 'group' => 'Cooling',    'watts' => 75,   'hours' => 8,    'duty' => 1,    'surge' => 1.5 ),
			array( 'id' => 'standing_fan',   'name' => 'Standing / table fan',        'profiles' => array( 'home', 'office' ), 'group' => 'Cooling',    'watts' => 60,   'hours' => 6,    'duty' => 1,    'surge' => 1.5 ),
			array( 'id' => 'ac_1hp_inv',     'name' => 'Air conditioner 1HP (inverter)', 'profiles' => array( 'home', 'office' ), 'group' => 'Cooling', 'watts' => 750,  'hours' => 6,    'duty' => 0.7,  'surge' => 1.5 ),
			array( 'id' => 'ac_15hp',        'name' => 'Air conditioner 1.5HP (standard)', 'profiles' => array( 'home', 'office' ), 'group' => 'Cooling', 'watts' => 1500, 'hours' => 6, 'duty' => 0.8, 'surge' => 3 ),
			array( 'id' => 'fridge_small',   'name' => 'Refrigerator (150-200L)',     'profiles' => array( 'home', 'office' ), 'group' => 'Kitchen',    'watts' => 100,  'hours' => 24,   'duty' => 0.35, 'surge' => 3 ),
			array( 'id' => 'fridge_large',   'name' => 'Refrigerator (350L+ / double door)', 'profiles' => array( 'home' ), 'group' => 'Kitchen',       'watts' => 200,  'hours' => 24,   'duty' => 0.4,  'surge' => 3 ),
			array( 'id' => 'freezer',        'name' => 'Chest freezer',               'profiles' => array( 'home' ),          'group' => 'Kitchen',    'watts' => 150,  'hours' => 24,   'duty' => 0.4,  'surge' => 3 ),
			array( 'id' => 'microwave',      'name' => 'Microwave oven',              'profiles' => array( 'home', 'office' ), 'group' => 'Kitchen',    'watts' => 1000, 'hours' => 0.3,  'duty' => 1,    'surge' => 1.5 ),
			array( 'id' => 'kettle',         'name' => 'Electric kettle',             'profiles' => array( 'home', 'office' ), 'group' => 'Kitchen',    'watts' => 1500, 'hours' => 0.3,  'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'blender',        'name' => 'Blender',                     'profiles' => array( 'home' ),          'group' => 'Kitchen',    'watts' => 400,  'hours' => 0.2,  'duty' => 1,    'surge' => 2 ),
			array( 'id' => 'rice_cooker',    'name' => 'Rice cooker',                 'profiles' => array( 'home' ),          'group' => 'Kitchen',    'watts' => 700,  'hours' => 0.5,  'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'toaster',        'name' => 'Toaster',                     'profiles' => array( 'home' ),          'group' => 'Kitchen',    'watts' => 800,  'hours' => 0.2,  'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'tv_32',          'name' => 'LED TV 32"',                  'profiles' => array( 'home', 'office' ), 'group' => 'Electronics','watts' => 50,   'hours' => 5,    'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'tv_55',          'name' => 'LED TV 55"',                  'profiles' => array( 'home' ),          'group' => 'Electronics','watts' => 120,  'hours' => 5,    'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'decoder',        'name' => 'Decoder / set-top box',       'profiles' => array( 'home' ),          'group' => 'Electronics','watts' => 25,   'hours' => 5,    'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'router',         'name' => 'Wi-Fi router / modem',        'profiles' => array( 'home', 'office', 'travel' ), 'group' => 'Electronics', 'watts' => 12, 'hours' => 24, 'duty' => 1, 'surge' => 1 ),
			array( 'id' => 'laptop',         'name' => 'Laptop',                      'profiles' => array( 'home', 'office', 'travel' ), 'group' => 'Electronics', 'watts' => 65, 'hours' => 6,  'duty' => 1, 'surge' => 1 ),
			array( 'id' => 'phone_charger',  'name' => 'Phone charger',               'profiles' => array( 'home', 'office', 'travel' ), 'group' => 'Electronics', 'watts' => 8,  'hours' => 3,  'duty' => 1, 'surge' => 1 ),
			array( 'id' => 'cctv',           'name' => 'CCTV camera',                 'profiles' => array( 'home', 'office' ), 'group' => 'Electronics','watts' => 10,   'hours' => 24,   'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'washing_machine','name' => 'Washing machine',             'profiles' => array( 'home' ),          'group' => 'Utility',    'watts' => 500,  'hours' => 1,    'duty' => 1,    'surge' => 3 ),
			array( 'id' => 'iron',           'name' => 'Clothes iron',                'profiles' => array( 'home' ),          'group' => 'Utility',    'watts' => 1200, 'hours' => 0.5,  'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'hair_dryer',     'name' => 'Hair dryer',                  'profiles' => array( 'home' ),          'group' => 'Utility',    'watts' => 1200, 'hours' => 0.2,  'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'water_pump',     'name' => 'Water pump (0.5HP)',          'profiles' => array( 'home' ),          'group' => 'Utility',    'watts' => 375,  'hours' => 1,    'duty' => 1,    'surge' => 3 ),
			array( 'id' => 'space_heater',   'name' => 'Electric space heater',       'profiles' => array( 'home', 'office' ), 'group' => 'Utility',    'watts' => 1500, 'hours' => 2,    'duty' => 0.6,  'surge' => 1 ),

			/* -------------------------------------------------------- Office */
			array( 'id' => 'desktop_pc',     'name' => 'Desktop PC + monitor',        'profiles' => array( 'office', 'home' ), 'group' => 'Workstation','watts' => 200,  'hours' => 8,    'duty' => 1,    'surge' => 1.5 ),
			array( 'id' => 'laptop_dock',    'name' => 'Laptop + external monitor',   'profiles' => array( 'office' ),        'group' => 'Workstation','watts' => 120,  'hours' => 8,    'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'monitor',        'name' => 'Monitor 24"',                 'profiles' => array( 'office' ),        'group' => 'Workstation','watts' => 30,   'hours' => 8,    'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'laser_printer',  'name' => 'Laser printer',               'profiles' => array( 'office' ),        'group' => 'Office kit', 'watts' => 500,  'hours' => 0.5,  'duty' => 1,    'surge' => 2 ),
			array( 'id' => 'inkjet_printer', 'name' => 'Inkjet printer',              'profiles' => array( 'office', 'home' ), 'group' => 'Office kit', 'watts' => 30,   'hours' => 0.5,  'duty' => 1,    'surge' => 1.5 ),
			array( 'id' => 'photocopier',    'name' => 'Photocopier',                 'profiles' => array( 'office' ),        'group' => 'Office kit', 'watts' => 1200, 'hours' => 1,    'duty' => 0.5,  'surge' => 2 ),
			array( 'id' => 'scanner',        'name' => 'Scanner',                     'profiles' => array( 'office' ),        'group' => 'Office kit', 'watts' => 20,   'hours' => 1,    'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'projector',      'name' => 'Projector',                   'profiles' => array( 'office' ),        'group' => 'Office kit', 'watts' => 300,  'hours' => 3,    'duty' => 1,    'surge' => 1.5 ),
			array( 'id' => 'net_switch',     'name' => 'Network switch',              'profiles' => array( 'office' ),        'group' => 'IT',         'watts' => 20,   'hours' => 24,   'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'server',         'name' => 'Small server / NAS',          'profiles' => array( 'office' ),        'group' => 'IT',         'watts' => 350,  'hours' => 24,   'duty' => 1,    'surge' => 1.5 ),
			array( 'id' => 'voip_phone',     'name' => 'VoIP desk phone',             'profiles' => array( 'office' ),        'group' => 'IT',         'watts' => 6,    'hours' => 8,    'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'water_dispenser','name' => 'Water dispenser (hot & cold)','profiles' => array( 'office', 'home' ), 'group' => 'Office kit', 'watts' => 550,  'hours' => 8,    'duty' => 0.4,  'surge' => 2 ),
			array( 'id' => 'coffee_machine', 'name' => 'Coffee machine',              'profiles' => array( 'office', 'home' ), 'group' => 'Office kit', 'watts' => 900,  'hours' => 0.3,  'duty' => 1,    'surge' => 1 ),

			/* ------------------------------------------- Travel / RV / camping */
			array( 'id' => 'rv_led',         'name' => '12V LED strip lighting',      'profiles' => array( 'travel' ),        'group' => 'Lighting',   'watts' => 5,    'hours' => 5,    'duty' => 1,    'surge' => 1,   'dc' => true ),
			array( 'id' => 'rv_fridge',      'name' => '12V compressor fridge',       'profiles' => array( 'travel' ),        'group' => 'Cooling',    'watts' => 45,   'hours' => 24,   'duty' => 0.4,  'surge' => 3,   'dc' => true ),
			array( 'id' => 'cooler_box',     'name' => 'Portable cooler box',         'profiles' => array( 'travel' ),        'group' => 'Cooling',    'watts' => 60,   'hours' => 24,   'duty' => 0.5,  'surge' => 2,   'dc' => true ),
			array( 'id' => 'vent_fan',       'name' => 'Roof vent fan',               'profiles' => array( 'travel' ),        'group' => 'Cooling',    'watts' => 30,   'hours' => 6,    'duty' => 1,    'surge' => 1.5, 'dc' => true ),
			array( 'id' => 'rv_pump',        'name' => '12V water pump',              'profiles' => array( 'travel' ),        'group' => 'Utility',    'watts' => 50,   'hours' => 0.5,  'duty' => 1,    'surge' => 2,   'dc' => true ),
			array( 'id' => 'diesel_heater',  'name' => 'Diesel air heater',           'profiles' => array( 'travel' ),        'group' => 'Utility',    'watts' => 30,   'hours' => 6,    'duty' => 0.5,  'surge' => 4,   'dc' => true ),
			array( 'id' => 'air_compressor', 'name' => '12V air compressor',          'profiles' => array( 'travel' ),        'group' => 'Utility',    'watts' => 180,  'hours' => 0.2,  'duty' => 1,    'surge' => 2,   'dc' => true ),
			array( 'id' => 'starlink',       'name' => 'Satellite internet (Starlink)','profiles' => array( 'travel', 'office' ), 'group' => 'Electronics', 'watts' => 60, 'hours' => 8, 'duty' => 1, 'surge' => 1.5 ),
			array( 'id' => 'cpap',           'name' => 'CPAP machine (no humidifier)','profiles' => array( 'travel', 'home' ), 'group' => 'Medical',    'watts' => 60,   'hours' => 8,    'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'camera_charger', 'name' => 'Camera battery charger',      'profiles' => array( 'travel' ),        'group' => 'Electronics','watts' => 30,   'hours' => 2,    'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'drone_charger',  'name' => 'Drone battery charger',       'profiles' => array( 'travel' ),        'group' => 'Electronics','watts' => 80,   'hours' => 1.5,  'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'portable_tv',    'name' => 'Portable TV',                 'profiles' => array( 'travel' ),        'group' => 'Electronics','watts' => 40,   'hours' => 3,    'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'induction_hob',  'name' => 'Portable induction cooktop',  'profiles' => array( 'travel', 'home' ), 'group' => 'Kitchen',    'watts' => 1200, 'hours' => 0.5,  'duty' => 1,    'surge' => 1 ),
			array( 'id' => 'electric_blanket','name'=> 'Electric blanket',            'profiles' => array( 'travel', 'home' ), 'group' => 'Utility',    'watts' => 60,   'hours' => 6,    'duty' => 0.5,  'surge' => 1 ),
		);

		foreach ( $appliances as &$appliance ) {
			$appliance = wp_parse_args(
				$appliance,
				array( 'duty' => 1, 'surge' => 1, 'dc' => false, 'group' => 'Other' )
			);
		}
		unset( $appliance );

		/**
		 * Filter the appliance library.
		 *
		 * @param array $appliances Appliance definitions.
		 */
		return apply_filters( 'spc_appliances', $appliances );
	}

	/**
	 * Usage profiles shown as the first choice in the calculator.
	 *
	 * @return array
	 */
	public static function profiles() {
		return apply_filters(
			'spc_profiles',
			array(
				'home' => array(
					'label'        => 'Home',
					'blurb'        => 'Whole house or a few essential circuits',
					'icon'         => 'home',
					'backup_hours' => 24,
				),
				'office' => array(
					'label'        => 'Office',
					'blurb'        => 'Workstations, IT equipment and cooling',
					'icon'         => 'office',
					'backup_hours' => 12,
				),
				'travel' => array(
					'label'        => 'Travel / RV',
					'blurb'        => 'Van, caravan, boat or camping setup',
					'icon'         => 'van',
					'backup_hours' => 48,
				),
			)
		);
	}

	/**
	 * Average daily peak sun hours by region.
	 *
	 * Peak sun hours = daily solar irradiation (kWh/m2/day) on a fixed,
	 * optimally tilted array. Values are conservative annual averages; the
	 * calculator lets visitors override them with a local figure.
	 *
	 * @return array
	 */
	public static function regions() {
		return apply_filters(
			'spc_regions',
			array(
				array( 'id' => 'wafrica_north', 'name' => 'West Africa - northern / Sahel',   'psh' => 5.8 ),
				array( 'id' => 'wafrica_south', 'name' => 'West Africa - coastal / humid',    'psh' => 4.5 ),
				array( 'id' => 'eafrica',       'name' => 'East Africa / Horn of Africa',     'psh' => 5.5 ),
				array( 'id' => 'safrica',       'name' => 'Southern Africa',                  'psh' => 5.5 ),
				array( 'id' => 'nafrica_me',    'name' => 'North Africa / Middle East',       'psh' => 5.7 ),
				array( 'id' => 'south_asia',    'name' => 'South Asia (India, Pakistan)',     'psh' => 5.0 ),
				array( 'id' => 'sea',           'name' => 'South East Asia',                  'psh' => 4.2 ),
				array( 'id' => 'aus',           'name' => 'Australia',                        'psh' => 5.0 ),
				array( 'id' => 'us_southwest',  'name' => 'US Southwest',                     'psh' => 5.7 ),
				array( 'id' => 'us_central',    'name' => 'US Central / Southeast',           'psh' => 4.5 ),
				array( 'id' => 'us_north',      'name' => 'US North / Canada',                'psh' => 3.5 ),
				array( 'id' => 'latam',         'name' => 'Latin America',                    'psh' => 4.8 ),
				array( 'id' => 'south_europe',  'name' => 'Southern Europe',                  'psh' => 4.2 ),
				array( 'id' => 'north_europe',  'name' => 'Northern Europe / UK',             'psh' => 2.6 ),
			)
		);
	}

	/**
	 * Battery chemistries.
	 *
	 * dod        - usable depth of discharge before cycle life suffers
	 * efficiency - round-trip charge/discharge efficiency
	 *
	 * @return array
	 */
	public static function battery_types() {
		return apply_filters(
			'spc_battery_types',
			array(
				'lithium'   => array( 'label' => 'Lithium (LiFePO4)',   'dod' => 0.8,  'efficiency' => 0.95, 'cycles' => 4000, 'cost_kwh' => 400, 'max_charge_c' => 0.5 ),
				'agm'       => array( 'label' => 'AGM / Gel sealed',    'dod' => 0.5,  'efficiency' => 0.85, 'cycles' => 700,  'cost_kwh' => 250, 'max_charge_c' => 0.25 ),
				'tubular'   => array( 'label' => 'Tubular deep cycle',  'dod' => 0.5,  'efficiency' => 0.85, 'cycles' => 1500, 'cost_kwh' => 220, 'max_charge_c' => 0.2 ),
				'flooded'   => array( 'label' => 'Flooded lead-acid',   'dod' => 0.5,  'efficiency' => 0.8,  'cycles' => 500,  'cost_kwh' => 160, 'max_charge_c' => 0.15 ),
			)
		);
	}

	/**
	 * Engineering constants and sizing tables.
	 *
	 * @return array
	 */
	public static function constants() {
		return apply_filters(
			'spc_constants',
			array(
				// Inverter DC-to-AC conversion efficiency by waveform.
				'inverter_efficiency' => array(
					'pure_sine'     => 0.9,
					'modified_sine' => 0.85,
				),
				// Charge controller harvest efficiency.
				'controller_efficiency' => array(
					'mppt' => 0.95,
					'pwm'  => 0.75,
				),
				// Array losses: soiling, heat, wiring, mismatch, tolerance.
				'array_derate'       => 0.85,
				// Headroom applied to continuous inverter and controller ratings.
				'safety_factor'      => 1.25,
				// Inverter apparent-power conversion (W to VA).
				'power_factor'       => 0.8,
				// Fraction of connected AC load assumed to run at the same moment.
				'simultaneity'       => 0.7,
				// Mains battery charger efficiency, AC in to DC stored.
				'charger_efficiency' => 0.9,
				// Hours of mains offered in the "power available" control.
				'grid_hour_options'  => array( 0, 2, 4, 6, 8, 10, 12, 16, 20, 24 ),
				// Hours the battery is asked to carry the load on its own. The
				// steps are fine enough to express "24 minus the mains window"
				// for every option offered in grid_hour_options.
				'backup_hour_options' => array(
					4  => '4 hours',
					6  => '6 hours',
					8  => '8 hours',
					10 => '10 hours',
					12 => '12 hours',
					14 => '14 hours',
					16 => '16 hours',
					18 => '18 hours',
					20 => '20 hours',
					24 => '1 day',
					48 => '2 days',
					72 => '3 days',
				),
				// Share of the daily load the solar array is asked to supply.
				'solar_share_options' => array(
					100 => 'Everything (no mains help)',
					75  => 'Most of it (about 75%)',
					50  => 'Half of it',
					25  => 'A quarter (cut the bill)',
				),
				// Grid emission factor, kg CO2 per kWh displaced.
				'co2_per_kwh'        => 0.45,
				// A mature tree absorbs roughly this much CO2 per year, in kg.
				'co2_per_tree_year'  => 21,
				// Petrol generator: litres burned per kWh produced.
				'generator_l_per_kwh' => 0.4,
				// Off-the-shelf sizes used to round recommendations up.
				'inverter_sizes'     => array( 300, 600, 1000, 1500, 2000, 2500, 3000, 4000, 5000, 6000, 8000, 10000, 12000, 15000, 20000 ),
				'controller_sizes'   => array( 10, 20, 30, 40, 50, 60, 80, 100, 120, 150, 200 ),
				'panel_sizes'        => array( 100, 150, 200, 250, 300, 330, 400, 450, 550, 600 ),
				'battery_units'      => array(
					array( 'id' => '12v100',  'label' => '12V 100Ah',  'volts' => 12, 'ah' => 100 ),
					array( 'id' => '12v150',  'label' => '12V 150Ah',  'volts' => 12, 'ah' => 150 ),
					array( 'id' => '12v200',  'label' => '12V 200Ah',  'volts' => 12, 'ah' => 200 ),
					array( 'id' => '12v220',  'label' => '12V 220Ah',  'volts' => 12, 'ah' => 220 ),
					array( 'id' => '24v100',  'label' => '24V 100Ah',  'volts' => 24, 'ah' => 100 ),
					array( 'id' => '48v100',  'label' => '48V 100Ah',  'volts' => 48, 'ah' => 100 ),
					array( 'id' => '48v200',  'label' => '48V 200Ah',  'volts' => 48, 'ah' => 200 ),
				),
			)
		);
	}

	/**
	 * Plugin settings merged over their defaults.
	 *
	 * @return array
	 */
	public static function settings() {
		$defaults = array(
			'default_region'   => 'wafrica_south',
			'default_battery'  => 'lithium',
			'default_panel'    => 550,
			'show_costs'       => 1,
			'currency'         => '$',
			'cost_pv_watt'     => 0.9,
			'cost_inverter_kw' => 300,
			'cost_install_pct' => 25,
			'enable_email'     => 0,
			'email_recipient'  => get_option( 'admin_email' ),
			'cta_text'         => '',
			'cta_url'          => '',
		);

		$settings = get_option( 'spc_settings', array() );

		return wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );
	}
}
