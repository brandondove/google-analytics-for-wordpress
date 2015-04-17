<?php
/**
 * @package GoogleAnalytics\AdminSettingsFieldsRegistrar
 */

/**
 * This class is for options/settings in the admin forms
 */
class Yoast_GA_Admin_Settings_Registrar {

	/**
	 * The slug of this settings page, used in the Settings API
	 *
	 * @var string
	 */
	protected $settings_api_page = 'yst_ga_settings_api';

	/**
	 * Current section of the form fields
	 *
	 * @var string
	 */
	private $current_section;

	/**
	 * @var array
	 */
	private $default_options = array(
		'tracking_code'        => array(),
		'user_roles'           => array(),
		'track_download_types' => array(),
		'track_full_url'       => array(),
		'analytics_profile'    => array(),
	);
	/**
	 * The fields array with all fields for the settings API.
	 *
	 * @var array
	 */
	private $registered_fields = array();

	/**
	 * Validator resource
	 *
	 * @var resource
	 */
	private $validator = array();

	/**
	 * Construct the new admin settings api forms
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'yst_ga_settings_errors' ) );
		add_action( 'admin_init', array( $this, 'init_default_options' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		if ( filter_input( INPUT_GET, 'settings-updated' ) ) {
			add_action( 'admin_init', array( $this, 'update_ga_tracking_from_profile' ) );
		}

		$this->validator = new Yoast_GA_Admin_Settings_Validator();
	}

	/**
	 * Show the settings errors
	 */
	public function yst_ga_settings_errors() {
		settings_errors( 'yst_ga_settings' );
	}

	/**
	 * Update the UA tracking code if we have a profile selected in the dropdown in the settings field
	 */
	public function update_ga_tracking_from_profile() {
		$tracking_code = get_option( 'yst_ga' );
		if ( $tracking_code['ga_general']['analytics_profile'] !== '' && ( $tracking_code['ga_general']['manual_ua_code'] === '0' || $tracking_code['ga_general']['manual_ua_code'] === 0 ) ) {
			$tracking_code['ga_general']['analytics_profile_code'] = $this->get_ua_code_from_profile( $tracking_code['ga_general']['analytics_profile'] );

			unset( $tracking_code['ga_general']['ga_general'] ); // cleanup old keys
			update_option( 'yst_ga', $tracking_code );
		}
	}

	/**
	 * Set the settings in this class and add them generically
	 */
	public function register_settings() {
		$this->registered_fields = array(
			'ua_code' => array(
				array(
					'name'  => 'analytics_profile',
					'label' => __( 'Google Analytics profile', 'google-analytics-for-wordpress' ),
					'type'  => 'select_profile',
					'args'  => array(
						'help'       => __( 'Select an analytics profile from your Google account to use for the tracking on this website.', 'google-analytics-for-wordpress' ),
						'attributes' => ' class="chosen"',
						'options'    => $this->default_options['analytics_profile'],
					)
				),
				array(
					'name'  => 'manual_ua_code',
					'label' => __( 'Use a manual UA code', 'google-analytics-for-wordpress' ),
					'type'  => 'checkbox',
					'args'  => array(
						'help' => __( 'You can use the manual UA code field to enter your UA code manually, instead of using the Google Authenticator.', 'google-analytics-for-wordpress' ),
					)
				),
				array(
					'name'  => 'manual_ua_code_field',
					'label' => __( 'Enter your UA code here', 'google-analytics-for-wordpress' ),
					'type'  => 'text',
					'args'  => array(
						'help' => __( 'Enter the UA code (e.g.: UA-1234567-89) here, you can find the correct UA code in your Google Analytics dashboard.', 'google-analytics-for-wordpress' ),
					)
				),
			),
			'general' => array(
				array(
					'name'  => 'track_outbound',
					'label' => __( 'Track outbound click and downloads', 'google-analytics-for-wordpress' ),
					'type'  => 'checkbox',
					'args'  => array(
						'help' => __( 'Clicks and downloads will be tracked as events, you can find these under Content &#xBB; Event Tracking in your Google Analytics reports.', 'google-analytics-for-wordpress' ),
					)
				),
				array(
					'name'  => 'anonymous_data',
					'label' => __( 'Allow tracking of anonymous data', 'google-analytics-for-wordpress' ),
					'type'  => 'checkbox',
					'args'  => array(
						'help' => __( 'By allowing us to track anonymous data we can better help you, because we know with which WordPress configurations, themes and plugins we should test. No personal data will be submitted.', 'google-analytics-for-wordpress' ),
					)
				),
				array(
					'name'  => 'anonymize_ips',
					'label' => __( 'Anonymize IPs', 'google-analytics-for-wordpress' ),
					'type'  => 'checkbox',
					'args'  => array(
						'help' => sprintf( __( 'This adds %1$s, telling Google Analytics to anonymize the information sent by the tracker objects by removing the last octet of the IP address prior to its storage.', 'google-analytics-for-wordpress' ), '<a href="https://developers.google.com/analytics/devguides/collection/gajs/methods/gaJSApi_gat?csw=1#_gat._anonymizeIp" target="_blank"><code>_anonymizeIp</code></a>' ),
					)
				),
				array(
					'name'  => 'ignore_users',
					'label' => __( 'Ignore users', 'google-analytics-for-wordpress' ),
					'type'  => 'select',
					'args'  => array(
						'help'       => __( 'Users of the role you select will be ignored, so if you select Editor, all Editors will be ignored.', 'google-analytics-for-wordpress' ),
						'attributes' => ' multiple="true" style="width: 365px;" class="chosen"',
						'options'    => $this->default_options['user_roles'],
					)
				),
				array(
					'name'  => 'dashboards_disabled',
					'label' => __( 'Disable analytics dashboard', 'google-analytics-for-wordpress' ),
					'type'  => 'checkbox',
					'args'  => array(
						'help' => __( 'This will completely disable the dashboard and stop the plugin from fetching the latest analytics data.', 'google-analytics-for-wordpress' ),
					)
				),
			),
			'universal' => array(
				array(
					'name'  => 'enable_universal',
					'label' => __( 'Enable universal', 'google-analytics-for-wordpress' ),
					'type'  => 'checkbox',
					'args'  => array(
						'help' => sprintf( __( 'First enable Universal tracking in your Google Analytics account. Please read %1$sthis guide%2$s to learn how to do that.', 'google-analytics-for-wordpress' ), '<a href="http://kb.yoast.com/article/125-universal-analytics#utm_medium=kb-link&utm_source=gawp-config&utm_campaign=wpgaplugin" target="_blank">', '</a>' ),
					)
				),
				array(
					'name'  => 'demographics',
					'label' => __( 'Enable Demographics and Interest Reports', 'google-analytics-for-wordpress' ),
					'type'  => 'checkbox',
					'args'  => array(
						'help' => sprintf( __( 'You have to enable the Demographics in Google Analytics before you can see the tracking data. We have a knowledge base article in our %1$sknowledge base%2$s about this feature.', 'google-analytics-for-wordpress' ), '<a href="http://kb.yoast.com/article/154-enable-demographics-and-interests-report-in-google-analytics/#utm_medium=kb-link&amp;utm_source=gawp-config&amp;utm_campaign=wpgaplugin" target="_blank">', '</a>' ),
					)
				),
				array(
					'name'  => 'enhanced_link_attribution',
					'label' => __( 'Enhanced Link Attribution', 'google-analytics-for-wordpress' ),
					'type'  => 'checkbox',
					'args'  => array(
						'help' => sprintf( __( 'Add %1$sEnhanced Link Attribution%2$s to your tracking code.', 'google-analytics-for-wordpress' ), '<a href="https://support.google.com/analytics/answer/2558867" target="_blank">', ' </a>' )
					)
				),
			),
			'advanced'  => array(
				array(
					'name'  => 'track_download_as',
					'label' => __( 'Track downloads as', 'google-analytics-for-wordpress' ),
					'type'  => 'select',
					'args'  => array(
						'help'       => __( 'Not recommended, as this would skew your statistics, but it does make it possible to track downloads as goals.', 'google-analytics-for-wordpress' ),
						'options'    => $this->default_options['track_download_types'],
						'attributes' => ' class="chosen"',
					)
				),
				array(
					'name'  => 'extensions_of_files',
					'label' => __( 'Extensions of files to track as downloads', 'google-analytics-for-wordpress' ),
					'type'  => 'text',
					'args'  => array(
						'help' => __( 'Please separate extensions using commas', 'google-analytics-for-wordpress' ),
					)
				),
				array(
					'name'  => 'track_full_url',
					'label' => __( 'Track full URL of outbound clicks or just the domain', 'google-analytics-for-wordpress' ),
					'type'  => 'select',
					'args'  => array(
						'help'       => __( 'How should we track your outbound clicks?', 'google-analytics-for-wordpress' ),
						'options'    => $this->default_options['track_full_url'],
						'attributes' => ' class="chosen"',
					)
				),
				array(
					'name'  => 'subdomain_tracking',
					'label' => __( 'Subdomain tracking', 'google-analytics-for-wordpress' ),
					'type'  => 'text',
					'args'  => array(
						'help' => sprintf( __( 'This allows you to set the domain that\'s set by %1$s for tracking subdomains.<br/>If empty, this will not be set.', 'google-analytics-for-wordpress' ), '<a href="https://developers.google.com/analytics/devguides/collection/gajs/methods/gaJSApiDomainDirectory#_gat.GA_Tracker_._setDomainName" target="_blank"><code>_setDomainName</code></a>' ),
					)
				),
				array(
					'name'  => 'track_internal_as_outbound',
					'label' => __( 'Set path for internal links to track as outbound links', 'google-analytics-for-wordpress' ),
					'type'  => 'text',
					'args'  => array(
						'help' => sprintf( __( 'If you want to track all internal links that begin with %1$s, enter %1$s in the box above. If you have multiple prefixes you can separate them with comma\'s: %2$s', 'google-analytics-for-wordpress' ), '<code>/out/</code>', '<code>/out/,/recommends/</code>' ),
					)
				),
				array(
					'name'  => 'track_internal_as_label',
					'label' => __( 'Label for those links', 'google-analytics-for-wordpress' ),
					'type'  => 'text',
					'args'  => array(
						'help' => __( 'The label to use for these links, this will be added to where the click came from, so if the label is "aff", the label for a click from the content of an article becomes "outbound-article-aff".', 'google-analytics-for-wordpress' ),
					)
				),
				array(
					'name'  => 'tag_links_in_rss',
					'label' => __( 'Tag links in RSS feed with campaign variables', 'google-analytics-for-wordpress' ),
					'type'  => 'checkbox',
					'args'  => array(
						'help' => __( 'Do not use this feature if you use FeedBurner, as FeedBurner can do this automatically and better than this plugin can. Check <a href="https://support.google.com/feedburner/answer/165769?hl=en&amp;ref_topic=13075" target="_blank">this help page</a> for info on how to enable this feature in FeedBurner.', 'google-analytics-for-wordpress' ),
					)
				),
				array(
					'name'  => 'allow_anchor',
					'label' => __( 'Tag links in RSS feed with campaign variables', 'google-analytics-for-wordpress' ),
					'type'  => 'checkbox',
					'args'  => array(
						'help' => sprintf( __( 'This adds a %1$s call to your tracking code, and makes RSS link tagging use a %2$s as well.', 'google-analytics-for-wordpress' ), '<a href="https://developers.google.com/analytics/devguides/collection/gajs/methods/gaJSApiCampaignTracking?csw=1#_gat.GA_Tracker_._setAllowAnchor" target="_blank"><code>_setAllowAnchor</code></a>', '<code>#</code>' ),
					)
				),
				array(
					'name'  => 'add_allow_linker',
					'label' => __( 'Add <code>_setAllowLinker</code>', 'google-analytics-for-wordpress' ),
					'type'  => 'checkbox',
					'args'  => array(
						'help' => sprintf( __( 'This adds a %1$s call to your tracking code, allowing you to use %2$s and related functions.', 'google-analytics-for-wordpress' ), '<a href="https://developers.google.com/analytics/devguides/collection/gajs/methods/gaJSApiDomainDirectory?csw=1#_gat.GA_Tracker_._setAllowLinker" target="_blank"><code>_setAllowLinker</code></a>', ' <code>_link</code>' ),
					)
				),
				array(
					'name'  => 'custom_code',
					'label' => __( 'Custom code', 'google-analytics-for-wordpress' ),
					'type'  => 'textarea',
					'args'  => array(
						'help' => sprintf( __( 'Not for the average user: this allows you to add a line of code, to be added before the %1$s call.', 'google-analytics-for-wordpress' ), '<a href="https://developers.google.com/analytics/devguides/collection/gajs/methods/gaJSApiBasicConfiguration#_gat.GA_Tracker_._trackPageview" target="_blank"><code>_trackPageview</code></a>' ),
					)
				),
			),
			'debug'     => array(
				array(
					'name'  => 'debug_mode',
					'label' => __( 'Enable debug mode', 'google-analytics-for-wordpress' ),
					'type'  => 'checkbox',
					'args'  => array(
						'help' => __( 'Not recommended, as this would skew your statistics, but it does make it possible to track downloads as goals.', 'google-analytics-for-wordpress' ),
					)
				),
			),
		);

		$this->register_sections( array( 'ua_code', 'general', 'universal', 'advanced', 'debug' ) );

		$this->validator->set_fields( $this->registered_fields );
	}

	/**
	 * Register all given sections
	 *
	 * @param array $section_keys
	 */
	private function register_sections( $section_keys = array() ) {
		if ( ! is_array( $section_keys ) ) {
			return;
		}

		foreach ( $section_keys as $number => $section_name ) {
			$add_validation = false;
			if ( $number === ( count( $section_keys ) - 1 ) ) {
				$add_validation = true;
			}

			$this->add_section( $section_name, $add_validation );
		}
	}

	/**
	 * Register a section
	 *
	 * @param string $section_name
	 * @param bool $hook_validation
	 */
	private function add_section( $section_name, $hook_validation ) {
		if( $hook_validation ) {
			register_setting( $this->settings_api_page . '_' . $section_name, 'yst_ga', array( $this->validator, 'activate_validator' ) );
		}
		else {
			register_setting( $this->settings_api_page . '_' . $section_name, 'yst_ga' );
		}

		$this->create_section( $section_name );

		foreach ( $this->registered_fields[ $section_name ] as $field ) {
			$this->add_field(
				$field['name'],
				$field['label'],
				$field['type'],
				$field['args']
			);
		}

		$this->close_section( $section_name );
	}

	/**
	 * Set the default options, for now, it is in the admin class (Needs to be hooked at admin_init)
	 */
	public function init_default_options() {
		$this->default_options = array(
			'tracking_code'        => Yoast_GA_Options::instance()->get_tracking_code(),
			'user_roles'           => $this->get_userroles(),
			'track_download_types' => $this->track_download_types(),
			'track_full_url'       => $this->get_track_full_url(),
			'analytics_profile'    => $this->get_profiles(),
		);
	}

	/**
	 * Transform the Profile ID into an helpful UA code
	 *
	 * @param int $profile_id The profile ID from Google Analytics
	 *
	 * @return null|string
	 */
	protected function get_ua_code_from_profile( $profile_id ) {
		$profiles = $this->get_profiles();
		$ua_code  = null;

		foreach ( $profiles as $account ) {
			foreach ( $account['items'] as $profile ) {
				foreach ( $profile['items'] as $subprofile ) {
					if ( isset( $subprofile['id'] ) && $subprofile['id'] === $profile_id ) {
						return $subprofile['ua_code'];
					}
				}
			}
		}

		return $ua_code;
	}

	/**
	 * Get the user roles of this WordPress blog
	 *
	 * @return array
	 */
	protected function get_userroles() {
		global $wp_roles;

		$all_roles = $wp_roles->roles;
		$roles     = array();

		/**
		 * Filter: 'editable_roles' - Allows filtering of the roles shown within the plugin (and elsewhere in WP as it's a WP filter)
		 *
		 * @api array $all_roles
		 */
		$editable_roles = apply_filters( 'editable_roles', $all_roles );

		foreach ( $editable_roles as $id => $name ) {
			$roles[] = array(
				'id'   => $id,
				'name' => translate_user_role( $name['name'] ),
			);
		}

		return $roles;
	}

	/**
	 * Get types of how we can track downloads
	 *
	 * @return array
	 */
	protected function track_download_types() {
		return array(
			0 => array( 'id' => 'event', 'name' => __( 'Event', 'google-analytics-for-wordpress' ) ),
			1 => array( 'id' => 'pageview', 'name' => __( 'Pageview', 'google-analytics-for-wordpress' ) ),
		);
	}

	/**
	 * Get options for the track full url or links setting
	 *
	 * @return array
	 */
	protected function get_track_full_url() {
		return array(
			0 => array( 'id' => 'domain', 'name' => __( 'Just the domain', 'google-analytics-for-wordpress' ) ),
			1 => array( 'id' => 'full_links', 'name' => __( 'Full links', 'google-analytics-for-wordpress' ) ),
		);
	}


	/**
	 * Create a new settings section
	 *
	 * @param string $tab The tab name that should be added
	 */
	protected function create_section( $tab ) {
		$this->current_section = $tab;

		add_settings_section(
			'yst_ga_settings_api_' . $tab,
			'',
			'',
			$this->settings_api_page . '_' . $tab
		);
	}

	/**
	 * Add a extra fields while closing the section
	 *
	 * @param string $tab
	 */
	protected function close_section( $tab ) {
		/**
		 * Filter: 'yst-ga-settings-fields-[TAB_NAME]' - Create an extra input field.
		 *
		 * @api array Array with extra fields for this tab
		 */
		$extra_fields = apply_filters( 'yst-ga-settings-fields-' . $tab, array() );

		if ( ! empty( $extra_fields ) && is_array( $extra_fields ) ) {
			foreach ( $extra_fields as $field ) {
				$this->add_field( $field['id'], $field['title'], $field['type'], $field['args'] );
			}
		}
	}

	/**
	 * Add a settings field
	 *
	 * @param string $id    ID of the field and key name in the options
	 * @param string $title Title of this field
	 * @param string $type  Type of the field e.g. text, select etc.
	 * @param array  $args  Extra arguments for the field that will be rendered
	 */
	protected function add_field( $id, $title, $type, $args ) {
		if ( ! isset( $args['key'] ) ) {
			$args['key'] = $id;
		}

		add_settings_field(
			'yst_ga_' . $id,
			$title,
			array( 'Yoast_GA_Admin_Settings_Fields', 'yst_ga_' . $type . '_field' ),
			$this->settings_api_page . '_' . $this->current_section,
			$this->settings_api_page . '_' . $this->current_section,
			$args
		);
	}

	/**
	 * Get the Google Analytics profiles which are in this google account
	 *
	 * @return array
	 */
	private function get_profiles() {
		$return = Yoast_Google_Analytics::get_instance()->get_profiles();

		return $return;
	}

}