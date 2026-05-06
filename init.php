<?php
/**
 * Entry point.
 *
 * @package Optiz
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

( static function () {
	global $optiz_candidates;

	if ( ! is_array( $optiz_candidates ) ) {
		$optiz_candidates = [];
	}

	$version = '1.0.0';

	$optiz_candidates[ $version ] = __DIR__;

	if ( defined( 'OPTIZ_ELECTION_HOOKED' ) ) {
		return;
	}

	define( 'OPTIZ_ELECTION_HOOKED', true );

	add_action(
		'plugins_loaded',
		static function () {
			global $optiz_candidates;

			uksort( $optiz_candidates, 'version_compare' );

			$winner_dir     = end( $optiz_candidates );
			$winner_version = key( $optiz_candidates );

			define( 'OPTIZ_LOADED_VERSION', $winner_version );
			define( 'OPTIZ_DIR', $winner_dir );
			define( 'OPTIZ_URL', plugin_dir_url( $winner_dir . '/init.php' ) );
		},
		0
	);
} )();
