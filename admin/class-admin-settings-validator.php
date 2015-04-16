<?php
/**
 * @package GoogleAnalytics\AdminSettingsFieldsValidator
 */

/**
 * This class is for options/settings in the admin forms
 */
class Yoast_GA_Admin_Settings_Validator {

	/**
	 * @var array
	 */
	private $fields = array();

	/**
	 * On construct, get the fields from the registrar to verfiy them.
	 *
	 * @param array $fields
	 */
	public function __construct( $fields ) {
		$this->fields = $fields;
	}

	/**
	 * Validate the UA code options
	 *
	 * @param array $new_settings
	 *
	 * @return array
	 */
	public function validate_options_ua_code( $new_settings ) {
		foreach ( $new_settings['ga_general'] as $key => $value ) {
			switch ( $key ) {
				case 'manual_ua_code':
					if ( $new_settings['ga_general']['manual_ua_code'] === '1' ) {
						$new_settings['ga_general']['manual_ua_code_field'] = trim( $new_settings['ga_general']['manual_ua_code_field'] );
						$new_settings['ga_general']['manual_ua_code_field'] = str_replace( '–', '-', $new_settings['ga_general']['manual_ua_code_field'] );

						if ( ! $this->validate_manual_ua_code( $new_settings['ga_general']['manual_ua_code_field'] ) ) {
							unset( $new_settings['ga_general']['manual_ua_code_field'] );

							$this->errors ++;
						}
					}
					break;
				case 'analytics_profile':
					if ( ! empty( $new_settings['ga_general']['analytics_profile'] ) ) {
						$new_settings['ga_general']['analytics_profile'] = trim( $new_settings['ga_general']['analytics_profile'] );

						if ( ! $this->validate_profile_id( $new_settings['ga_general']['analytics_profile'] ) ) {
							unset( $new_settings['ga_general']['analytics_profile'] );

							$this->errors ++;
						}
					}
					break;
			}
		}

		if ( ! isset( $new_settings['ga_general']['ignore_users'] ) ) {
			$new_settings['ga_general']['ignore_users'] = array();
		}

		if ( $this->errors === 0 && get_settings_errors( 'yst_ga_settings' ) === array() ) {
			add_settings_error(
				'yst_ga_settings',
				'yst_ga_settings',
				__( 'The Google Analytics settings are saved successfully.', 'google-analytics-for-wordpress' ),
				'updated'
			);
		}

		return $new_settings;
	}

}