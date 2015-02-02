<?php

class Yoast_GA_Settings {

	/**
	 * The main GA options
	 *
	 * @var
	 */
	private static $options;

	/**
	 * Return the Dashboards disabled bool
	 *
	 * @return bool
	 */
	public static function dashboards_disabled() {
		return Yoast_GA_Options::instance()->option_value_to_bool( 'dashboards_disabled' );
	}

	/**
	 * Get the tracking code
	 *
	 * @return string
	 */
	public static function get_tracking_code() {
		return 'UA-1244';
	}

	/**
	 * Get bool if universal is enabled
	 *
	 * @return bool
	 */
	public static function universal_enabled() {
		return true;
	}

	/**
	 * Deprecated?
	 */
	private static function check_options() {
		Yoast_GA_Options::instance()->get_options();
	}

}