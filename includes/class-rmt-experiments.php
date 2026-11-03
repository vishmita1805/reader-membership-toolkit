<?php
/**
 * Deterministic A/B assignment. The same visitor id always lands in the same
 * variant, so results are stable without storing extra state on the server.
 */
class RMT_Experiments {

	const EXPERIMENTS = array(
		'cta_headline_v1' => array(
			'control'  => null, // uses the heading from settings
			'impact'   => 'Your gift helps us change laws and expose wrongdoing',
		),
	);

	public static function variant( $experiment, $visitor_id ) {
		if ( ! isset( self::EXPERIMENTS[ $experiment ] ) || '' === (string) $visitor_id ) {
			return 'control';
		}
		$variants = array_keys( self::EXPERIMENTS[ $experiment ] );
		$bucket   = hexdec( substr( hash( 'sha256', $experiment . ':' . $visitor_id ), 0, 8 ) );
		return $variants[ $bucket % count( $variants ) ];
	}

	public static function headline( $experiment, $variant, $fallback ) {
		$copy = self::EXPERIMENTS[ $experiment ][ $variant ] ?? null;
		return $copy ?? $fallback;
	}
}
