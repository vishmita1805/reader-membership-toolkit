<?php
/**
 * Stripe Checkout donations through two REST routes:
 *   POST /wp-json/rmt/v1/checkout  creates a Checkout Session
 *   POST /wp-json/rmt/v1/webhook   verifies the Stripe signature and records the gift
 * Calls Stripe's HTTP API directly with wp_remote_post, so no SDK is required.
 */
class RMT_Donations {

	const TABLE = 'rmt_donations';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = $wpdb->prefix . self::TABLE;
		dbDelta( "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			stripe_session_id VARCHAR(255) NOT NULL,
			amount_cents INT UNSIGNED NOT NULL,
			currency CHAR(3) NOT NULL,
			frequency VARCHAR(10) NOT NULL,
			variant VARCHAR(50) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY stripe_session_id (stripe_session_id)
		) {$wpdb->get_charset_collate()};" );
	}

	public static function routes() {
		register_rest_route( 'rmt/v1', '/checkout', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'checkout' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'amount'    => array( 'required' => true, 'type' => 'integer' ),
				'frequency' => array( 'required' => true, 'type' => 'string', 'enum' => array( 'once', 'monthly' ) ),
				'variant'   => array( 'required' => false, 'type' => 'string' ),
			),
		) );
		register_rest_route( 'rmt/v1', '/webhook', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'webhook' ),
			'permission_callback' => '__return_true',
		) );
	}

	/** Validates a whole dollar amount and returns cents, or null when invalid. */
	public static function to_cents( $amount ) {
		$amount = (int) $amount;
		return ( $amount >= 1 && $amount <= 10000 ) ? $amount * 100 : null;
	}

	/** Builds the form body Stripe expects for a one time or monthly donation. */
	public static function session_params( $cents, $frequency, $currency, $variant, $return_url ) {
		$params = array(
			'mode'                                         => 'monthly' === $frequency ? 'subscription' : 'payment',
			'success_url'                                  => add_query_arg( 'rmt_thanks', '1', $return_url ),
			'cancel_url'                                   => $return_url,
			'line_items[0][quantity]'                      => 1,
			'line_items[0][price_data][currency]'          => $currency,
			'line_items[0][price_data][unit_amount]'       => $cents,
			'line_items[0][price_data][product_data][name]' => 'monthly' === $frequency ? 'Monthly membership' : 'One time donation',
			'metadata[frequency]'                          => $frequency,
			'metadata[variant]'                            => $variant,
		);
		if ( 'monthly' === $frequency ) {
			$params['line_items[0][price_data][recurring][interval]'] = 'month';
		}
		return $params;
	}

	public static function checkout( WP_REST_Request $req ) {
		$cents = self::to_cents( $req['amount'] );
		if ( null === $cents ) {
			return new WP_Error( 'rmt_amount', 'Please choose an amount between $1 and $10,000.', array( 'status' => 400 ) );
		}
		$secret = RMT_Settings::stripe_secret();
		if ( '' === $secret ) {
			return new WP_Error( 'rmt_config', 'Donations are not configured yet.', array( 'status' => 503 ) );
		}
		$return_url = wp_get_referer() ?: home_url( '/' );
		$variant    = sanitize_key( $req['variant'] ?? '' );
		$response   = wp_remote_post( 'https://api.stripe.com/v1/checkout/sessions', array(
			'timeout' => 15,
			'headers' => array( 'Authorization' => 'Bearer ' . $secret ),
			'body'    => self::session_params( $cents, $req['frequency'], RMT_Settings::get( 'currency' ), $variant, $return_url ),
		) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'rmt_stripe', 'We could not start checkout. Please try again.', array( 'status' => 502 ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return array( 'url' => esc_url_raw( $body['url'] ?? '' ) );
	}

	/**
	 * Verifies a Stripe-Signature header (t=timestamp,v1=hmac) against the raw payload.
	 * Rejects events older than the tolerance to block replays.
	 */
	public static function verify_signature( $payload, $header, $secret, $tolerance = 300, $now = null ) {
		if ( '' === (string) $secret || '' === (string) $header ) {
			return false;
		}
		$now       = $now ?? time();
		$timestamp = null;
		$sigs      = array();
		foreach ( explode( ',', $header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );
			if ( 2 !== count( $kv ) ) {
				continue;
			}
			if ( 't' === $kv[0] ) {
				$timestamp = (int) $kv[1];
			} elseif ( 'v1' === $kv[0] ) {
				$sigs[] = $kv[1];
			}
		}
		if ( null === $timestamp || abs( $now - $timestamp ) > $tolerance ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $sigs as $sig ) {
			if ( hash_equals( $expected, $sig ) ) {
				return true;
			}
		}
		return false;
	}

	public static function webhook( WP_REST_Request $req ) {
		$payload = $req->get_body();
		if ( ! self::verify_signature( $payload, $req->get_header( 'stripe_signature' ), RMT_Settings::stripe_webhook_secret() ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 400 );
		}
		$event = json_decode( $payload, true );
		if ( 'checkout.session.completed' === ( $event['type'] ?? '' ) ) {
			global $wpdb;
			$s = $event['data']['object'];
			// The unique key on stripe_session_id makes retries from Stripe idempotent.
			$wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->prefix}" . self::TABLE . ' (stripe_session_id, amount_cents, currency, frequency, variant, created_at) VALUES (%s, %d, %s, %s, %s, %s)',
				$s['id'], (int) $s['amount_total'], $s['currency'], $s['metadata']['frequency'] ?? 'once', $s['metadata']['variant'] ?? '', current_time( 'mysql', true )
			) );
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}

register_activation_hook( dirname( __DIR__ ) . '/reader-membership-toolkit.php', array( 'RMT_Donations', 'install' ) );
