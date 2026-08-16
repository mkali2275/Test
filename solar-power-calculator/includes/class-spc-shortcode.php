<?php
/**
 * Front-end markup for the [solar_calculator] shortcode.
 *
 * The form is rendered server-side so the block is visible (and indexable)
 * before JavaScript runs; the script then wires up live calculation.
 *
 * @package Solar_Power_Calculator
 */

defined( 'ABSPATH' ) || exit;

class SPC_Shortcode {

	/**
	 * Instances rendered on the current page, used to keep element ids unique.
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * Register the shortcode.
	 */
	public static function init() {
		add_shortcode( 'solar_calculator', array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the calculator.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'profile'  => 'home',   // home | office | travel
				'title'    => 'Solar System Sizing Calculator',
				'subtitle' => 'Pick what you need to run and get the panel, battery and inverter sizes to run it.',
				'costs'    => '',       // "yes" / "no" overrides the global setting
			),
			$atts,
			'solar_calculator'
		);

		$settings   = SPC_Data::settings();
		$profiles   = SPC_Data::profiles();
		$regions    = SPC_Data::regions();
		$batteries  = SPC_Data::battery_types();
		$constants  = SPC_Data::constants();

		$profile = array_key_exists( $atts['profile'], $profiles ) ? $atts['profile'] : 'home';

		$show_costs = ! empty( $settings['show_costs'] );
		if ( 'no' === strtolower( $atts['costs'] ) ) {
			$show_costs = false;
		} elseif ( 'yes' === strtolower( $atts['costs'] ) ) {
			$show_costs = true;
		}

		self::$instance++;
		$uid = 'spc-' . self::$instance;

		SPC_Plugin::enqueue_assets(
			array(
				'appliances' => SPC_Data::appliances(),
				'profiles'   => $profiles,
				'regions'    => $regions,
				'batteries'  => $batteries,
				'constants'  => $constants,
				'settings'   => array(
					'currency'         => $settings['currency'],
					'showCosts'        => $show_costs,
					'costPvWatt'       => (float) $settings['cost_pv_watt'],
					'costInverterKw'   => (float) $settings['cost_inverter_kw'],
					'costInstallPct'   => (float) $settings['cost_install_pct'],
					'enableEmail'      => ! empty( $settings['enable_email'] ),
					'ctaText'          => $settings['cta_text'],
					'ctaUrl'           => $settings['cta_url'],
					'defaultRegion'    => $settings['default_region'],
					'defaultBattery'   => $settings['default_battery'],
					'defaultPanel'     => (float) $settings['default_panel'],
				),
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'spc_email_results' ),
			)
		);

		ob_start();
		?>
		<div class="spc" id="<?php echo esc_attr( $uid ); ?>" data-profile="<?php echo esc_attr( $profile ); ?>" data-costs="<?php echo $show_costs ? '1' : '0'; ?>">

			<?php if ( $atts['title'] ) : ?>
				<header class="spc-header">
					<h2 class="spc-title"><?php echo esc_html( $atts['title'] ); ?></h2>
					<?php if ( $atts['subtitle'] ) : ?>
						<p class="spc-subtitle"><?php echo esc_html( $atts['subtitle'] ); ?></p>
					<?php endif; ?>
				</header>
			<?php endif; ?>

			<noscript>
				<p class="spc-noscript">This calculator needs JavaScript enabled in your browser.</p>
			</noscript>

			<div class="spc-body" hidden>

				<!-- Step 1: where is the system going -->
				<section class="spc-step" aria-labelledby="<?php echo esc_attr( $uid ); ?>-step1">
					<h3 class="spc-step-title" id="<?php echo esc_attr( $uid ); ?>-step1">
						<span class="spc-step-num">1</span> Where is this system going?
					</h3>
					<div class="spc-profiles" role="radiogroup" aria-label="Usage profile">
						<?php foreach ( $profiles as $key => $data ) : ?>
							<button type="button"
								class="spc-profile<?php echo $key === $profile ? ' is-active' : ''; ?>"
								role="radio"
								aria-checked="<?php echo $key === $profile ? 'true' : 'false'; ?>"
								data-profile="<?php echo esc_attr( $key ); ?>">
								<span class="spc-profile-icon" aria-hidden="true" data-icon="<?php echo esc_attr( $data['icon'] ); ?>"></span>
								<span class="spc-profile-label"><?php echo esc_html( $data['label'] ); ?></span>
								<span class="spc-profile-blurb"><?php echo esc_html( $data['blurb'] ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				</section>

				<!-- Step 2: the loads -->
				<section class="spc-step" aria-labelledby="<?php echo esc_attr( $uid ); ?>-step2">
					<h3 class="spc-step-title" id="<?php echo esc_attr( $uid ); ?>-step2">
						<span class="spc-step-num">2</span> What do you need to power?
					</h3>

					<div class="spc-picker">
						<label class="spc-search">
							<span class="screen-reader-text">Search appliances</span>
							<input type="search" class="spc-search-input" placeholder="Search appliances&hellip;" autocomplete="off">
						</label>
						<div class="spc-library" role="list"></div>
					</div>

					<div class="spc-selected">
						<div class="spc-selected-head">
							<h4>Your load list</h4>
							<button type="button" class="spc-link spc-clear" hidden>Clear all</button>
						</div>

						<p class="spc-empty">Nothing selected yet. Add appliances above, or add your own below.</p>

						<div class="spc-table-wrap">
							<table class="spc-table" hidden>
								<thead>
									<tr>
										<th scope="col" class="spc-col-name">Appliance</th>
										<th scope="col" class="spc-col-num">Qty</th>
										<th scope="col" class="spc-col-num">Watts each</th>
										<th scope="col" class="spc-col-num">Hours/day</th>
										<th scope="col" class="spc-col-num">Wh/day</th>
										<th scope="col" class="spc-col-action"><span class="screen-reader-text">Remove</span></th>
									</tr>
								</thead>
								<tbody></tbody>
								<tfoot>
									<tr>
										<th scope="row" colspan="4">Total daily energy</th>
										<td class="spc-col-num spc-total-wh">0 Wh</td>
										<td></td>
									</tr>
								</tfoot>
							</table>
						</div>

						<p class="spc-total-mobile" hidden>
							<span>Total daily energy</span>
							<strong class="spc-total-wh">0 Wh</strong>
						</p>

						<details class="spc-custom">
							<summary>Add an appliance that isn't listed</summary>
							<div class="spc-custom-fields">
								<label>Name
									<input type="text" class="spc-custom-name" placeholder="e.g. Borehole pump">
								</label>
								<label>Watts
									<input type="number" class="spc-custom-watts" min="1" step="1" placeholder="750">
								</label>
								<label>Hours/day
									<input type="number" class="spc-custom-hours" min="0" max="24" step="0.25" placeholder="2">
								</label>
								<label class="spc-check">
									<input type="checkbox" class="spc-custom-dc">
									<span>Runs on 12/24V DC<span class="spc-hint" title="DC appliances draw straight from the battery and skip inverter losses">?</span></span>
								</label>
								<button type="button" class="spc-btn spc-btn-secondary spc-custom-add">Add</button>
							</div>
							<p class="spc-custom-error" role="alert" hidden></p>
						</details>
					</div>
				</section>

				<!-- Step 3: system options -->
				<section class="spc-step" aria-labelledby="<?php echo esc_attr( $uid ); ?>-step3">
					<h3 class="spc-step-title" id="<?php echo esc_attr( $uid ); ?>-step3">
						<span class="spc-step-num">3</span> Your location and equipment
					</h3>

					<div class="spc-fields">
						<label class="spc-field">
							<span class="spc-field-label">Region <span class="spc-hint" title="Sets average peak sun hours - the number of hours per day your panels produce at full rating">?</span></span>
							<select class="spc-region">
								<?php foreach ( $regions as $region ) : ?>
									<option value="<?php echo esc_attr( $region['id'] ); ?>"
										data-psh="<?php echo esc_attr( $region['psh'] ); ?>"
										<?php selected( $settings['default_region'], $region['id'] ); ?>>
										<?php echo esc_html( $region['name'] ); ?> &mdash; <?php echo esc_html( $region['psh'] ); ?>h
									</option>
								<?php endforeach; ?>
								<option value="custom">Enter my own figure</option>
							</select>
						</label>

						<label class="spc-field spc-field-psh" hidden>
							<span class="spc-field-label">Peak sun hours per day</span>
							<input type="number" class="spc-psh" min="0.5" max="10" step="0.1" value="4.5">
						</label>

						<label class="spc-field">
							<span class="spc-field-label">Days of backup <span class="spc-hint" title="How many days the batteries should carry the load with no sun at all">?</span></span>
							<select class="spc-autonomy">
								<option value="0.5">Half a day (evening only)</option>
								<option value="1" selected>1 day</option>
								<option value="2">2 days</option>
								<option value="3">3 days</option>
							</select>
						</label>

						<label class="spc-field">
							<span class="spc-field-label">Battery type</span>
							<select class="spc-battery-type">
								<?php foreach ( $batteries as $key => $battery ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['default_battery'], $key ); ?>>
										<?php echo esc_html( $battery['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>

						<label class="spc-field">
							<span class="spc-field-label">Panel size</span>
							<select class="spc-panel">
								<?php foreach ( $constants['panel_sizes'] as $size ) : ?>
									<option value="<?php echo esc_attr( $size ); ?>" <?php selected( (float) $settings['default_panel'], (float) $size ); ?>>
										<?php echo esc_html( $size ); ?>W panels
									</option>
								<?php endforeach; ?>
							</select>
						</label>

						<label class="spc-field">
							<span class="spc-field-label">Battery size</span>
							<select class="spc-battery-unit">
								<?php foreach ( $constants['battery_units'] as $unit ) : ?>
									<option value="<?php echo esc_attr( $unit['id'] ); ?>" <?php selected( '12v200', $unit['id'] ); ?>>
										<?php echo esc_html( $unit['label'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>

					<details class="spc-advanced">
						<summary>Advanced options</summary>
						<div class="spc-fields">
							<label class="spc-field">
								<span class="spc-field-label">System voltage</span>
								<select class="spc-voltage">
									<option value="auto" selected>Choose for me</option>
									<option value="12">12V</option>
									<option value="24">24V</option>
									<option value="48">48V</option>
								</select>
							</label>

							<label class="spc-field">
								<span class="spc-field-label">Inverter type</span>
								<select class="spc-inverter-type">
									<option value="pure_sine" selected>Pure sine wave</option>
									<option value="modified_sine">Modified sine wave</option>
								</select>
							</label>

							<label class="spc-field">
								<span class="spc-field-label">Charge controller</span>
								<select class="spc-controller">
									<option value="mppt" selected>MPPT</option>
									<option value="pwm">PWM</option>
								</select>
							</label>

							<label class="spc-field">
								<span class="spc-field-label">
									Simultaneous use
									<span class="spc-hint" title="Share of your connected appliances likely to run at the same moment. Drives inverter size, not energy.">?</span>
								</span>
								<input type="range" class="spc-simultaneity" min="30" max="100" step="5" value="70">
								<output class="spc-simultaneity-out">70%</output>
							</label>
						</div>
					</details>
				</section>

				<!-- Results -->
				<section class="spc-step spc-results-step" aria-labelledby="<?php echo esc_attr( $uid ); ?>-results" aria-live="polite">
					<h3 class="spc-step-title" id="<?php echo esc_attr( $uid ); ?>-results">
						<span class="spc-step-num">4</span> Your system
					</h3>

					<div class="spc-results-empty">
						<p>Add at least one appliance to see your system size.</p>
					</div>

					<div class="spc-results" hidden>

						<div class="spc-headline">
							<div class="spc-headline-item">
								<span class="spc-headline-value spc-r-daily">0</span>
								<span class="spc-headline-unit">kWh per day</span>
								<span class="spc-headline-label">Energy you use</span>
							</div>
							<div class="spc-headline-item">
								<span class="spc-headline-value spc-r-array">0</span>
								<span class="spc-headline-unit">watts of panels</span>
								<span class="spc-headline-label">Solar array</span>
							</div>
							<div class="spc-headline-item">
								<span class="spc-headline-value spc-r-bank">0</span>
								<span class="spc-headline-unit">kWh of battery</span>
								<span class="spc-headline-label">Storage</span>
							</div>
						</div>

						<div class="spc-cards">

							<article class="spc-card">
								<h4>Solar array</h4>
								<p class="spc-card-lead"><strong class="spc-r-panel-qty">0</strong> &times; <span class="spc-r-panel-size">550</span>W panels</p>
								<dl>
									<div><dt>Minimum array</dt><dd class="spc-r-array-min">0 W</dd></div>
									<div><dt>Installed</dt><dd class="spc-r-array-installed">0 W</dd></div>
									<div><dt>Expected harvest</dt><dd class="spc-r-harvest">0 Wh/day</dd></div>
									<div><dt>Peak sun hours</dt><dd class="spc-r-psh">0 h</dd></div>
								</dl>
							</article>

							<article class="spc-card">
								<h4>Battery bank</h4>
								<p class="spc-card-lead"><strong class="spc-r-batt-qty">0</strong> &times; <span class="spc-r-batt-unit">12V 200Ah</span></p>
								<dl>
									<div><dt>Bank capacity</dt><dd class="spc-r-bank-ah">0 Ah</dd></div>
									<div><dt>Nameplate energy</dt><dd class="spc-r-bank-kwh">0 kWh</dd></div>
									<div><dt>Usable energy</dt><dd class="spc-r-usable">0 kWh</dd></div>
									<div><dt>Wiring</dt><dd class="spc-r-batt-config">&mdash;</dd></div>
									<div><dt>Runs your load for</dt><dd class="spc-r-backup">0 h</dd></div>
								</dl>
							</article>

							<article class="spc-card">
								<h4>Inverter</h4>
								<p class="spc-card-lead"><strong class="spc-r-inverter">0</strong> W continuous</p>
								<dl>
									<div><dt>Running load</dt><dd class="spc-r-running">0 W</dd></div>
									<div><dt>Startup surge</dt><dd class="spc-r-surge">0 W</dd></div>
									<div><dt>Apparent power</dt><dd class="spc-r-va">0 VA</dd></div>
									<div><dt>System voltage</dt><dd class="spc-r-voltage">12V</dd></div>
								</dl>
							</article>

							<article class="spc-card">
								<h4>Charge controller</h4>
								<p class="spc-card-lead"><strong class="spc-r-controller">0</strong> A <span class="spc-r-controller-type">MPPT</span></p>
								<dl>
									<div><dt>Calculated current</dt><dd class="spc-r-controller-calc">0 A</dd></div>
									<div><dt>Array voltage side</dt><dd class="spc-r-voltage-2">12V</dd></div>
								</dl>
							</article>

							<article class="spc-card spc-card-impact">
								<h4>Yearly impact</h4>
								<dl>
									<div><dt>Energy generated</dt><dd class="spc-r-annual">0 kWh</dd></div>
									<div><dt>CO<sub>2</sub> avoided</dt><dd class="spc-r-co2">0 kg</dd></div>
									<div><dt>Same as planting</dt><dd class="spc-r-trees">0 trees</dd></div>
									<div><dt>Generator fuel saved</dt><dd class="spc-r-fuel">0 L</dd></div>
								</dl>
							</article>

							<article class="spc-card spc-card-cost"<?php echo $show_costs ? '' : ' hidden'; ?>>
								<h4>Budget estimate</h4>
								<p class="spc-card-lead"><strong class="spc-r-cost-total">&mdash;</strong></p>
								<dl>
									<div><dt>Panels</dt><dd class="spc-r-cost-pv">&mdash;</dd></div>
									<div><dt>Batteries</dt><dd class="spc-r-cost-batt">&mdash;</dd></div>
									<div><dt>Inverter</dt><dd class="spc-r-cost-inv">&mdash;</dd></div>
									<div><dt>Wiring &amp; install</dt><dd class="spc-r-cost-install">&mdash;</dd></div>
								</dl>
								<p class="spc-card-note">Indicative only. Prices vary by brand and market.</p>
							</article>

						</div>

						<div class="spc-warnings" hidden>
							<h4>Worth knowing</h4>
							<ul></ul>
						</div>

						<div class="spc-actions">
							<button type="button" class="spc-btn spc-btn-secondary spc-print">Print / save as PDF</button>
							<?php if ( ! empty( $settings['enable_email'] ) ) : ?>
								<button type="button" class="spc-btn spc-btn-secondary spc-email-toggle">Email me these results</button>
							<?php endif; ?>
							<?php if ( ! empty( $settings['cta_text'] ) && ! empty( $settings['cta_url'] ) ) : ?>
								<a class="spc-btn spc-btn-primary" href="<?php echo esc_url( $settings['cta_url'] ); ?>">
									<?php echo esc_html( $settings['cta_text'] ); ?>
								</a>
							<?php endif; ?>
						</div>

						<?php if ( ! empty( $settings['enable_email'] ) ) : ?>
							<form class="spc-email-form" hidden>
								<label class="spc-field">
									<span class="spc-field-label">Your email</span>
									<input type="email" class="spc-email-input" required placeholder="you@example.com">
								</label>
								<label class="spc-field">
									<span class="spc-field-label">Name (optional)</span>
									<input type="text" class="spc-email-name" placeholder="Your name">
								</label>
								<button type="submit" class="spc-btn spc-btn-primary spc-email-send">Send results</button>
								<p class="spc-email-status" role="status"></p>
							</form>
						<?php endif; ?>

						<p class="spc-disclaimer">
							Estimates for planning. Have a qualified installer confirm cable sizes, protection and mounting before you buy.
						</p>
					</div>
				</section>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
