<?php
/**
 * Admin settings page. Secrets are read from wp-config constants first,
 * so production keys never need to live in the database.
 */
class RMT_Settings {

	const OPTION = 'rmt_settings';

	public static function defaults() {
		return array(
			'free_articles'      => 3,
			'cta_heading'        => 'Journalism that holds power to account',
			'cta_body'           => 'Our reporting is free for everyone because readers like you support it.',
			'suggested_amounts'  => '10,25,50',
			'currency'           => 'usd',
			'ga4_measurement_id' => '',
			'experiment_enabled' => 1,
		);
	}

	public static function get( $key ) {
		$opts = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		return $opts[ $key ] ?? null;
	}

	public static function stripe_secret() {
		return defined( 'RMT_STRIPE_SECRET_KEY' ) ? RMT_STRIPE_SECRET_KEY : '';
	}

	public static function stripe_webhook_secret() {
		return defined( 'RMT_STRIPE_WEBHOOK_SECRET' ) ? RMT_STRIPE_WEBHOOK_SECRET : '';
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function menu() {
		add_options_page( 'Reader Membership', 'Reader Membership', 'manage_options', 'rmt', array( __CLASS__, 'render' ) );
	}

	public static function register() {
		register_setting( 'rmt', self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $input ) {
		$clean                       = self::defaults();
		$clean['free_articles']      = max( 0, absint( $input['free_articles'] ?? 3 ) );
		$clean['cta_heading']        = sanitize_text_field( $input['cta_heading'] ?? '' );
		$clean['cta_body']           = sanitize_textarea_field( $input['cta_body'] ?? '' );
		$clean['suggested_amounts']  = implode( ',', self::parse_amounts( $input['suggested_amounts'] ?? '' ) );
		$clean['currency']           = preg_match( '/^[a-z]{3}$/', $input['currency'] ?? '' ) ? $input['currency'] : 'usd';
		$clean['ga4_measurement_id'] = preg_match( '/^G-[A-Z0-9]+$/', $input['ga4_measurement_id'] ?? '' ) ? $input['ga4_measurement_id'] : '';
		$clean['experiment_enabled'] = empty( $input['experiment_enabled'] ) ? 0 : 1;
		return $clean;
	}

	/** Turns "10, 25,abc,50" into [10, 25, 50]. Amounts are whole dollars between 1 and 10000. */
	public static function parse_amounts( $raw ) {
		$out = array();
		foreach ( explode( ',', (string) $raw ) as $part ) {
			$n = (int) trim( $part );
			if ( $n >= 1 && $n <= 10000 ) {
				$out[] = $n;
			}
		}
		return $out ? array_values( array_unique( $out ) ) : array( 10, 25, 50 );
	}

	public static function render() {
		$o = wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
		?>
		<div class="wrap">
			<h1>Reader Membership Toolkit</h1>
			<p>Stripe keys are set in <code>wp-config.php</code> as <code>RMT_STRIPE_SECRET_KEY</code> and <code>RMT_STRIPE_WEBHOOK_SECRET</code>.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'rmt' ); ?>
				<table class="form-table">
					<tr><th>Free articles before the ask</th><td><input type="number" min="0" name="rmt_settings[free_articles]" value="<?php echo esc_attr( $o['free_articles'] ); ?>"></td></tr>
					<tr><th>CTA heading</th><td><input class="regular-text" name="rmt_settings[cta_heading]" value="<?php echo esc_attr( $o['cta_heading'] ); ?>"></td></tr>
					<tr><th>CTA body</th><td><textarea class="large-text" name="rmt_settings[cta_body]"><?php echo esc_textarea( $o['cta_body'] ); ?></textarea></td></tr>
					<tr><th>Suggested amounts</th><td><input name="rmt_settings[suggested_amounts]" value="<?php echo esc_attr( $o['suggested_amounts'] ); ?>"></td></tr>
					<tr><th>Currency</th><td><input name="rmt_settings[currency]" value="<?php echo esc_attr( $o['currency'] ); ?>"></td></tr>
					<tr><th>GA4 Measurement ID</th><td><input name="rmt_settings[ga4_measurement_id]" value="<?php echo esc_attr( $o['ga4_measurement_id'] ); ?>"></td></tr>
					<tr><th>Run headline A/B test</th><td><input type="checkbox" name="rmt_settings[experiment_enabled]" value="1" <?php checked( $o['experiment_enabled'], 1 ); ?>></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
