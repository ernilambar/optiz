<?php
/**
 * Entry point.
 *
 * @package Optiz
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// Bootstrap class lives in the global namespace so every bundled copy of this
// file (across multiple plugins) shares the same candidate registry without
// relying on a PHP global variable.
if ( ! class_exists( 'OptizBootstrap', false ) ) {
	/**
	 * Bootstrap class for version-election across multiple bundled copies.
	 *
	 * @since 1.0.0
	 */
	final class OptizBootstrap {

		/**
		 * Version-to-directory map for all registered copies.
		 *
		 * @var array<string,string>
		 */
		private static array $candidates = [];

		/**
		 * Registers a copy of the library as a candidate for election.
		 *
		 * @since 1.0.0
		 *
		 * @param string $version Semantic version string.
		 * @param string $dir     Absolute path to the library root directory.
		 */
		public static function add_candidate( string $version, string $dir ): void {
			self::$candidates[ $version ] = $dir;
		}

		/**
		 * Elects the highest-version candidate and defines the three library constants.
		 *
		 * Hooked to plugins_loaded at priority 0 by the first init.php that runs.
		 *
		 * @since 1.0.0
		 */
		public static function elect(): void {
			uksort( self::$candidates, 'version_compare' );

			$winner_dir     = end( self::$candidates );
			$winner_version = key( self::$candidates );

			if ( ! defined( 'OPTIZ_LOADED_VERSION' ) ) {
				define( 'OPTIZ_LOADED_VERSION', $winner_version );
			}

			if ( ! defined( 'OPTIZ_DIR' ) ) {
				define( 'OPTIZ_DIR', $winner_dir );
			}

			if ( ! defined( 'OPTIZ_URL' ) ) {
				define( 'OPTIZ_URL', plugin_dir_url( $winner_dir . '/init.php' ) );
			}
		}
	}
}

( static function () {
	$version = '1.0.0';

	OptizBootstrap::add_candidate( $version, __DIR__ );

	if ( defined( 'OPTIZ_ELECTION_HOOKED' ) ) {
		return;
	}

	define( 'OPTIZ_ELECTION_HOOKED', true );

	add_action( 'plugins_loaded', [ 'OptizBootstrap', 'elect' ], 0 );
} )();
