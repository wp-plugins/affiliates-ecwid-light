<?php
/**
 * Copyright (c) 2012,2013 "kento" Karim Rahimpur www.itthinx.com
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
 */

if ( !empty( $_POST['owner_id'] ) && !empty( $_POST['event_type'] ) && !empty( $_GET['key'] ) ) {

	// bootstrap WordPress
	if ( !defined( 'ABSPATH' ) ) {
		$wp_load = 'wp-load.php';
		$max_depth = 100; // prevent death by depth
		while ( !file_exists( $wp_load ) && ( $max_depth > 0 ) ) {
			$wp_load = '../' . $wp_load;
			$max_depth--;
		}
		if ( file_exists( $wp_load ) ) {
			require_once $wp_load;
		}
	}

	if ( defined( 'ABSPATH' ) ) {
		$active_plugins = get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_sitewide_plugins = get_site_option( 'active_sitewide_plugins', array() );
			$active_sitewide_plugins = array_keys( $active_sitewide_plugins );
			$active_plugins = array_merge( $active_plugins, $active_sitewide_plugins );
		}
		if ( !in_array( 'affiliates-ecwid/affiliates-ecwid.php', $active_plugins ) ) {
			$options         = get_option( Affiliates_Ecwid_Light::PLUGIN_OPTIONS , array() );
			$secure_auth_key = isset( $options[Affiliates_Ecwid_Light::SECURE_AUTH_KEY] ) ? $options[Affiliates_Ecwid_Light::SECURE_AUTH_KEY] : null;
			$key             = isset( $options[Affiliates_Ecwid_Light::KEY] ) ? $options[Affiliates_Ecwid_Light::KEY] : null;
			if ( $secure_auth_key !== null && $key !== null ) {
				if ( $_GET['key'] === $key ) {
					require_once( 'class-ix-ion-handler.php' );
					$h = new IX_ION_Handler( array( 'secure_auth_key' => $secure_auth_key ) );
					if ( $data = $h->verify( $_POST ) ) {
						if ( isset( $data->orders ) ) {
							if ( $order = array_pop( $data->orders ) ) {
								$order_id = isset( $order->number ) ? $order->number : null;
								if ( $order_id !== null ) {
									switch( $_POST['event_type'] ) {
										case 'new_order' :
											Affiliates_Ecwid_Light::ecwid_new_order( $order_id, $order );
											break;
									}
								}
							}
						}
					}
				}
			}
		}
	}

}