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

/**
 * Ecwid ION handler.
 */
class IX_ION_Handler {
	
	private $debug = false;
	
	private $secure_auth_key = null;
	
	private $curl = true;
	private $ssl = true; // required
	private $port = 443;
	private $followlocation = false;
	private $max_redirects = 0; // not used with socket
	private $timeout = 30; // in seconds
	
	private $base_url = 'app.ecwid.com';
	
	
	/**
	 * Create instance, $params must provide secure_auth_key.
	 * 
	 * @param array $params
	 */
	public function __construct( $params ) {
		$this->set_curl( true );
		$this->secure_auth_key = isset( $params['secure_auth_key'] ) ? $params['secure_auth_key'] : null; 
		$this->debug           = isset( $params['debug'] ) ? $params['debug'] == true : false;
	}
	
	/**
	 * Use cURL or not.
	 *
	 * @param boolean $curl true to use cURL if available
	 * @return true if cURL is available and will be used, otherwise false
	 */
	public function set_curl( $curl ) {
		if ( ( $curl === true ) && function_exists( 'curl_init' ) ) {
			$this->curl = true;
		} else {
			$this->curl = false;
		}
		return $this->curl;
	}
	
	public function get_curl() {
		return $this->curl;
	}

	/**
	 * Verifies the ION identified through $data (normally the $_POST of the ION).
	 * 
	 * @param array $data normally the $_POST of the ION received
	 * @return array verified stuff or false if verification fails
	 */
	public function verify( $data ) {
		$result = false;
		$owner_id       = isset( $data['owner_id'] ) ? $data['owner_id'] : null;
		$event_type     = isset( $data['event_type'] ) ? $data['event_type'] : null;
		$order_id       = isset( $data['order_id'] ) ? $data['order_id'] : null;
		$payment_status = isset( $data['payment_status'] ) ? intval( $data['payment_status'] ) : null;
		if ( $owner_id !== null && $event_type !== null && $order_id !== null && $payment_status !== null ) {
			switch ( $event_type ) {
				case 'new_order' :
				case 'order_status_change' :
					$result = $this->get_order_data( $owner_id, $order_id );
					break;
			}
		}
		return $result;
	}
	
	/**
	 * Retrieve an order.
	 * 
	 * @param int $owner_id
	 * @param int $order_id
	 * @return order data or false if failed
	 */
	public function get_order_data( $owner_id, $order_id ) {
		// https://app.ecwid.com/api/v1/STOREID/orders
		// URL parameters:
		// required : secure_auth_key
		// optional to pass order id : order
		$path = '/api/v1/' . intval( $owner_id ) . '/orders?secure_auth_key=' . $this->secure_auth_key . '&order=' . intval( $order_id );
		$response = $this->get( $this->base_url, $path );
		if ( $response !== false ) {
			return json_decode( $response );
		} else {
			return false;
		}
	}
	
	/**
	 * Retrieve the store profile.
	 *
	 * @param int $owner_id
	 * @param int $order_id
	 * @return order data or false if failed
	 */
	public function get_profile( $owner_id ) {
		// http://app.ecwid.com/api/v1/STOREID/profile
		$path = '/api/v1/' . intval( $owner_id ) . '/profile';
		$response = $this->get( $this->base_url, $path );
		if ( $response !== false ) {
			return json_decode( $response );
		} else {
			return false;
		}
	}
	
	/**
	 * POSTs the $content to $url and returns the response or false on failure.
	 * 
	 * @param $hostname where to post to 
	 * @param $path path to post to
	 * @param $content string data to post
	 * @return string response or false if failed
	 */
	public function post( $hostname, $path, $content ) {
		if ( $this->get_curl() ) {
			return $this->curl_post( $hostname, $path, $content );
		} else {
			return $this->socket_post( $hostname, $path, $content );
		}
	}
	
	/**
	 * GET request.
	 * 
	 * @param $hostname where to get from 
	 * @param $path path to get from
	 * @param $content string data to post
	 * @return string response or false if failed
	 */
	public function get( $hostname, $path ) {
		if ( $this->get_curl() ) {
			return $this->curl_get( $hostname, $path );
		} else {
			return $this->socket_get( $hostname, $path );
		}
	}
	
	/**
	 * Post by cURL.
	 * 
	 * POSTs the $content to $url and returns the response or false on failure.
	 * 
	 * @param $hostname where to post to 
	 * @param $path path to post to
	 * @param $content string data to post
	 * @return string response or false if failed
	 */
	public function curl_post( $hostname, $path, $content ) {
		$result = false;
		$url = ( $this->ssl ? 'https://' : 'http://' ) . $hostname . $path;
		$h = curl_init( $url );
		if ( $h !== false ) {
			if (
			curl_setopt( $h, CURLOPT_HEADER,         true                  ) &&
			curl_setopt( $h, CURLOPT_POST,           true                  ) &&
			curl_setopt( $h, CURLOPT_POSTFIELDS,     $content              ) &&
			curl_setopt( $h, CURLOPT_TIMEOUT,        $this->timeout        ) &&
			curl_setopt( $h, CURLOPT_RETURNTRANSFER, true                  )
			) {
				if (
				!$this->followlocation ||
				curl_setopt( $h, CURLOPT_FOLLOWLOCATION, $this->followlocation ) &&
				curl_setopt( $h, CURLOPT_MAXREDIRS,      $this->max_redirects  )
				) {
					if ( !AFF_ECWID_CURLOPT_SSL_VERIFYPEER ) {
						curl_setopt( $h, CURLOPT_SSL_VERIFYPEER, false );
					}
					$response = curl_exec( $h );
					if ( $response !== false ) {
						$http_code = curl_getinfo( $h, CURLINFO_HTTP_CODE );
						if ( intval( $http_code ) == 200 ) {
							$header_size = intval( curl_getinfo( $h, CURLINFO_HEADER_SIZE ) );
							$header = substr( $response, 0, $header_size );
							$result = substr( $response, $header_size );
						} else {
							if ( $this->debug ) {
								error_log( "HTTP code: $http_code" );
							}
						}
					}
				}
			}
			curl_close( $h );
		}
		return $result;
	}
	
	/**
	 * cURL GET
	 * 
	 * @param string $hostname
	 * @param string $path
	 * @return string response
	 */
	public function curl_get( $hostname, $path ) {
		$result = false;
		$url = ( $this->ssl ? 'https://' : 'http://' ) . $hostname . $path;
		$h = curl_init( $url );
		if ( $h !== false ) {
			if (
			curl_setopt( $h, CURLOPT_HEADER,         true                  ) &&
			curl_setopt( $h, CURLOPT_HTTPGET,        true                  ) &&
			curl_setopt( $h, CURLOPT_TIMEOUT,        $this->timeout        ) &&
			curl_setopt( $h, CURLOPT_RETURNTRANSFER, true                  )
			) {
				if (
				!$this->followlocation ||
				curl_setopt( $h, CURLOPT_FOLLOWLOCATION, $this->followlocation ) &&
				curl_setopt( $h, CURLOPT_MAXREDIRS,      $this->max_redirects  )
				) {
					if ( !AFF_ECWID_CURLOPT_SSL_VERIFYPEER ) {
						curl_setopt( $h, CURLOPT_SSL_VERIFYPEER, false );
					}
					$response = curl_exec( $h );
					if ( $response !== false ) {
						$http_code = curl_getinfo( $h, CURLINFO_HTTP_CODE );
						if ( intval( $http_code ) == 200 ) {
							$header_size = intval( curl_getinfo( $h, CURLINFO_HEADER_SIZE ) );
							$header = substr( $response, 0, $header_size );
							$result = substr( $response, $header_size );
						} else {
							if ( $this->debug ) {
								error_log( "HTTP code: $http_code" );
							}
						}
					}
				}
			}
			curl_close( $h );
		}
		return $result;
	}
	
	
	/**
	 * Post through socket.
	 * 
	 * POSTs the $content to $url and returns the response or false on failure.
	 * 
	 * @param $hostname where to post to 
	 * @param $path path to post to
	 * @param $content string data to post
	 * @return string response or false if failed
	 */
	public function socket_post( $hostname, $path, $content ) {
		$result = false;
		$headers = "POST $path HTTP/1.0\r\n";
		$headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$headers .= "Content-Length: " . strlen( $content ) . "\r\n";
		$headers .= "Connection: close\r\n";
		$headers .= "\r\n";
	
		$hostname = ( $this->ssl ? 'ssl://' : '' ) . $hostname;
		$fp = fsockopen( $hostname, $this->port, $errno, $errstr, $this->timeout );
	
		if ( $fp ) {
			fputs( $fp, $headers . $content );
			$http_ok = true;
			$http_status = "";
			$response = "";
			while ( !feof( $fp ) ) {
				$response .= fgets( $fp, 1024 );
			}
			$lines = explode( "\n", $response );
			if ( strpos( $lines[0], "200") !== false ) {
				// strip headers, see socket_get for regex
				$splits = preg_split( "/\r\n\r\n|\r\r|\n\n/", $response, 2 );
				if ( isset( $splits[1] ) ) {
					$result = $splits[1];
				}
			} else {
				if ( $this->debug ) {
					error_log( "First HTTP header: " . $lines[0] );
				}
			}
			fclose( $fp );
		} else {
			if ( $this->debug ) {
				error_log( "fsockopen error # $errno message: $errstr" );
			}
		}
		return $result;
	}
	
	/**
	 * Socket GET
	 * 
	 * @param string $hostname
	 * @param string $path
	 * @return string response
	 */
	public function socket_get( $hostname, $path ) {
		$result = false;
		$headers = "GET $path HTTP/1.0\r\n";
		$headers .= "Connection: close\r\n";
		$headers .= "\r\n";

		$hostname = ( $this->ssl ? 'ssl://' : '' ) . $hostname;
		$fp = fsockopen( $hostname, $this->port, $errno, $errstr, $this->timeout );
		if ( $fp ) {
			fputs( $fp, $headers );
			$http_ok = true;
			$http_status = "";
			$response = "";
			while ( !feof( $fp ) ) {
				$response .= fgets( $fp, 1024 );
			}
			$lines = explode( "\n", $response );
			if ( strpos( $lines[0], "200") !== false ) {
				// strip headers
				// /$^/ won't work with \r line endings
				// $splits = preg_split( "/^$/", $response, 2 );
				$splits = preg_split( "/\r\n\r\n|\r\r|\n\n/", $response, 2 );
				if ( isset( $splits[1] ) ) {
					$result = $splits[1];
				}
			} else {
				if ( $this->debug ) {
					error_log( "First HTTP header: " . $lines[0] );
				}
			}
			fclose( $fp );
		} else {
			if ( $this->debug ) {
				error_log( "fsockopen error # $errno message: $errstr" );
			}
		}
		return $result;
	}
}
