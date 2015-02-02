<?php

class Yoast_GA_Upgrade {

	public function init_upgrade() {
		$methods = get_class_methods( 'Yoast_GA_Upgrade' );

		if ( is_array( $methods ) && count( $methods ) >= 1 ) {
			foreach( $methods as $function ){
				$this->$function();
			}
		}
	}

	/**
	 * Upgrade from 5.0.0 to 5.0.1
	 */
	private function from_500_to_501() {
		// 5.0.0 to 5.0.1 fix of ignore users array
		if ( ! isset( $this->options['version'] ) || version_compare( $this->options['version'], '5.0.1', '<' ) ) {
			if ( isset( $this->options['ignore_users'] ) && ! is_array( $this->options['ignore_users'] ) ) {
				$this->options['ignore_users'] = (array) $this->options['ignore_users'];
			}
		}
	}

	/**
	 * Upgrade from 5.1.2+
	 */
	private function from_512_plus() {
		// 5.1.2+ Remove firebug_lite from options, if set
		if ( ! isset ( $this->options['version'] ) || version_compare( $this->options['version'], '5.1.2', '<' ) ) {
			if ( isset( $this->options['firebug_lite'] ) ) {
				unset( $this->options['firebug_lite'] );
			}
		}
	}

	/**
	 * Upgrade from 5.2.8+
	 */
	private function from_528_plus() {
		// 5.2.8+ Add disabled dashboards option
		if ( ! isset ( $this->options['dashboards_disabled'] ) || version_compare( $this->options['version'], '5.2.8', '>' ) ) {
			$this->options['dashboards_disabled'] = 0;
		}
	}

}