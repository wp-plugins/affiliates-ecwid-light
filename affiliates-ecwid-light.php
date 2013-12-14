<?php
/**
 * affiliates-ecwid-light.php
 * 
 * Copyright (c) 2013 "kento" Karim Rahimpur www.itthinx.com
 * 
 * This code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 * 
 * This code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * This header and all notices must be kept intact.
 * 
 * @author Karim Rahimpur
 * @package affiliates-ecwid
 * @since affiliates-ecwid 1.0.0
 *
 * Plugin Name: Affiliates Ecwid Light
 * Plugin URI: http://www.itthinx.com/plugins/affiliates-ecwid-light
 * Description: Integrates Affiliates and Ecwid
 * Author: itthinx
 * Author URI: http://www.itthinx.com
 * Version: 1.0.2
 */
define( 'AFF_ECWID_LIGHT_PLUGIN_DOMAIN', 'affiliates-ecwid' );
define( 'AFF_ECWID_LIGHT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( !defined( 'AFF_ECWID_CURLOPT_SSL_VERIFYPEER' ) ) {
	define( 'AFF_ECWID_CURLOPT_SSL_VERIFYPEER', true );
}

/**
 * Affiliates Ecwid light integration.
 */
class Affiliates_Ecwid_Light {

	const PLUGIN_OPTIONS    = 'affiliates_ecwid';
	const NONCE             = 'aff_ecwid_admin_nonce';
	const SET_ADMIN_OPTIONS = 'set_admin_options';
	const KEY               = 'key';
	const STORE_ID          = 'store_id';
	const CURRENCY          = 'currency';
	const SECURE_AUTH_KEY   = 'secure_auth_key';
	const REFERRAL_RATE     = "referral-rate";
	const REFERRAL_RATE_DEFAULT = "0";

	private static $admin_messages = array();

	/**
	 * Prints admin notices.
	 */
	public static function admin_notices() {
		if ( !empty( self::$admin_messages ) ) {
			foreach ( self::$admin_messages as $msg ) {
				echo $msg;
			}
		}
	}

	/**
	 * Tasks performed upon plugin activation.
	 */
	public static function activate() {
		$options = get_option( self::PLUGIN_OPTIONS , null );
		if ( $options === null ) {
			$options = array();
			$options[self::KEY] = md5( rand() );
			// add (no need to autoload)
			add_option( self::PLUGIN_OPTIONS, $options, null, 'no' );
		}
	}

	/**
	 * Checks dependencies and adds appropriate actions and filters.
	 */
	public static function init() {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		if ( !class_exists( 'Affiliates_Ecwid_Integration' ) ) {
			if ( self::check_dependencies() ) {
				add_action( 'affiliates_admin_menu', array( __CLASS__, 'affiliates_admin_menu' ) );
				add_filter( 'affiliates_footer', array( __CLASS__, 'affiliates_footer' ) );
			}
		} else {
			self::$admin_messages[] =
				'<div class="error" style="padding:1em;margin:1em;border:#caa;background-color:#fcc">' .
				'<p>' .
				__( 'The <em>Affiliates Ecwid Light</em> plugin is activated but not operative.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) .
				'</p>' .
				'<p>' .
				__( 'The <em>Affiliates Ecwid</em> integration is installed, you must <strong>deactivate</strong> the <em>Affiliates Ecwid Light</em> plugin and adjust the <strong>ION Cannon endpoint URL</strong> in your Ecwid account under <strong>System Settings > API</strong> to use the new URL provided under <strong>Affiliates > Ecwid</strong>.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) .
				'</p>' .
				'</div>';
		}
	}

	/**
	 * Verifies plugin dependencies, Affiliates (or Pro/Enterprise).
	 *
	 * @param boolean $disable If true, disables the plugin if dependencies are not met. Defaults to false.
	 * @return true if dependencies are met, otherwise false.
	 */
	public static function check_dependencies( $disable = false ) {
		$result = true;
		$active_plugins = get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_sitewide_plugins = get_site_option( 'active_sitewide_plugins', array() );
			$active_sitewide_plugins = array_keys( $active_sitewide_plugins );
			$active_plugins = array_merge( $active_plugins, $active_sitewide_plugins );
		}
		$affiliates_is_active = in_array( 'affiliates/affiliates.php', $active_plugins ) || in_array( 'affiliates-pro/affiliates-pro.php', $active_plugins ) || in_array( 'affiliates-enterprise/affiliates-enterprise.php', $active_plugins );
		if ( !$affiliates_is_active ) {
			self::$admin_messages[] = "<div class='error'>" . __( '<strong>Affiliates Ecwid Light</strong> plugin requires an appropriate Affiliates plugin to be activated: <a href="http://www.wordpress.org/extend/plugins/affiliates" target="_blank">Affiliates</a>, <a href="http://www.itthinx.com/plugins/affiliates-pro" target="_blank">Affiliates Pro</a> or <a href="http://www.itthinx.com/plugins/affiliates-enterprise" target="_blank">Affiliates Enterprise</a>.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . "</div>";
		}
		if ( !$affiliates_is_active ) {
			if ( $disable ) {
				include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
				deactivate_plugins( array( __FILE__ ) );
			}
			$result = false;
		}
		return $result;
	}

	/**
	 * Affiliates submenu for the Ecwid Light integration options.
	 */
	public static function affiliates_admin_menu() {
		$page = add_submenu_page(
			'affiliates-admin',
			__( 'Affiliates Ecwid Light', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ),
			__( 'Ecwid Light', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ),
			AFFILIATES_ADMINISTER_OPTIONS,
			'affiliates-admin-ecwid-light',
			array( __CLASS__, 'affiliates_admin_ecwid' )
		);
		$pages[] = $page;
		add_action( 'admin_print_styles-' . $page, 'affiliates_admin_print_styles' );
		add_action( 'admin_print_scripts-' . $page, 'affiliates_admin_print_scripts' );
	}

	/**
	 * Affiliates Ecwid Integration : admin section.
	 */
	public static function affiliates_admin_ecwid() {
		$output = '';
		
		if ( !current_user_can( AFFILIATES_ADMINISTER_OPTIONS ) ) {
			wp_die( __( 'Access denied.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) );
		}
		
		$options = get_option( self::PLUGIN_OPTIONS , array() );
		if ( isset( $_POST['submit'] ) ) {
			if ( wp_verify_nonce( $_POST[self::NONCE], self::SET_ADMIN_OPTIONS ) ) {
				// store
				$options[self::SECURE_AUTH_KEY] = !empty( $_POST[self::SECURE_AUTH_KEY] ) ? trim( $_POST[self::SECURE_AUTH_KEY] ) : '';
				$options[self::STORE_ID] = !empty( $_POST[self::STORE_ID] ) ? trim( $_POST[self::STORE_ID] ) : '';
				// rate
				$options[self::REFERRAL_RATE]  = floatval( $_POST[self::REFERRAL_RATE] );
				if ( $options[self::REFERRAL_RATE] > 1.0 ) {
					$options[self::REFERRAL_RATE] = 1.0;
				} else if ( $options[self::REFERRAL_RATE] < 0 ) {
					$options[self::REFERRAL_RATE] = 0.0;
				}
			}
			update_option( self::PLUGIN_OPTIONS, $options );
		}
		$referral_rate = isset( $options[self::REFERRAL_RATE] ) ? $options[self::REFERRAL_RATE] : self::REFERRAL_RATE_DEFAULT;

		echo '<h2>' . __( 'Affiliates Ecwid Light', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</h2>';

		$output .= '<div style="padding:1em;margin:1em;background-color:#ffe;border:1px solid #ccc; border-radius:4px;">';
		$profile = null;
		if ( !empty( $options[self::STORE_ID] ) && !empty( $options[self::SECURE_AUTH_KEY] ) ) {
			require_once( 'class-ix-ion-handler.php' );
			$h = new IX_ION_Handler( array( 'secure_auth_key' => $options[self::SECURE_AUTH_KEY] ) );
			if ( $profile = $h->get_profile( $options[self::STORE_ID] ) ) {
				$store_name = isset( $profile->storeName ) ? $profile->storeName : null;
				if ( $store_name !== null ) {
					$output .= '<p>';
					$output .= sprintf( __( 'Your store <strong>%s</strong> has been successfully authenticated.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ), stripslashes( wp_filter_nohtml_kses( $store_name ) ) );
					$output .= '</p>';
				} else {
					$output .= '<p class="warning">' . __( 'Please check your settings, the store name could not be determined.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</p>';
				}
				$currency   = isset( $profile->currency ) ? $profile->currency : null;
				if ( $currency !== null ) {
					if ( empty( $options[self::CURRENCY] ) || ( $options[self::CURRENCY] != $currency ) ) {
						$options[self::CURRENCY] = $currency;
						update_option( self::PLUGIN_OPTIONS, $options );
					}
					$output .= '<p>';
					$output .= sprintf( __( 'Currency : %s', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ), $currency );
					$output .= '</p>';
				} else {
					$output .= '<p class="warning">' . __( 'Please check your settings, the store currency could not be determined.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</p>';
				}
			} else {
				$output .= '<p class="warning">' . __( 'Please check your settings, the store could not be authenticated.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</p>';
			}
		} else {
			$output .= '<p class="warning">';
			$output .= __( 'You still need to provide the details to authenticate your store.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN );
			$output .= '</p>';
		}
		$output .= '</div>';

		$output .= '<form action="" name="options" method="post">';
		$output .= '<div>';

		$output .= '<div style="padding:1em;margin:1em;background-color:#ffc;border:1px solid #ccc; border-radius:4px;">';

		$output .= '<h3>' . __( 'Setup', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</h3>';
		$output .= '<p class="description">' . __( 'Please provide the following information and follow the directions given below. These steps must be fulfilled in order to activate the integration with Ecwid. Click <strong>Save</strong> at the end of the page once you have provided the required data.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</p>';

		$output .= '<h4>' . __( '1. Store ID', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</h4>';
		$output .= '<p>';
		$output .= '<label style="display:block" for="' . self::STORE_ID . '">' . __( 'Store ID', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</label>';
		$output .= '<input style="width:40em" name="' . self::STORE_ID . '" type="text" value="' . ( isset( $options[self::STORE_ID] ) ? esc_attr( $options[self::STORE_ID] ) : '' ) . '" />';
		$output .= '</p>';
		$output .= '<p class="description">' . __( 'You must provide your <strong>Store ID</strong> here. You can find it in your Ecwid account under <strong>Dashboard > Account Summary</strong>.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</p>';

		$output .= '<h4>' . __( '2. Order API', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</h4>';
		$output .= '<p>';
		$output .= '<label style="display:block" for="' . self::SECURE_AUTH_KEY . '">' . __( 'Order API secrect key', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</label>';
		$output .= '<input style="width:40em" name="' . self::SECURE_AUTH_KEY . '" type="text" value="' . ( isset( $options[self::SECURE_AUTH_KEY] ) ? esc_attr( $options[self::SECURE_AUTH_KEY] ) : '' ) . '" />';
		$output .= '</p>';
		$output .= '<p class="description">' . __( 'You must provide your <strong>Order API secret key</strong> key here. You can find the key in your Ecwid account under <strong>System Settings > API</strong>.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</p>';

		$output .= '<h4>' . __( '3. Instant Order Notifications', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</h3>';
		$output .= '<p>';
		$output .= __( 'The notification URL for ION messages from Ecwid is:', AFF_ECWID_LIGHT_PLUGIN_DOMAIN );
		$output .= '</p>';
		$output .= '<p>';
		$output .= '<input type="text" style="width:100%" readonly="readonly" value="' . esc_attr( self::get_ion_url() ) . '" />';
		$output .= '</p>';
		$output .= '<p class="description">' . __( 'You must use this URL as the <strong>ION Cannon endpoint URL</strong> in your Ecwid account under <strong>System Settings > API</strong>. Make sure to copy and paste the full URL.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</p>';

		$output .= '</div>';
		
		$output .= '<div style="padding:1em;margin:1em;background-color:#dfe;border:1px solid #ccc; border-radius:4px;">';
		$output .= '<h3>' . __( 'Referral Rate', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</h3>';
		$output .= '<p>';
		$output .= '<label for="' . self::REFERRAL_RATE . '">' . __( 'Referral rate', AFF_ECWID_LIGHT_PLUGIN_DOMAIN) . '</label>';
		$output .= '&nbsp;';
		$output .= '<input name="' . self::REFERRAL_RATE . '" type="text" value="' . esc_attr( $referral_rate ) . '"/>';
		$output .= '</p>';
		$output .= '<p>';
		$output .= __( 'The referral rate determines the referral amount based on the net sale made.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN );
		$output .= '</p>';
		$output .= '<p class="description">';
		$output .= __( 'Example: Set the referral rate to <strong>0.1</strong> if you want your affiliates to get a <strong>10%</strong> commission on each sale.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN );
		$output .= '</p>';
		$output .= '</div>';

		$output .= '<div style="padding:1em;margin:1em;border:1px solid #ccc; border-radius:4px;">';
		$output .= '<p class="description">' . __( 'Please click <strong>Save</strong> for any changes to take effect.', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '</p>';
		$output .= '<p>';
		$output .= wp_nonce_field( self::SET_ADMIN_OPTIONS, self::NONCE, true, false );
		$output .= '<input type="submit" name="submit" value="' . __( 'Save', AFF_ECWID_LIGHT_PLUGIN_DOMAIN ) . '"/>';
		$output .= '</p>';
		$output .= '</div>';

		$output .= '</div>';
		$output .= '</form>';

		echo $output;

		affiliates_footer();
	}

	/**
	 * Footer notice.
	 * @param string $footer
	 */
	public static function affiliates_footer( $footer ) {
		return $footer;
	}

	/**
	 * Record a referral when a new order has been processed.
	 * 
	 * @param int $order_id the id of the order
	 * @param object $order data
	 */
	public static function ecwid_new_order( $order_id, $order ) {

		global $wpdb;

		$options = get_option( self::PLUGIN_OPTIONS , array() );

		$order = (array) $order;

		$payment_status = isset( $order['paymentStatus'] ) ? $order['paymentStatus'] : null;

		$customer_ip    = isset( $order['customerIP'] ) ? $order['customerIP'] : null;
		$subtotal_cost  = isset( $order['subtotalCost'] ) ? $order['subtotalCost'] : 0;
		$discount_cost  = isset( $order['discountCost'] ) ? $order['discountCost'] : 0;
		$order_subtotal = bcsub( $subtotal_cost, $discount_cost, AFFILIATES_REFERRAL_AMOUNT_DECIMALS );
		$referral_rate  = isset( $options[self::REFERRAL_RATE] ) ? $options[self::REFERRAL_RATE] : self::REFERRAL_RATE_DEFAULT;
		$amount         = round( floatval( $referral_rate ) * floatval( $order_subtotal ), AFFILIATES_REFERRAL_AMOUNT_DECIMALS );
		$currency       = isset( $options[self::CURRENCY] ) ? $options[self::CURRENCY] : 'USD';  

		$data = array(
			'order_id' => array(
				'title' => 'Order #',
				'domain' => AFF_ECWID_LIGHT_PLUGIN_DOMAIN,
				'value' => esc_sql( $order_id )
			),
			'order_total' => array(
				'title' => 'Total',
				'domain' =>  AFF_ECWID_LIGHT_PLUGIN_DOMAIN,
				'value' => esc_sql( $order_subtotal )
			),
			'order_currency' => array(
				'title' => 'Currency',
				'domain' =>  AFF_ECWID_LIGHT_PLUGIN_DOMAIN,
				'value' => esc_sql( $currency )
			)
		);

		$post_id = 0;
		$description = sprintf( 'Order #%s', $order_id );

		$affiliate_id = null;
		if ( !empty( $customer_ip ) ) {
			if ( $ip_int = ip2long( $customer_ip ) ) {
				if ( PHP_INT_SIZE < 8 ) {
					$ip_int = sprintf( '%u', $ip_int );
				}
				$hits_table = _affiliates_get_tablename( 'hits' );
				$hits = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $hits_table WHERE ip = %s ORDER BY datetime DESC LIMIT 1 OFFSET 0", "$ip_int" ) );
				if ( count( $hits ) > 0 ) {
					$hit = $hits[0];
					$now = time();
					$then = strtotime( $hit->datetime . " GMT" ); // time is in seconds since the Unix Epoch with respect to GMT
					$timeout_days = intval( get_option( 'aff_cookie_timeout_days', AFFILIATES_COOKIE_TIMEOUT_DAYS ) );
					if ( ( $timeout_days == 0 ) || ( ( $now - $then ) <= ( AFFILIATES_COOKIE_TIMEOUT_BASE * $timeout_days ) ) ) {
						$affiliate_id = affiliates_check_affiliate_id( $hit->affiliate_id );
					}
				}
			}
		}
		if ( !$affiliate_id ) {
			if ( get_option( 'aff_use_direct', true ) ) {
				$affiliate_id = affiliates_get_direct_id();
			}
		}
		if ( $affiliate_id ) {
			affiliates_add_referral(
				$affiliate_id,
				$post_id,
				$description,
				$data,
				$amount,
				$currency,
				null,
				'sale',
				$order_id
			);
		}
	}

	/**
	* Returns the IPN notification URL.
	* 
	* @return notification URL
	*/
	public static function get_ion_url() {
		$options = get_option( self::PLUGIN_OPTIONS , array() );
		$url = AFF_ECWID_LIGHT_PLUGIN_URL . 'ion.php?key=' . urlencode( $options[self::KEY] );
		return $url;
	}
}
Affiliates_Ecwid_Light::init();
