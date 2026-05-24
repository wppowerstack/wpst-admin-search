<?php
/**
 * Security Class
 * Handles security hardening and validation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPPowerStack_Security {

	/**
	 * Singleton instance
	 * @var WPPowerStack_Security|null
	 */
	private static $instance = null;

	/**
	 * Rate limiting storage
	 * @var array
	 */
	private static $rate_limits = array();

	/**
	 * Get singleton instance
	 * @return WPPowerStack_Security
	 */
	public static function wppowerstack_get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'wppowerstack_init_security' ) );
	}

	/**
	 * Initialize security measures
	 */
	public function wppowerstack_init_security() {
		// Add security headers
		add_action( 'admin_head', array( $this, 'wppowerstack_add_security_headers' ) );
		
		// Cleanup old rate limit data
		add_action( 'wp_scheduled_delete', array( $this, 'wppowerstack_cleanup_rate_limits' ) );
	}

	/**
	 * Enhanced nonce verification
	 * @param string $nonce
	 * @param string $action
	 * @return bool
	 */
	public static function wppowerstack_verify_nonce( $nonce, $action = 'wppowerstack_command_bar_nonce' ) {
		if ( ! $nonce ) {
			return false;
		}

		// Verify nonce
		$valid = wp_verify_nonce( $nonce, $action );
		
		if ( ! $valid ) {
			// Log failed nonce attempt
			self::wppowerstack_log_security_event( 'failed_nonce', array(
				'nonce_length' => strlen( $nonce ),
				'action' => $action,
				'user_ip' => self::wppowerstack_get_user_ip(),
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			) );
		}

		return $valid;
	}

	/**
	 * Enhanced capability verification
	 * @param string $capability
	 * @param int $user_id
	 * @return bool
	 */
	public static function wppowerstack_verify_capability( $capability, $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		// Check user exists and is not suspended
		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( $user->user_status, array( 0 ), true ) ) {
			return false;
		}

		// Verify capability
		$has_capability = user_can( $user, $capability );
		
		if ( ! $has_capability ) {
			// Log failed capability attempt
			self::wppowerstack_log_security_event( 'failed_capability', array(
				'capability' => $capability,
				'user_id' => $user_id,
				'user_ip' => self::wppowerstack_get_user_ip(),
			) );
		}

		return $has_capability;
	}

	/**
	 * Rate limiting for API requests
	 * @param string $identifier
	 * @param int $max_requests
	 * @param int $time_window
	 * @return bool|WP_Error
	 */
	public static function wppowerstack_rate_limit( $identifier, $max_requests = 30, $time_window = 60 ) {
		$current_time = time();
		$window_start = $current_time - $time_window;
		
		// Clean old requests
		if ( ! isset( self::$rate_limits[$identifier] ) ) {
			self::$rate_limits[$identifier] = array();
		}
		
		self::$rate_limits[$identifier] = array_filter(
			self::$rate_limits[$identifier],
			function( $timestamp ) use ( $window_start ) {
				return $timestamp > $window_start;
			}
		);
		
		// Check if limit exceeded
		if ( count( self::$rate_limits[$identifier] ) >= $max_requests ) {
			self::wppowerstack_log_security_event( 'rate_limit_exceeded', array(
				'identifier' => $identifier,
				'requests' => count( self::$rate_limits[$identifier] ),
				'limit' => $max_requests,
				'window' => $time_window,
			) );
			
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Rate limit exceeded. Please try again later.', 'wpst-admin-search-bar-quick-actions-navigation' ),
				array( 'status' => 429 )
			);
		}
		
		// Add current request
		self::$rate_limits[$identifier][] = $current_time;
		
		return true;
	}

	/**
	 * Input sanitization with enhanced validation
	 * @param mixed $input
	 * @param string $type
	 * @return mixed
	 */
	public static function wppowerstack_sanitize_input( $input, $type = 'text' ) {
		if ( is_array( $input ) ) {
			return array_map( function( $item ) use ( $type ) {
				return self::wppowerstack_sanitize_input( $item, $type );
			}, $input );
		}

		switch ( $type ) {
			case 'text':
				return sanitize_text_field( $input );
				
			case 'textarea':
				return sanitize_textarea_field( $input );
				
			case 'url':
				return esc_url_raw( $input );
				
			case 'email':
				return sanitize_email( $input );
				
			case 'integer':
				return absint( $input );
				
			case 'float':
				return floatval( $input );
				
			case 'alpha':
				return preg_replace( '/[^a-zA-Z]/', '', $input );
				
			case 'alphanumeric':
				return preg_replace( '/[^a-zA-Z0-9]/', '', $input );
				
			case 'search_query':
				// Enhanced search query sanitization
				$query = sanitize_text_field( $input );
				// Remove potentially harmful characters
				$query = preg_replace( '/[<>"\']/', '', $query );
				// Limit length
				return substr( $query, 0, 100 );
				
			default:
				return sanitize_text_field( $input );
		}
	}

	/**
	 * Output escaping with context awareness
	 * @param string $output
	 * @param string $context
	 * @return string
	 */
	public static function wppowerstack_escape_output( $output, $context = 'html' ) {
		switch ( $context ) {
			case 'html':
				return esc_html( $output );
				
			case 'attr':
				return esc_attr( $output );
				
			case 'url':
				return esc_url( $output );
				
			case 'js':
				return esc_js( $output );
				
			case 'sql':
				global $wpdb;
				return $wpdb->_real_escape( $output );
				
			default:
				return esc_html( $output );
		}
	}

	/**
	 * Get user IP safely
	 * @return string
	 */
	private static function wppowerstack_get_user_ip() {
		$ip_headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[$header] ) ) {
				$ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[$header] ) ) );
				$ip = trim( $ips[0] );
				
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		return '127.0.0.1';
	}

	/**
	 * Log security events
	 * @param string $event_type
	 * @param array $data
	 */
	private static function wppowerstack_log_security_event( $event_type, $data = array() ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'event' => $event_type,
			'data' => $data,
		);

		// Debug logging removed for production
	}

	/**
	 * Add security headers
	 */
	public function wppowerstack_add_security_headers() {
		// Only add on admin pages where our plugin loads
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Add CSP header for our assets
		$plugin_url = WPPOWERSTACK_COMMAND_BAR_URL;
		$csp_header = "default-src 'self'; script-src 'self' 'unsafe-inline' {$plugin_url}; style-src 'self' 'unsafe-inline' {$plugin_url}; img-src 'self' data:; font-src 'self' data:;";
		
		header( "Content-Security-Policy: {$csp_header}" );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	}

	/**
	 * Cleanup old rate limit data
	 */
	public function wppowerstack_cleanup_rate_limits() {
		$cutoff_time = time() - 3600; // Keep 1 hour of data
		
		foreach ( self::$rate_limits as $identifier => $timestamps ) {
			self::$rate_limits[$identifier] = array_filter(
				$timestamps,
				function( $timestamp ) use ( $cutoff_time ) {
					return $timestamp > $cutoff_time;
				}
			);
			
			// Remove empty entries
			if ( empty( self::$rate_limits[$identifier] ) ) {
				unset( self::$rate_limits[$identifier] );
			}
		}
	}
}
