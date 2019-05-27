<?php

if ( ! class_exists( 'Jetpack_Contact_Info_Widget' ) ) {

	//register Contact_Info_Widget widget
	function jetpack_contact_info_widget_init() {
		register_widget( 'Jetpack_Contact_Info_Widget' );
	}

	add_action( 'widgets_init', 'jetpack_contact_info_widget_init' );

	/**
	 * Makes a custom Widget for displaying Restaurant Location/Map, Hours, and Contact Info available.
	 *
	 * @package WordPress
	 */
	class Jetpack_Contact_Info_Widget extends WP_Widget {

		/**
		 * Constructor
		 */
		function __construct() {
			$widget_ops = array(
				'classname'                   => 'widget_contact_info',
				'description'                 => __( 'Display a map with your location, hours, and contact information.', 'jetpack' ),
				'customize_selective_refresh' => true,
			);
			parent::__construct(
				'widget_contact_info',
				/** This filter is documented in modules/widgets/facebook-likebox.php */
				apply_filters( 'jetpack_widget_name', __( 'Contact Info & Map', 'jetpack' ) ),
				$widget_ops
			);
			$this->alt_option_name = 'widget_contact_info';

			if ( is_customize_preview() ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			}
		}

		/**
		 * Enqueue scripts and styles.
		 */
		public function enqueue_scripts() {
			wp_enqueue_style( 'contact-info-map-css', plugins_url( 'contact-info/contact-info-map.css', __FILE__ ), null, 20160623 );
		}


		/**
		 * Return an associative array of default values
		 *
		 * These values are used in new widgets.
		 *
		 * @return array Array of default values for the Widget's options
		 */
		public function defaults() {
			return array(
				'title'   => __( 'Hours & Info', 'jetpack' ),
				'address' => __( "3999 Mission Boulevard,\nSan Diego CA 92109", 'jetpack' ),
				'phone'   => _x( '1-202-555-1212', 'Example of a phone number', 'jetpack' ),
				'hours'   => __( "Lunch: 11am - 2pm \nDinner: M-Th 5pm - 11pm, Fri-Sat:5pm - 1am", 'jetpack' ),
				'email'   => null,
				'showmap' => 0,
				'apikey'  => null,
				'goodmap' => null,
			);
		}

		/**
		 * Outputs the HTML for this widget.
		 *
		 * @param array $args     An array of standard parameters for widgets in this theme
		 * @param array $instance An array of settings for this widget instance
		 *
		 * @return void Echoes it's output
		 **/
		function widget( $args, $instance ) {
			$instance = wp_parse_args( $instance, $this->defaults() );

			echo $args['before_widget'];

			if ( '' != $instance['title'] ) {
				echo $args['before_title'] . $instance['title'] . $args['after_title'];
			}

			/**
			 * Fires at the beginning of the Contact Info widget, after the title.
			 *
			 * @module widgets
			 *
			 * @since 3.9.2
			 */
			do_action( 'jetpack_contact_info_widget_start' );

			echo '<div itemscope itemtype="http://schema.org/LocalBusiness">';

			if ( '' != $instance['address'] ) {

				$showmap = $instance['showmap'];
				$goodmap = isset( $instance['goodmap'] ) ? $instance['goodmap'] : $this->has_good_map( $instance );

				if ( $showmap && $goodmap ) {
					/**
					 * Set a Google Maps API Key.
					 *
					 * @since 4.1.0
					 *
					 * @param string $api_key Google Maps API Key
					 */
					$api_key = apply_filters( 'jetpack_google_maps_api_key', $instance['apikey'] );
					echo $this->build_map( $instance['address'], $api_key );
				}

				$map_link = $this->build_map_link( $instance['address'] );

				echo '<div class="confit-address" itemscope itemtype="http://schema.org/PostalAddress" itemprop="address"><a href="' . esc_url( $map_link ) . '" target="_blank">' . str_replace( "\n", '<br/>', esc_html( $instance['address'] ) ) . '</a></div>';
			}

			if ( '' != $instance['phone'] ) {
				if ( wp_is_mobile() ) {
					echo '<div class="confit-phone"><span itemprop="telephone"><a href="' . esc_url( 'tel:' . $instance['phone'] ) . '">' . esc_html( $instance['phone'] ) . '</a></span></div>';
				} else {
					echo '<div class="confit-phone"><span itemprop="telephone">' . esc_html( $instance['phone'] ) . '</span></div>';
				}
			}

			if ( is_email( trim( $instance['email'] ) ) ) {
				printf(
					'<div class="confit-email"><a href="mailto:%1$s">%1$s</a></div>',
					esc_html( $instance['email'] )
				);
			}

			if ( '' != $instance['hours'] ) {
				echo '<div class="confit-hours" itemprop="openingHours">' . str_replace( "\n", '<br/>', esc_html( $instance['hours'] ) ) . '</div>';
			}

			echo '</div>';

			/**
			 * Fires at the end of Contact Info widget.
			 *
			 * @module widgets
			 *
			 * @since 3.9.2
			 */
			do_action( 'jetpack_contact_info_widget_end' );

			echo $args['after_widget'];

			/** This action is documented in modules/widgets/gravatar-profile.php */
			do_action( 'jetpack_stats_extra', 'widget_view', 'contact_info' );
		}


		/**
		 * Deals with the settings when they are saved by the admin. Here is
		 * where any validation should be dealt with.
		 *
		 * @param array $new_instance New configuration values
		 * @param array $old_instance Old configuration values
		 *
		 * @return array
		 */
		function update( $new_instance, $old_instance ) {

			$instance            = array();
			$instance['title']   = wp_kses( $new_instance['title'], array() );
			$instance['address'] = wp_kses( $new_instance['address'], array() );
			$instance['phone']   = wp_kses( $new_instance['phone'], array() );
			$instance['email']   = wp_kses( $new_instance['email'], array() );
			$instance['hours']   = wp_kses( $new_instance['hours'], array() );
			$instance['apikey']  = wp_kses( isset( $new_instance['apikey'] ) ? $new_instance['apikey'] : $old_instance['apikey'], array() );

			if ( ! isset( $new_instance['showmap'] ) ) {
				$instance['showmap'] = 0;
			} else {
				$instance['showmap'] = intval( $new_instance['showmap'] );
			}

			$update_goodmap = false;
			if (
				! isset( $instance['goodmap'] ) ||
				! isset( $old_instance['address'] ) ||
				$this->urlencode_address( $old_instance['address'] ) != $this->urlencode_address( $new_instance['address'] ) ||
				! isset( $old_instance['apikey'] ) ||
				$old_instance['apikey'] != $new_instance['apikey']
			) {
				$update_goodmap = true;
			}

			if ( empty( $instance['address'] ) || $instance['showmap'] === 0) {
					$update_goodmap = false;
					$instance['goodmap'] = false;
			}

			if ( $update_goodmap ) {
				$instance['goodmap'] = $this->has_good_map( $instance );
			}

			return $instance;
		}


		/**
		 * Displays the form for this widget on the Widgets page of the WP Admin area.
		 *
		 * @param array $instance Instance configuration.
		 *
		 * @return void
		 */
		function form( $instance ) {
			$instance = wp_parse_args( $instance, $this->defaults() );
			/** This filter is documented in modules/widgets/contact-info.php */
			$apikey = apply_filters( 'jetpack_google_maps_api_key', $instance['apikey'] );

			wp_enqueue_script(
				'contact-info-admin',
				Jetpack::get_file_url_for_environment(
					'_inc/build/widgets/contact-info/contact-info-admin.min.js',
					'modules/widgets/contact-info/contact-info-admin.js'
				),
				array( 'jquery' ),
				20160727
			);

			?>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'jetpack' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'address' ) ); ?>"><?php esc_html_e( 'Address:', 'jetpack' ); ?></label>
				<textarea class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'address' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'address' ) ); ?>"><?php echo esc_textarea( $instance['address'] ); ?></textarea>

				<input class="jp-contact-info-showmap" id="<?php echo esc_attr( $this->get_field_id( 'showmap' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'showmap' ) ); ?>" value="1" type="checkbox" <?php checked( $instance['showmap'], 1 ); ?> />
				<label for="<?php echo esc_attr( $this->get_field_id( 'showmap' ) ); ?>"><?php esc_html_e( 'Show map', 'jetpack' ); ?></label>
			</p>

			<p class="jp-contact-info-admin-map" style="<?php echo $instance['showmap'] ? '' : 'display: none;'; ?>">
				<label for="<?php echo esc_attr( $this->get_field_id( 'apikey' ) ); ?>">
					<?php _e( 'Google Maps API Key', 'jetpack' ); ?>
					<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'apikey' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'apikey' ) ); ?>" type="text" value="<?php echo esc_attr( $apikey ); ?>" />
					<br />
					<small><?php printf( wp_kses( __( 'Google now requires an API key to use their maps on your site. <a href="%s">See our documentation</a> for instructions on acquiring a key.', 'jetpack' ), array( 'a' => array( 'href' => true ) ) ), 'https://jetpack.com/support/extra-sidebar-widgets/contact-info-widget/' ); ?></small>
				</label>
			</p>

			<?php
			if ( ! is_customize_preview() ) {
				?>
				<p class="jp-contact-info-admin-map" style="<?php echo $instance['showmap'] ? '' : 'display: none;'; ?>">
					<?php echo $this->build_map( $instance['address'], $apikey ); ?>
				</p>
				<?php
			}
			?>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'phone' ) ); ?>"><?php esc_html_e( 'Phone:', 'jetpack' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'phone' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'phone' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['phone'] ); ?>" />
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'email' ) ); ?>"><?php esc_html_e( 'Email Address:', 'jetpack' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'email' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'email' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['email'] ); ?>" />
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'hours' ) ); ?>"><?php esc_html_e( 'Hours:', 'jetpack' ); ?></label>
				<textarea class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'hours' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'hours' ) ); ?>"><?php echo esc_textarea( $instance['hours'] ); ?></textarea>
			</p>

			<?php
		}


		/**
		 * Generate a Google Maps link for the supplied address.
		 *
		 * @param string $address Address to link to.
		 *
		 * @return string
		 */
		function build_map_link( $address ) {
			// Google map urls have lots of available params but zoom (z) and query (q) are enough.
			return 'https://maps.google.com/maps?z=16&q=' . $this->urlencode_address( $address );
		}


		/**
		 * Builds map display HTML code from the supplied address.
		 *
		 * @param string $address Address.
		 * @param string $api_key API Key.
		 *
		 * @return string HTML of the map.
		 */
		function build_map( $address, $api_key = null ) {
			$this->enqueue_scripts();
			$src = add_query_arg( 'q', rawurlencode( $address ), 'https://www.google.com/maps/embed/v1/place' );
			if ( ! empty( $api_key ) ) {
				$src = add_query_arg( 'key', $api_key, $src );
			}

			$height = 216;

			$iframe_attributes = sprintf(
				' height="%d" frameborder="0" src="%s" class="contact-map"',
				esc_attr( $height ),
				esc_url( $src )
			);

			$iframe_html = sprintf( '<iframe width="600" %s></iframe>', $iframe_attributes );

			if (
				! class_exists( 'Jetpack_AMP_Support' )
				|| ! Jetpack_AMP_Support::is_amp_request()
			) {
				return $iframe_html;
			}

			$amp_iframe_html = sprintf( '<amp-iframe layout="fixed-height" width="auto" sandbox="allow-scripts allow-same-origin" %s>', $iframe_attributes );

			// Add placeholder to avoid AMP error: <amp-iframe> elements must be positioned outside the first 75% of the viewport or 600px from the top (whichever is smaller).
			$amp_iframe_html .= sprintf( '<span placeholder>%s</span>', esc_html__( 'Loading map&hellip;', 'jetpack' ) );

			// Add original iframe as fallback in case JavaScript is disabled.
			$amp_iframe_html .= sprintf( '<noscript>%s</noscript>', $iframe_html );

			$amp_iframe_html .= '</amp-iframe>';
			return $amp_iframe_html;
		}

		/**
		 * Encode an URL
		 *
		 * @param string $address The URL to encode
		 *
		 * @return string The encoded URL
		 */
		function urlencode_address( $address ) {

			$address = strtolower( $address );
			$address = preg_replace( '/\s+/', ' ', trim( $address ) ); // Get rid of any unwanted whitespace
			$address = str_ireplace( ' ', '+', $address ); // Use + not %20
			return urlencode( $address );
		}

		/**
		 * Check if the instance has a valid Map location.
		 *
		 * @param array $instance Widget instance configuration.
		 *
		 * @return bool Whether or not there is a valid map.
		 */
		function has_good_map( $instance ) {
			/** This filter is documented in modules/widgets/contact-info.php */
			$api_key = apply_filters( 'jetpack_google_maps_api_key', $instance['apikey'] );
			if ( ! empty( $api_key ) ) {
				$path = add_query_arg( 'q', rawurlencode( $instance['address'] ), 'https://www.google.com/maps/embed/v1/place' );
				$path = add_query_arg( 'key', $api_key, $path );
				$response_code = wp_remote_retrieve_response_code( wp_remote_get( esc_url( $path, null, null ) ) );

				return 200 === $response_code;
			}

			return false;
		}

	}

}
