<?php
namespace GroundhoggBookingCalendar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Autoloader {

    /**
     * Classes map.
     *
     * Maps Groundhogg classes to file names.
     *
     * @since 1.6.0
     * @access private
     * @static
     *
     * @var array Classes used by groundhogg.
     */
    private static $classes_map = [

    ];

    /**
	 * Run autoloader.
	 *
	 * Register a function as `__autoload()` implementation.
	 *
	 * @since 1.6.0
	 * @access public
	 * @static
	 */
	public static function run() {
		spl_autoload_register( [ __CLASS__, 'autoload' ] );
	}

	/**
	 * Load class.
	 *
	 * For a given class name, require the class file.
	 *
	 * @since 1.6.0
	 * @access private
	 * @static
	 *
	 * @param string $relative_class_name Class name.
	 */
	private static function load_class( $relative_class_name ) {
		if ( isset( self::$classes_map[ $relative_class_name ] ) ) {
			$filename = GROUNDHOGG_BOOKING_CALENDAR_PATH . '/' . self::$classes_map[ $relative_class_name ];
		} else {
			$filename = strtolower(
				preg_replace(
					[ '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
					[ '$1-$2', '-', DIRECTORY_SEPARATOR ],
					$relative_class_name
				)
			);

			$is_filename = GROUNDHOGG_BOOKING_CALENDAR_PATH . $filename . '.php';

			if ( ! file_exists( $is_filename ) ){
			    $filename = wp_normalize_path( GROUNDHOGG_BOOKING_CALENDAR_PATH . 'includes/' . $filename . '.php' );
            } else {
			    $filename = $is_filename;
            }
		}

		if ( is_readable( $filename ) ) {
			require $filename;
		}
	}

	/**
	 * Autoload.
	 *
	 * For a given class, check if it exist and load it.
	 *
	 * @since 1.6.0
	 * @access private
	 * @static
	 *
	 * @param string $class Class name.
	 */
	private static function autoload( $class ) {
		if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
			return;
		}

		$relative_class_name = preg_replace( '/^' . __NAMESPACE__ . '\\\/', '', $class );

		$final_class_name = __NAMESPACE__ . '\\' . $relative_class_name;

		if ( ! class_exists( $final_class_name ) ) {
			self::load_class( $relative_class_name );
		}
	}
}
