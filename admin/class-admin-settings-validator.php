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
	 * @var bool
	 */
	private $validator_activated = false;

	/**
	 * Amount of errors on validation
	 *
	 * @var integer
	 */
	private $errors = 0;

	/**
	 * @var string
	 */
	private $errors_namespace = 'yst_ga_settings';

	/**
	 * @var string
	 */
	private $form_namespace = 'ga_general';

	/**
	 * @var array
	 */
	private $new_settings = array();

	/**
	 * Set the fields
	 *
	 * @param $fields
	 */
	public function set_fields( $fields ) {
		$this->fields = $fields;
	}


	public function activate_validator( $new_settings = array() ) {
		$this->new_settings = $new_settings;

		if( $this->validator_activated || get_settings_errors( $this->errors_namespace ) !== array()  ){
			// Break the validation, because this validation has been already runned.
			return $this->new_settings;
		}
		$this->validator_activated = true;

		foreach ( $new_settings[ $this->form_namespace ] as $key => $value ) {
			$this->sanitize_field( $key, $value, $this->get_field( $key ) );
		}

		return $this->new_settings;
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

		if ( $this->errors === 0 && get_settings_errors( $this->errors_namespace ) === array() ) {
			add_settings_error(
				'yst_ga_settings',
				'yst_ga_settings',
				__( 'The Google Analytics settings are saved successfully.', 'google-analytics-for-wordpress' ),
				'updated'
			);
		}

		return $new_settings;
	}

	/**
	 * Add a settings error
	 *
	 * @param $text
	 */
	private function add_error( $text ) {
		add_settings_error(
			$this->errors_namespace,
			$this->errors_namespace,
			$text,
			'error'
		);
	}

	/**
	 * Get the field settings from key name
	 *
	 * @param string $key
	 *
	 * @return array
	 */
	private function get_field( $key ) {
		foreach ( $this->fields as $section => $fields ) {
			foreach ( $fields as $field_key => $field_settings ) {
				if ( $field_settings['name'] === $key ) {
					return $field_settings;
				}
			}
		}

		return array();
	}

	/**
	 * Validate a field's value on the given field name
	 *
	 * @param string $key
	 * @param string $value
	 * @param array  $field
	 *
	 * @return mixed
	 */
	private function sanitize_field( $key, $value, $field ) {
		if( !isset($field['type']) ){
			return $value;
		}

		// Scan and sanitize all fields by type
		switch( $field['type'] ) {
			case 'text':
				return $this->sanitize_text_input( $key, $value );
				break;
			case 'checkbox':
				return $this->sanitize_checkbox_input( $key, $value );
				break;
			case 'select':
				return $this->sanitize_select_input( $key, $value, $field );
				break;
		}

		return;
	}

	/**
	 * Sanitize the checkbox value
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return mixeds
	 */
	private function sanitize_checkbox_input( $key, $value ) {
		$valid_values = array( 0, 1, '0', '1' );
		if ( in_array( $value, $valid_values, true ) ) {
			return $value;
		}

		unset( $this->new_settings[$this->form_namespace][$key] );

		return null;
	}

	/**
	 * Sanitize the select value (or values, for a multiple select field)
	 *
	 * @param string $key
	 * @param mixed $value
	 * @param array $field
	 *
	 * @return mixed
	 */
	private function sanitize_select_input( $key, $value, $field ) {
		$options = array();
		$valid = 0;

		foreach ( $field['args']['options'] as $suboption ) {
			$options[] = $suboption['id'];
		}

		if( is_array( $value ) ){
			foreach( $value as $subvalue ){
				if( in_array( $subvalue, $options, true ) ) {
					$valid ++;
				}
			}
		}

		if ( in_array( $value, $options, true ) || $valid >= 1 ) {
			return $value;
		}

		$this->new_settings[$this->form_namespace][$key] = array();

		return null;
	}

	/**
	 * Sanitize the text input field
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return mixed
	 */
	private function sanitize_text_input( $key, $value ) {
		$regex = preg_match( '/[a-zA-Z-_.&?=!,\/\d\s]*/i', $value, $matches );

		if ( ! isset( $matches[0] ) || strlen( $matches[0] ) < strlen( $value ) ) {
			unset( $this->new_settings[$this->form_namespace][$key] );

			return null;
		}

		return $value;
	}

	/**
	 * Validate the manual UA code
	 *
	 * @param string $ua_code The UA code that we have to check
	 *
	 * @return bool
	 */
	private function validate_manual_ua_code( $ua_code ) {
		if ( ! preg_match( '|^UA-\d{4,}-\d+$|', $ua_code ) ) {
			add_settings_error(
				$this->errors_namespace,
				$this->errors_namespace,
				__( 'The UA code needs to follow UA-XXXXXXXX-X format.', 'google-analytics-for-wordpress' ),
				'error'
			);

			return false;
		}

		return true;
	}

	/**
	 * Validate the profile ID in the selectbox
	 *
	 * @param int $profile_id Check the profile id
	 *
	 * @return bool
	 */
	private function validate_profile_id( $profile_id ) {
		if ( ! is_numeric( $profile_id ) ) {
			add_settings_error(
				$this->errors_namespace,
				$this->errors_namespace,
				__( 'The profile ID needs to be numeric.', 'google-analytics-for-wordpress' ),
				'error'
			);

			return false;
		}

		return true;
	}

}