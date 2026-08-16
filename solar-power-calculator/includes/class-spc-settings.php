<?php
/**
 * Admin settings screen (Settings -> Solar Calculator).
 *
 * @package Solar_Power_Calculator
 */

defined( 'ABSPATH' ) || exit;

class SPC_Settings {

	const OPTION = 'spc_settings';
	const GROUP  = 'spc_settings_group';

	/**
	 * Hook the admin screen.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_filter( 'plugin_action_links_' . SPC_BASENAME, array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Add the settings page.
	 */
	public static function menu() {
		add_options_page(
			'Solar Calculator',
			'Solar Calculator',
			'manage_options',
			'solar-calculator',
			array( __CLASS__, 'page' )
		);
	}

	/**
	 * Register the option and its sanitizer.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Add a Settings link on the plugins list.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$url = admin_url( 'options-general.php?page=solar-calculator' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">Settings</a>' );

		return $links;
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		$region_ids  = wp_list_pluck( SPC_Data::regions(), 'id' );
		$battery_ids = array_keys( SPC_Data::battery_types() );

		$region  = isset( $input['default_region'] ) ? sanitize_key( $input['default_region'] ) : '';
		$battery = isset( $input['default_battery'] ) ? sanitize_key( $input['default_battery'] ) : '';

		return array(
			'default_region'   => in_array( $region, $region_ids, true ) ? $region : 'wafrica_south',
			'default_battery'  => in_array( $battery, $battery_ids, true ) ? $battery : 'lithium',
			'default_panel'    => isset( $input['default_panel'] ) ? min( 1000, max( 10, (int) $input['default_panel'] ) ) : 550,
			'show_costs'       => empty( $input['show_costs'] ) ? 0 : 1,
			'currency'         => isset( $input['currency'] ) ? sanitize_text_field( substr( $input['currency'], 0, 5 ) ) : '$',
			'cost_pv_watt'     => isset( $input['cost_pv_watt'] ) ? max( 0, (float) $input['cost_pv_watt'] ) : 0.9,
			'cost_inverter_kw' => isset( $input['cost_inverter_kw'] ) ? max( 0, (float) $input['cost_inverter_kw'] ) : 300,
			'cost_install_pct' => isset( $input['cost_install_pct'] ) ? min( 200, max( 0, (float) $input['cost_install_pct'] ) ) : 25,
			'enable_email'     => empty( $input['enable_email'] ) ? 0 : 1,
			'email_recipient'  => isset( $input['email_recipient'] ) ? sanitize_email( $input['email_recipient'] ) : get_option( 'admin_email' ),
			'cta_text'         => isset( $input['cta_text'] ) ? sanitize_text_field( $input['cta_text'] ) : '',
			'cta_url'          => isset( $input['cta_url'] ) ? esc_url_raw( $input['cta_url'] ) : '',
		);
	}

	/**
	 * Render the settings page.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s = SPC_Data::settings();
		$o = self::OPTION;
		?>
		<div class="wrap">
			<h1>Solar Calculator</h1>

			<div class="notice notice-info inline" style="padding:12px 16px;margin:16px 0">
				<p style="margin:0 0 6px"><strong>How to use it:</strong> paste this shortcode into any page, post or Elementor text widget:</p>
				<p style="margin:0"><code>[solar_calculator]</code></p>
				<p style="margin:8px 0 0">Options:
					<code>[solar_calculator profile="travel"]</code> to open on a different tab (<code>home</code>, <code>office</code>, <code>travel</code>),
					<code>title=""</code> to hide the heading, and
					<code>costs="no"</code> to hide the budget card on one page only.
				</p>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2 class="title">Defaults</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="spc_region">Default region</label></th>
						<td>
							<select id="spc_region" name="<?php echo esc_attr( $o ); ?>[default_region]">
								<?php foreach ( SPC_Data::regions() as $region ) : ?>
									<option value="<?php echo esc_attr( $region['id'] ); ?>" <?php selected( $s['default_region'], $region['id'] ); ?>>
										<?php echo esc_html( $region['name'] ); ?> (<?php echo esc_html( $region['psh'] ); ?> peak sun hours)
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">Pick the region most of your visitors are in.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spc_battery">Default battery type</label></th>
						<td>
							<select id="spc_battery" name="<?php echo esc_attr( $o ); ?>[default_battery]">
								<?php foreach ( SPC_Data::battery_types() as $key => $battery ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['default_battery'], $key ); ?>>
										<?php echo esc_html( $battery['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spc_panel">Default panel size</label></th>
						<td>
							<input type="number" id="spc_panel" name="<?php echo esc_attr( $o ); ?>[default_panel]" value="<?php echo esc_attr( $s['default_panel'] ); ?>" min="10" max="1000" step="10" class="small-text"> W
						</td>
					</tr>
				</table>

				<h2 class="title">Budget estimate</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Show budget card</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $o ); ?>[show_costs]" value="1" <?php checked( $s['show_costs'], 1 ); ?>>
								Show an indicative price with the results
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spc_currency">Currency symbol</label></th>
						<td><input type="text" id="spc_currency" name="<?php echo esc_attr( $o ); ?>[currency]" value="<?php echo esc_attr( $s['currency'] ); ?>" class="small-text" maxlength="5"></td>
					</tr>
					<tr>
						<th scope="row"><label for="spc_pv">Panel price</label></th>
						<td>
							<?php echo esc_html( $s['currency'] ); ?>
							<input type="number" id="spc_pv" name="<?php echo esc_attr( $o ); ?>[cost_pv_watt]" value="<?php echo esc_attr( $s['cost_pv_watt'] ); ?>" min="0" step="0.01" class="small-text"> per watt
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spc_inv">Inverter price</label></th>
						<td>
							<?php echo esc_html( $s['currency'] ); ?>
							<input type="number" id="spc_inv" name="<?php echo esc_attr( $o ); ?>[cost_inverter_kw]" value="<?php echo esc_attr( $s['cost_inverter_kw'] ); ?>" min="0" step="1" class="small-text"> per kW
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spc_install">Wiring &amp; installation</label></th>
						<td>
							<input type="number" id="spc_install" name="<?php echo esc_attr( $o ); ?>[cost_install_pct]" value="<?php echo esc_attr( $s['cost_install_pct'] ); ?>" min="0" max="200" step="1" class="small-text"> % of hardware cost
						</td>
					</tr>
					<tr>
						<th scope="row">Battery prices</th>
						<td><p class="description">Battery cost per kWh is set per chemistry. Change it with the <code>spc_battery_types</code> filter if your market differs.</p></td>
					</tr>
				</table>

				<h2 class="title">Leads</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Email results</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $o ); ?>[enable_email]" value="1" <?php checked( $s['enable_email'], 1 ); ?>>
								Let visitors email themselves a copy (a copy is sent to you too)
							</label>
							<p class="description">Make sure your site can send mail (an SMTP plugin is usually needed) and that your privacy policy covers it.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spc_recipient">Send your copy to</label></th>
						<td><input type="email" id="spc_recipient" name="<?php echo esc_attr( $o ); ?>[email_recipient]" value="<?php echo esc_attr( $s['email_recipient'] ); ?>" class="regular-text"></td>
					</tr>
					<tr>
						<th scope="row"><label for="spc_cta_text">Call-to-action button</label></th>
						<td>
							<input type="text" id="spc_cta_text" name="<?php echo esc_attr( $o ); ?>[cta_text]" value="<?php echo esc_attr( $s['cta_text'] ); ?>" class="regular-text" placeholder="Get a quote">
							<p class="description">Button label. Leave empty to hide the button.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spc_cta_url">Button link</label></th>
						<td><input type="url" id="spc_cta_url" name="<?php echo esc_attr( $o ); ?>[cta_url]" value="<?php echo esc_attr( $s['cta_url'] ); ?>" class="regular-text" placeholder="https://sustainaportal.com/contact"></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
