<?php
/**
 * Renders the donation call to action and loads the front end script.
 * Use the [rmt_donate] shortcode anywhere, or let the plugin add it after single posts.
 */
class RMT_CTA {

	public static function init() {
		add_shortcode( 'rmt_donate', array( __CLASS__, 'shortcode' ) );
		add_filter( 'the_content', array( __CLASS__, 'append' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		wp_enqueue_style( 'rmt', RMT_URL . 'assets/css/rmt.css', array(), RMT_VERSION );
		wp_enqueue_script( 'rmt-core', RMT_URL . 'assets/js/rmt-core.js', array(), RMT_VERSION, true );
		wp_enqueue_script( 'rmt', RMT_URL . 'assets/js/rmt.js', array( 'rmt-core' ), RMT_VERSION, true );
		wp_localize_script( 'rmt', 'RMT_CONFIG', array(
			'checkoutUrl'   => esc_url_raw( rest_url( 'rmt/v1/checkout' ) ),
			'freeArticles'  => (int) RMT_Settings::get( 'free_articles' ),
			'articleId'     => get_the_ID(),
			'ga4Id'         => RMT_Settings::get( 'ga4_measurement_id' ),
			'experiment'    => RMT_Settings::get( 'experiment_enabled' ) ? 'cta_headline_v1' : '',
			'headlines'     => array(
				'control' => RMT_Settings::get( 'cta_heading' ),
				'impact'  => RMT_Experiments::headline( 'cta_headline_v1', 'impact', RMT_Settings::get( 'cta_heading' ) ),
			),
		) );
	}

	public static function append( $content ) {
		if ( is_singular( 'post' ) && in_the_loop() && is_main_query() && ! has_shortcode( $content, 'rmt_donate' ) ) {
			$content .= self::shortcode();
		}
		return $content;
	}

	public static function shortcode() {
		$amounts = RMT_Settings::parse_amounts( RMT_Settings::get( 'suggested_amounts' ) );
		ob_start();
		?>
		<aside class="rmt-cta" data-rmt-cta hidden aria-labelledby="rmt-heading">
			<h2 id="rmt-heading" data-rmt-headline><?php echo esc_html( RMT_Settings::get( 'cta_heading' ) ); ?></h2>
			<p><?php echo esc_html( RMT_Settings::get( 'cta_body' ) ); ?></p>
			<fieldset class="rmt-frequency">
				<legend class="screen-reader-text">How often</legend>
				<label><input type="radio" name="rmt_freq" value="monthly" checked> Monthly</label>
				<label><input type="radio" name="rmt_freq" value="once"> One time</label>
			</fieldset>
			<div class="rmt-amounts">
				<?php foreach ( $amounts as $a ) : ?>
					<button type="button" data-rmt-amount="<?php echo esc_attr( $a ); ?>">$<?php echo esc_html( $a ); ?></button>
				<?php endforeach; ?>
			</div>
			<p class="rmt-error" data-rmt-error role="alert" hidden></p>
			<button type="button" class="rmt-dismiss" data-rmt-dismiss>Maybe later</button>
		</aside>
		<?php
		return ob_get_clean();
	}
}
