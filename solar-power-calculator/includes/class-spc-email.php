<?php
/**
 * Optional "email me these results" handler.
 *
 * The browser sends only the load list and the system choices; the sizing is
 * recalculated here so the emailed figures come from the server, not from
 * whatever the page happened to post.
 *
 * @package Solar_Power_Calculator
 */

defined( 'ABSPATH' ) || exit;

class SPC_Email {

	const RATE_LIMIT_SECONDS = 60;

	/**
	 * Hook the AJAX endpoints.
	 */
	public static function init() {
		add_action( 'wp_ajax_spc_email_results', array( __CLASS__, 'handle' ) );
		add_action( 'wp_ajax_nopriv_spc_email_results', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Validate, recalculate and send.
	 */
	public static function handle() {
		$settings = SPC_Data::settings();

		if ( empty( $settings['enable_email'] ) ) {
			wp_send_json_error( array( 'message' => 'Email delivery is switched off.' ), 403 );
		}

		if ( ! check_ajax_referer( 'spc_email_results', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Your session expired. Reload the page and try again.' ), 403 );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Please enter a valid email address.' ), 400 );
		}

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

		// One send per address per minute, and one per IP per minute.
		$keys = array( 'spc_rl_' . md5( $email ) );
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$keys[] = 'spc_rl_' . md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) );
		}
		foreach ( $keys as $key ) {
			if ( get_transient( $key ) ) {
				wp_send_json_error( array( 'message' => 'You just sent one. Give it a minute before trying again.' ), 429 );
			}
		}

		$items  = self::parse_items( isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '' );
		$config = self::parse_config( isset( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : '' );

		if ( empty( $items ) ) {
			wp_send_json_error( array( 'message' => 'Add at least one appliance first.' ), 400 );
		}

		$results = SPC_Calculator::calculate( $items, $config );

		$sent = wp_mail(
			$email,
			sprintf( '[%s] Your solar system sizing', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			self::body( $results, $name ),
			array( 'Content-Type: text/html; charset=UTF-8' )
		);

		if ( ! $sent ) {
			wp_send_json_error( array( 'message' => 'We could not send the email. Please try again later.' ), 500 );
		}

		foreach ( $keys as $key ) {
			set_transient( $key, 1, self::RATE_LIMIT_SECONDS );
		}

		// Copy the site owner so the enquiry is not lost.
		$recipient = sanitize_email( $settings['email_recipient'] );
		if ( is_email( $recipient ) && $recipient !== $email ) {
			wp_mail(
				$recipient,
				'New solar calculator enquiry',
				sprintf(
					'<p><strong>%1$s</strong> (%2$s) sized a system on your site.</p>%3$s',
					esc_html( $name ? $name : 'A visitor' ),
					esc_html( $email ),
					self::body( $results, '' )
				),
				array( 'Content-Type: text/html; charset=UTF-8' )
			);
		}

		/**
		 * Fires after results are emailed. Handy for CRM or newsletter hooks.
		 *
		 * @param string $email   Visitor email.
		 * @param string $name    Visitor name.
		 * @param array  $results Sizing results.
		 */
		do_action( 'spc_results_emailed', $email, $name, $results );

		wp_send_json_success( array( 'message' => 'Sent. Check your inbox.' ) );
	}

	/**
	 * Sanitize the posted load list.
	 *
	 * @param string $raw JSON payload.
	 * @return array
	 */
	private static function parse_items( $raw ) {
		$decoded = json_decode( (string) $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$items = array();
		foreach ( array_slice( $decoded, 0, 100 ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$items[] = array(
				'name'  => isset( $row['name'] ) ? sanitize_text_field( $row['name'] ) : 'Load',
				'watts' => isset( $row['watts'] ) ? (float) $row['watts'] : 0,
				'qty'   => isset( $row['qty'] ) ? (int) $row['qty'] : 0,
				'hours' => isset( $row['hours'] ) ? (float) $row['hours'] : 0,
				'duty'  => isset( $row['duty'] ) ? (float) $row['duty'] : 1,
				'surge' => isset( $row['surge'] ) ? (float) $row['surge'] : 1,
				'dc'    => ! empty( $row['dc'] ),
			);
		}

		return $items;
	}

	/**
	 * Sanitize the posted system choices.
	 *
	 * @param string $raw JSON payload.
	 * @return array
	 */
	private static function parse_config( $raw ) {
		$decoded = json_decode( (string) $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$batteries = SPC_Data::battery_types();
		$constants = SPC_Data::constants();

		$battery_type = isset( $decoded['battery_type'] ) ? sanitize_key( $decoded['battery_type'] ) : 'lithium';
		$unit_ids     = wp_list_pluck( $constants['battery_units'], 'id' );
		$unit         = isset( $decoded['battery_unit'] ) ? sanitize_key( $decoded['battery_unit'] ) : '12v200';
		$voltage      = isset( $decoded['voltage'] ) ? sanitize_key( $decoded['voltage'] ) : 'auto';

		return array(
			'psh'           => isset( $decoded['psh'] ) ? min( 12, max( 0.5, (float) $decoded['psh'] ) ) : 4.5,
			'autonomy'      => isset( $decoded['autonomy'] ) ? min( 7, max( 0.5, (float) $decoded['autonomy'] ) ) : 1,
			'battery_type'  => isset( $batteries[ $battery_type ] ) ? $battery_type : 'lithium',
			'voltage'       => in_array( $voltage, array( 'auto', '12', '24', '48' ), true ) ? $voltage : 'auto',
			'inverter_type' => isset( $decoded['inverter_type'] ) && 'modified_sine' === $decoded['inverter_type'] ? 'modified_sine' : 'pure_sine',
			'controller'    => isset( $decoded['controller'] ) && 'pwm' === $decoded['controller'] ? 'pwm' : 'mppt',
			'panel_watts'   => isset( $decoded['panel_watts'] ) ? min( 1000, max( 10, (float) $decoded['panel_watts'] ) ) : 550,
			'battery_unit'  => in_array( $unit, $unit_ids, true ) ? $unit : '12v200',
			'simultaneity'  => isset( $decoded['simultaneity'] ) ? min( 1, max( 0.1, (float) $decoded['simultaneity'] ) ) : $constants['simultaneity'],
		);
	}

	/**
	 * Build the HTML email body.
	 *
	 * @param array  $r    Results.
	 * @param string $name Recipient name.
	 * @return string
	 */
	private static function body( array $r, $name ) {
		$settings = SPC_Data::settings();
		$currency = $settings['currency'];

		$rows = array(
			'Daily energy use'   => number_format_i18n( $r['daily_wh'], 0 ) . ' Wh (' . number_format_i18n( $r['daily_kwh'], 2 ) . ' kWh)',
			'Solar array'        => $r['panel_qty'] . ' &times; ' . number_format_i18n( $r['panel_watts'], 0 ) . 'W = ' . number_format_i18n( $r['array_installed'], 0 ) . 'W',
			'Battery bank'       => $r['battery_qty'] . ' &times; ' . esc_html( $r['battery_unit'] ) . ' (' . number_format_i18n( $r['bank_kwh'], 2 ) . ' kWh nameplate, ' . number_format_i18n( $r['usable_kwh'], 2 ) . ' kWh usable)',
			'Battery type'       => esc_html( $r['battery_label'] ),
			'Inverter'           => number_format_i18n( $r['inverter_rated'], 0 ) . 'W continuous, ' . number_format_i18n( $r['surge_w'], 0 ) . 'W surge',
			'Charge controller'  => number_format_i18n( $r['controller_rated'], 0 ) . 'A ' . esc_html( $r['controller_type'] ),
			'System voltage'     => $r['system_voltage'] . 'V',
			'Peak sun hours'     => number_format_i18n( $r['psh'], 1 ) . ' h/day',
			'Days of backup'     => number_format_i18n( $r['autonomy'], 1 ),
			'CO2 avoided a year' => number_format_i18n( $r['co2_saved_kg'], 0 ) . ' kg',
		);

		if ( ! empty( $settings['show_costs'] ) ) {
			$rows['Budget estimate'] = $currency . number_format_i18n( $r['cost_total'], 0 );
		}

		$html  = '<div style="font-family:Arial,Helvetica,sans-serif;color:#1f2933;max-width:640px">';
		$html .= '<h2 style="color:#0f766e">Your solar system sizing</h2>';

		if ( $name ) {
			$html .= '<p>Hi ' . esc_html( $name ) . ',</p>';
		}
		$html .= '<p>Here is the system size for the appliances you selected on ' . esc_html( get_bloginfo( 'name' ) ) . '.</p>';

		$html .= '<table cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse;width:100%">';
		foreach ( $rows as $label => $value ) {
			$html .= '<tr style="border-bottom:1px solid #e4e7eb">'
				. '<td style="color:#616e7c">' . esc_html( $label ) . '</td>'
				. '<td style="text-align:right;font-weight:bold">' . $value . '</td>'
				. '</tr>';
		}
		$html .= '</table>';

		if ( ! empty( $r['breakdown'] ) ) {
			$html .= '<h3 style="margin-top:24px">Your load list</h3>';
			$html .= '<table cellpadding="6" cellspacing="0" border="0" style="border-collapse:collapse;width:100%">';
			$html .= '<tr style="background:#f5f7fa"><th align="left">Appliance</th><th align="right">Qty</th><th align="right">Watts</th><th align="right">Hours</th><th align="right">Wh/day</th></tr>';
			foreach ( $r['breakdown'] as $row ) {
				$html .= '<tr style="border-bottom:1px solid #e4e7eb">'
					. '<td>' . esc_html( $row['name'] ) . ( $row['dc'] ? ' <span style="color:#616e7c">(DC)</span>' : '' ) . '</td>'
					. '<td align="right">' . (int) $row['qty'] . '</td>'
					. '<td align="right">' . number_format_i18n( $row['watts'], 0 ) . '</td>'
					. '<td align="right">' . number_format_i18n( $row['hours'], 1 ) . '</td>'
					. '<td align="right">' . number_format_i18n( $row['wh'], 0 ) . '</td>'
					. '</tr>';
			}
			$html .= '</table>';
		}

		if ( ! empty( $r['warnings'] ) ) {
			$html .= '<h3 style="margin-top:24px">Worth knowing</h3><ul>';
			foreach ( $r['warnings'] as $warning ) {
				$html .= '<li>' . esc_html( $warning ) . '</li>';
			}
			$html .= '</ul>';
		}

		$html .= '<p style="color:#616e7c;font-size:13px;margin-top:24px">These are planning estimates. Have a qualified installer confirm cable sizes, protection and mounting before you buy.</p>';
		$html .= '<p style="font-size:13px"><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( get_bloginfo( 'name' ) ) . '</a></p>';
		$html .= '</div>';

		return $html;
	}
}
