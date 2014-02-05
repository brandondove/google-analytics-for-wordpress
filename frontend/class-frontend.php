<?php

/**
 * Code that actually inserts stuff into pages.
 */
if ( ! class_exists( 'GA_Filter' ) ) {
	class GA_Filter {

		var $options = array();

		/**
		 * Class constructor
		 */
		function __construct() {
			$this->options = get_option( 'Yoast_Google_Analytics' );

			if ( ! is_array( $this->options ) ) {
				$this->options = get_option( 'GoogleAnalyticsPP' );
				if ( ! is_array( $this->options ) )
					return;
			}

			if ( ! isset( $this->options['uastring'] ) || $this->options['uastring'] == '' ) {
				add_action( 'wp_head', array( $this, 'not_shown_error' ) );
			}
			else {
				if ( isset( $this->options['allowanchor'] ) && $this->options['allowanchor'] ) {
					add_action( 'init', array( $this, 'utm_hashtag_redirect' ), 1 );
				}

				if ( ( isset( $this->options['trackoutbound'] ) && $this->options['trackoutbound'] ) ||
						( isset( $this->options['trackcrossdomain'] ) && $this->options['trackcrossdomain'] )
				) {
					// filters alter the existing content
					add_filter( 'the_content', array( $this, 'the_content' ), 99 );
					add_filter( 'widget_text', array( $this, 'widget_content' ), 99 );
					add_filter( 'the_excerpt', array( $this, 'the_content' ), 99 );
					add_filter( 'comment_text', array( $this, 'comment_text' ), 99 );
					add_filter( 'get_bookmarks', array( $this, 'bookmarks' ), 99 );
					add_filter( 'get_comment_author_link', array( $this, 'comment_author_link' ), 99 );
					add_filter( 'wp_nav_menu', array( $this, 'nav_menu' ), 99 );
				}

				if ( $this->options["trackcommentform"] ) {
					add_action( 'wp_insert_comment', array( $this, 'track_comment_submit' ) );
				}

				if ( isset( $this->options['trackadsense'] ) && $this->options['trackadsense'] )
					add_action( 'wp_head', array( $this, 'spool_adsense' ), 1 );

				if ( ! isset( $this->options['position'] ) )
					$this->options['position'] = 'header';

				switch ( $this->options['position'] ) {
					case 'manual':
						// No need to insert here, bail NOW.
						break;
					case 'header':
					default:
						add_action( 'wp_head', array( $this, 'spool_analytics' ), 2 );
						break;
				}

				if ( isset( $this->options['trackregistration'] ) && $this->options['trackregistration'] )
					add_action( 'login_head', array( $this, 'spool_analytics' ), 20 );

				if ( isset( $this->options['rsslinktagging'] ) && $this->options['rsslinktagging'] )
					add_filter( 'the_permalink_rss', array( $this, 'rsslinktagger' ), 99 );
			}
		}

		/**
		 * Throws error to admin user on why GA is not showing in the code.
		 */
		function not_shown_error() {
			if ( current_user_can( 'manage_options' ) )
				echo "<!-- " . __( "Google Analytics tracking code not shown because you haven't setup Google Analytics for WordPress yet.", "gawp" ) . " -->\n";
		}

		/**
		 * Determines whether or not to run tracking based on the current user.
		 *
		 * @global object $current_user Holds the userdata for the current user.
		 *
		 * @return bool
		 */
		function do_tracking() {
			global $current_user;

			get_currentuserinfo();

			if ( 0 == $current_user->ID )
				return true;

			if ( ( $current_user->user_level >= $this->options["ignore_userlevel"] ) )
				return false;
			else
				return true;
		}

		/**
		 * Redirect normal campaign tagged URLs to hashtagged URLs.
		 *
		 * If setAllowAnchor is set to true, GA ignores all links tagged "normally", so we redirect all "normally" tagged URL's
		 * to one tagged with a hash.
		 */
		function utm_hashtag_redirect() {
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				if ( strpos( $_SERVER['REQUEST_URI'], "utm_" ) !== false ) {
					$url = 'http://';
					if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] != "" ) {
						$url = 'https://';
					}
					$url .= $_SERVER['SERVER_NAME'];
					if ( strpos( $_SERVER['REQUEST_URI'], "?utm_" ) !== false ) {
						$url .= str_replace( "?utm_", "#utm_", $_SERVER['REQUEST_URI'] );
					}
					else if ( strpos( $_SERVER['REQUEST_URI'], "&utm_" ) !== false ) {
						$url .= substr_replace( $_SERVER['REQUEST_URI'], "#utm_", strpos( $_SERVER['REQUEST_URI'], "&utm_" ), 5 );
					}
					wp_redirect( $url, 301 );
					exit;
				}
			}
		}

		/**
		 * Echos a field object properly
		 *
		 * @since 5.0
		 *
		 * @link  https://developers.google.com/analytics/devguides/collection/analyticsjs/field-reference
		 *
		 * @note  this won't work with callback functions
		 *
		 * @param array $field The field object, passed around as an array for more easy management
		 */
		function display_field_object( $field ) {
			$field = (object) $field;
			echo stripslashes( json_encode( $field ) );
		}

		/**
		 * Echo a Google Analytics command
		 *
		 * @since 5.0
		 *
		 * @param string $command Whether to send, get etc.
		 * @param string $name    Name of the command
		 * @param bool   $field   Optional field to pass along with the command.
		 */
		function display_command( $command, $name, $field = false ) {
			$command = trim( $command );
			$name    = trim( $name );
			echo "ga( '${command}', '${name}'";
			if ( $field ) {
				echo ", ";
				if ( is_array( $field ) )
					$this->display_field_object( $field );
				else
					echo $field;
			}
			echo " );\n";
		}

		/**
		 * The core track page view functionality of the plugin.
		 *
		 * @global object $wp_query     Holds the current query info.
		 * @global object $current_user Holds the current user info.
		 *
		 * @since 5.0
		 */
		function track_page_view() {
			global $wp_query, $current_user;

			$field = array();

			if ( is_404() ) {
				$request_uri   = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
				$query_string  = isset( $_SERVER['QUERY_STRING'] ) ? $_SERVER['QUERY_STRING'] : '';
				$referrer      = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
				$field['page'] = '/404.html?page=' . $request_uri . $query_string . '&from=' . $referrer;
			}
			else if ( $wp_query->is_search ) {
				$pv = get_bloginfo( 'url' ) . "/?s=";
				if ( $wp_query->found_posts == 0 ) {
					$pv .= "no-results:" . rawurlencode( $wp_query->query_vars['s'] ) . "&cat=no-results'";
				}
				else if ( $wp_query->found_posts == 1 ) {
					$pv .= rawurlencode( $wp_query->query_vars['s'] ) . "&cat=1-result'";
				}
				else if ( $wp_query->found_posts > 1 && $wp_query->found_posts < 6 ) {
					$pv .= rawurlencode( $wp_query->query_vars['s'] ) . "&cat=2-5-results'";
				}
				else {
					$pv .= rawurlencode( $wp_query->query_vars['s'] ) . "&cat=plus-5-results'";
				}
				$field['page'] = $pv;
			}

			// Make sure $current_user is filled.
			get_currentuserinfo();

			if ( $current_user && $current_user->ID != 0 )
				$field['dimension1'] = 'Logged in';

			if ( ! is_home() && ( is_post_type_archive() || is_singular() ) ) {
				$post_type = get_post_type();
				if ( $post_type )
					$field['dimension2'] = $post_type;
			}

			if ( is_singular() && ! is_home() ) {
				$field['dimension3'] = get_the_author_meta( 'display_name', $wp_query->post->post_author );
				$field['dimension5'] = get_the_time( 'Y-m-d H:i' );
			}

			if ( is_single() ) {
				$cats = get_the_category();
				if ( is_array( $cats ) && isset( $cats[0] ) )
					$field['dimension4'] = $cats[0]->name;
			}

			$this->display_command( 'send', 'pageview', $field );
		}

		/*
		 * Insert the tracking code into the page
		 */
		function spool_analytics() {

			if ( current_user_can( 'manage_options' ) && $this->options['firebuglite'] && $this->options['debug'] )
				echo '<script src="https://getfirebug.com/firebug-lite.js" type="text/javascript"></script>';
			?>

			<script type="text/javascript">//<![CDATA[
				<?php echo '// Google Analytics for WordPress by Yoast v5 beta - | http://yoast.com/wordpress/google-analytics/' ;  ?>
				
						(function (i, s, o, g, r, a, m) {
							i['GoogleAnalyticsObject'] = r;
							i[r] = i[r] || function () {
								(i[r].q = i[r].q || []).push(arguments)
							}, i[r].l = 1 * new Date();
							a = s.createElement(o),
									m = s.getElementsByTagName(o)[0];
							a.async = 1;
							a.src = g;
							m.parentNode.insertBefore(a, m)
						})(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');

				<?php

				$create_field = array();
				if ( $this->options['allowlinker'] )
				$create_field[ 'allowLinker' ] = true;

				if ( isset( $this->options['domain'] ) && $this->options['domain'] != "" )
				$create_field[ 'cookieDomain' ] = $this->options['domain'];

				/**
				* Filter that allows filtering of the field passed to the create command.
				*
				* @since 5.0
				*
				* @param array $create_field
				*/
				$create_field = apply_filters( 'yoast-ga-create-field', $create_field );

				$this->display_command( 'create', $this->options["uastring"], $create_field );

				if ( $this->options['anonymizeip'] )
				$this->display_command( 'set', 'anonymizeIp', 'true' );

				/**
				* Action to run just before pageview is tracked, useful to insert extra data.
				*
				* @since 5.0
				*/
				do_action( 'yoast-ga-before-pageview' );

				$this->track_page_view();

				/**
				* Action to run just after pageview is tracked, useful for for instance ecommerce tracking.
				*
				* @since 5.0
				*/
				do_action('yoast-ga-after-pageview');

				?>
				//]]></script>
		<?php
		}

		/*
		 * Insert the AdSense parameter code into the page. This'll go into the header per Google's instructions.
		 */
		function spool_adsense() {
			if ( $this->do_tracking() && ! is_preview() ) {
				echo '<script type="text/javascript">' . "\n";
				echo "\t" . 'window.google_analytics_uacct = "' . $this->options["uastring"] . '";' . "\n";
				echo '</script>' . "\n";
			}
		}

		/**
		 * Retrieves the tracking prefix from options and returns either that or the default.
		 *
		 * @return string
		 */
		function get_tracking_prefix() {
			return ( empty( $this->options['trackprefix'] ) ) ? '/yoast-ga/' : $this->options['trackprefix'];
		}

		/**
		 * @param string $prefix
		 * @param string $target
		 * @param string $jsprefix
		 *
		 * @return string
		 */
		function get_tracking_link( $prefix, $target, $jsprefix = 'javascript:' ) {
			if ( $prefix == 'download' ) {
				$prefix = $this->get_tracking_prefix() . $prefix;
				$cmd    = "'pageview', { 'page' : '" . esc_url( $prefix . '/' . $target ) . "' } ";
			}
			else {
				$cmd = "'event','" . $prefix . "','" . esc_js( esc_url( $target ) ) . "'";
			}
			return $jsprefix . "ga('send', " . $cmd . ");";
		}

		function parse_link( $category, $matches ) {
			$origin = yoast_ga_get_domain( $_SERVER["HTTP_HOST"] );

			// Break out immediately if the link is not an http or https link.
			if ( strpos( $matches[2], "http" ) !== 0 ) {
				$target = false;
			}
			else if ( ( strpos( $matches[2], "mailto" ) === 0 ) ) {
				$target = 'email';
			}
			else {
				$target = yoast_ga_get_domain( $matches[3] );
			}
			$trackBit     = "";
			$extension    = substr( strrchr( $matches[3], '.' ), 1 );
			$dlextensions = explode( ",", str_replace( '.', '', $this->options['dlextensions'] ) );
			if ( $target ) {
				if ( $target == 'email' ) {
					$trackBit = $this->get_tracking_link( 'mailto', str_replace( 'mailto:', '', $matches[3] ), '' );
				}
				else if ( in_array( $extension, $dlextensions ) ) {
					$trackBit = $this->get_tracking_link( 'download', $matches[3], '' );
				}
				else if ( $target["domain"] != $origin["domain"] ) {
					$crossdomains = array();
					if ( isset( $this->options['othercrossdomains'] ) && ! empty( $this->options['othercrossdomains'] ) )
						$crossdomains = explode( ',', str_replace( ' ', '', $this->options['othercrossdomains'] ) );

					if ( isset( $this->options['trackcrossdomain'] ) && $this->options['trackcrossdomain'] && in_array( $target["host"], $crossdomains ) ) {
						$trackBit = '_gaq.push([\'_link\', \'' . $matches[2] . '//' . $matches[3] . '\']); return false;"';
					}
					else if ( $this->options['trackoutbound'] && in_array( $this->options['domainorurl'], array( 'domain', 'url' ) ) ) {
						$url      = $this->options['domainorurl'] == 'domain' ? $target["host"] : $matches[3];
						$trackBit = $this->get_tracking_link( $category, $url, '' );
					}
				}
				else if ( $target["domain"] == $origin["domain"] && isset( $this->options['internallink'] ) && $this->options['internallink'] != '' ) {
					$url         = preg_replace( '|' . $origin["host"] . '|', '', $matches[3] );
					$extintlinks = explode( ',', $this->options['internallink'] );
					foreach ( $extintlinks as $link ) {
						if ( preg_match( '|^' . trim( $link ) . '|', $url, $match ) ) {
							$label = $this->options['internallinklabel'];
							if ( $label == '' )
								$label = 'int';
							$trackBit = $this->get_tracking_link( $category . '-' . $label, $url, '' );
						}
					}
				}
			}
			if ( $trackBit != "" ) {
				if ( preg_match( '/onclick=[\'\"](.*?)[\'\"]/i', $matches[4] ) > 0 ) {
					// Check for manually tagged outbound clicks, and replace them with the tracking of choice.
					if ( preg_match( '/.*_track(Pageview|Event).*/i', $matches[4] ) > 0 ) {
						$matches[4] = preg_replace( '/onclick=[\'\"](javascript:)?(.*;)?[a-zA-Z0-9]+\._track(Pageview|Event)\([^\)]+\)(;)?(.*)?[\'\"]/i', 'onclick="javascript:' . $trackBit . '$2$5"', $matches[4] );
					}
					else {
						$matches[4] = preg_replace( '/onclick=[\'\"](javascript:)?(.*?)[\'\"]/i', 'onclick="javascript:' . $trackBit . '$2"', $matches[4] );
					}
				}
				else {
					$matches[4] = 'onclick="javascript:' . $trackBit . '"' . $matches[4];
				}
			}
			return '<a ' . $matches[1] . 'href="' . $matches[2] . '//' . $matches[3] . '"' . ' ' . $matches[4] . '>' . $matches[5] . '</a>';
		}

		function parse_article_link( $matches ) {
			return $this->parse_link( 'outbound-article', $matches );
		}

		function parse_comment_link( $matches ) {
			return $this->parse_link( 'outbound-comment', $matches );
		}

		function parse_widget_link( $matches ) {
			return $this->parse_link( 'outbound-widget', $matches );
		}

		function parse_nav_menu( $matches ) {
			return $this->parse_link( 'outbound-menu', $matches );
		}

		function widget_content( $text ) {
			if ( ! $this->do_tracking() )
				return $text;
			static $anchorPattern = '/<a (.*?)href=[\'\"](.*?)\/\/([^\'\"]+?)[\'\"](.*?)>(.*?)<\/a>/i';
			$text = preg_replace_callback( $anchorPattern, array( $this, 'parse_widget_link' ), $text );
			return $text;
		}

		function the_content( $text ) {
			if ( ! $this->do_tracking() )
				return $text;

			if ( ! is_feed() ) {
				static $anchorPattern = '/<a (.*?)href=[\'\"](.*?)\/\/([^\'\"]+?)[\'\"](.*?)>(.*?)<\/a>/i';
				$text = preg_replace_callback( $anchorPattern, array( $this, 'parse_article_link' ), $text );
			}
			return $text;
		}

		function nav_menu( $text ) {
			if ( ! $this->do_tracking() )
				return $text;

			if ( ! is_feed() ) {
				static $anchorPattern = '/<a (.*?)href=[\'\"](.*?)\/\/([^\'\"]+?)[\'\"](.*?)>(.*?)<\/a>/i';
				$text = preg_replace_callback( $anchorPattern, array( $this, 'parse_nav_menu' ), $text );
			}
			return $text;
		}

		function comment_text( $text ) {
			if ( ! $this->do_tracking() )
				return $text;

			if ( ! is_feed() ) {
				static $anchorPattern = '/<a (.*?)href="(.*?)\/\/(.*?)"(.*?)>(.*?)<\/a>/i';
				$text = preg_replace_callback( $anchorPattern, array( $this, 'parse_comment_link' ), $text );
			}
			return $text;
		}

		function comment_author_link( $text ) {
			if ( ! $this->do_tracking() )
				return $text;

			static $anchorPattern = '/(.*\s+.*?href\s*=\s*)["\'](.*?)["\'](.*)/';
			preg_match( $anchorPattern, $text, $matches );
			if ( ! isset( $matches[2] ) || $matches[2] == "" ) return $text;

			$trackBit = '';
			$target   = yoast_ga_get_domain( $matches[2] );
			$origin   = yoast_ga_get_domain( $_SERVER["HTTP_HOST"] );
			if ( $target["domain"] != $origin["domain"] ) {
				if ( isset( $this->options['domainorurl'] ) && $this->options['domainorurl'] == "domain" )
					$url = $target["host"];
				else
					$url = $matches[2];
				$trackBit = 'onclick="' . $this->get_tracking_link( 'outbound-commentauthor', $url ) . '"';
			}
			return $matches[1] . "\"" . $matches[2] . "\" " . $trackBit . " " . $matches[3];
		}

		function bookmarks( $bookmarks ) {
			if ( ! $this->do_tracking() )
				return $bookmarks;

			$i = 0;
			while ( $i < count( $bookmarks ) ) {
				$target     = yoast_ga_get_domain( $bookmarks[$i]->link_url );
				$sitedomain = yoast_ga_get_domain( get_bloginfo( 'url' ) );
				if ( $target['host'] == $sitedomain['host'] ) {
					$i ++;
					continue;
				}
				if ( isset( $this->options['domainorurl'] ) && $this->options['domainorurl'] == "domain" )
					$url = $target["host"];
				else
					$url = $bookmarks[$i]->link_url;
				$trackBit = '" onclick="' . $this->get_tracking_link( 'outbound-blogroll', $url );
				$bookmarks[$i]->link_target .= $trackBit;
				$i ++;
			}
			return $bookmarks;
		}

		function rsslinktagger( $guid ) {
			global $post;
			if ( is_feed() ) {
				if ( $this->options['allowanchor'] ) {
					$delimiter = '#';
				}
				else {
					$delimiter = '?';
					if ( strpos( $guid, $delimiter ) > 0 )
						$delimiter = '&amp;';
				}
				return $guid . $delimiter . 'utm_source=rss&amp;utm_medium=rss&amp;utm_campaign=' . urlencode( $post->post_name );
			}
			return $guid;
		}

		function track_comment_submit() {
			// Needs to be replaced by a measurement protocol implementation
			// https://developers.google.com/analytics/devguides/collection/protocol/v1/devguide
			//
			// For data consistency: event named was: ['_trackEvent', 'comment', 'submit']
		}

	} // class GA_Filter
} // endif

$yoast_ga = new GA_Filter();

function yoast_analytics() {
	global $yoast_ga;
	$options = get_option( 'Yoast_Google_Analytics' );
	if ( $options['position'] == 'manual' )
		$yoast_ga->spool_analytics();
	else
		echo '<!-- ' . __( 'Please set Google Analytics position to "manual" in the settings, or remove this call to yoast_analytics();', 'gawp' ) . ' -->';
}

