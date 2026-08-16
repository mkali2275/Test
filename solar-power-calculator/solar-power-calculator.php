<?php
/**
 * Plugin Name:       Solar Power Calculator
 * Plugin URI:        https://sustainaportal.com/
 * Description:       Off-grid solar sizing calculator. Visitors pick the appliances they need to run at home, in the office or while travelling, and get the panel wattage, battery bank, inverter and charge controller sizes to run them. Add it to any page with [solar_calculator].
 * Version:           1.0.1
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            SustainaPortal
 * Author URI:        https://sustainaportal.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       solar-power-calculator
 *
 * @package Solar_Power_Calculator
 */

defined( 'ABSPATH' ) || exit;

define( 'SPC_VERSION', '1.0.1' );
define( 'SPC_FILE', __FILE__ );
define( 'SPC_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPC_URL', plugin_dir_url( __FILE__ ) );
define( 'SPC_BASENAME', plugin_basename( __FILE__ ) );

require_once SPC_PATH . 'includes/class-spc-data.php';
require_once SPC_PATH . 'includes/class-spc-calculator.php';
require_once SPC_PATH . 'includes/class-spc-shortcode.php';
require_once SPC_PATH . 'includes/class-spc-email.php';
require_once SPC_PATH . 'includes/class-spc-settings.php';

class SPC_Plugin {

	/**
	 * Whether the front-end assets have already been queued for this request.
	 *
	 * @var bool
	 */
	private static $assets_queued = false;

	/**
	 * Boot the plugin.
	 */
	public static function init() {
		SPC_Shortcode::init();
		SPC_Email::init();

		if ( is_admin() ) {
			SPC_Settings::init();
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register (but do not load) the front-end assets.
	 *
	 * They are only enqueued when a page actually contains the shortcode, so
	 * pages without the calculator stay as light as they were.
	 */
	public static function register_assets() {
		wp_register_style(
			'solar-power-calculator',
			SPC_URL . 'assets/css/solar-calculator.css',
			array(),
			SPC_VERSION
		);

		wp_register_script(
			'solar-power-calculator',
			SPC_URL . 'assets/js/solar-calculator.js',
			array(),
			SPC_VERSION,
			true
		);
	}

	/**
	 * Enqueue the assets and hand the appliance library to the script.
	 *
	 * Safe to call once per shortcode instance; the payload is only attached
	 * the first time.
	 *
	 * @param array $data Data to expose as window.SPC_DATA.
	 */
	public static function enqueue_assets( array $data ) {
		// register_assets() runs on wp_enqueue_scripts. A shortcode inside a
		// widget or a theme template can fire earlier, so make sure the
		// handles exist before we ask for them.
		if ( ! wp_style_is( 'solar-power-calculator', 'registered' ) ) {
			self::register_assets();
		}

		wp_enqueue_style( 'solar-power-calculator' );
		wp_enqueue_script( 'solar-power-calculator' );

		if ( self::$assets_queued ) {
			return;
		}
		self::$assets_queued = true;

		wp_add_inline_script(
			'solar-power-calculator',
			'window.SPC_DATA = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}

	/**
	 * Seed default options on activation.
	 */
	public static function activate() {
		if ( false === get_option( SPC_Settings::OPTION ) ) {
			add_option( SPC_Settings::OPTION, SPC_Settings::sanitize( array( 'show_costs' => 1 ) ) );
		}
	}
}

register_activation_hook( __FILE__, array( 'SPC_Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'SPC_Plugin', 'init' ) );
