<?php
/**
 * Content Search Class
 * Handles core content search for Posts, Pages, and Media
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPPowerStack_Content_Search {

	/**
	 * Singleton instance
	 * @var WPPowerStack_Content_Search|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 * @return WPPowerStack_Content_Search
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
		add_action( 'rest_api_init', array( $this, 'wppowerstack_register_content_search_routes' ) );
	}

	/**
	 * Register content search REST routes
	 */
	public function wppowerstack_register_content_search_routes() {
		register_rest_route(
			'wppowerstack/v1',
			'/search-content',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'wppowerstack_search_content' ),
				'permission_callback' => array( $this, 'wppowerstack_check_permissions' ),
				'args'                => array(
					'q' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'minLength'         => 2,
						'maxLength'         => 100,
					),
					'type' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'enum'              => array( 'post', 'page', 'attachment', 'shop_order', 'product', 'customer', 'all' ),
						'default'           => 'all',
					),
				),
			)
		);
	}

	/**
	 * Check user permissions for content search
	 * @param WP_REST_Request $request
	 * @return bool|WP_Error
	 */
	public function wppowerstack_check_permissions( $request ) {
		// Enhanced capability verification - use standard WordPress check
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to access this endpoint.', 'wpst-admin-search-bar-quick-actions-navigation' ),
				array( 'status' => 403 )
			);
		}

		// WordPress REST API automatically handles nonce verification when X-WP-Nonce header is present
		// No manual nonce verification needed - WordPress core handles it

		// Rate limiting for content search (more restrictive)
		$user_id = get_current_user_id();
		$rate_limit_result = WPPowerStack_Security::wppowerstack_rate_limit( "content_search_user_{$user_id}", 20, 60 );
		if ( is_wp_error( $rate_limit_result ) ) {
			return $rate_limit_result;
		}

		return true;
	}

	/**
	 * Search content (Posts, Pages, Media)
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function wppowerstack_search_content( $request ) {
		$query = WPPowerStack_Security::wppowerstack_sanitize_input( $request->get_param( 'q' ), 'search_query' );
		$type = WPPowerStack_Security::wppowerstack_sanitize_input( $request->get_param( 'type' ), 'text' );

		if ( empty( $query ) || strlen( $query ) < 2 ) {
			return new WP_Error(
				'invalid_query',
				__( 'Search query must be at least 2 characters long.', 'wpst-admin-search-bar-quick-actions-navigation' ),
				array( 'status' => 400 )
			);
		}

		$results = array();

		// Search based on type
		if ( $type === 'all' || $type === 'post' ) {
			$results['posts'] = $this->wppowerstack_search_posts( $query );
		}

		if ( $type === 'all' || $type === 'page' ) {
			$results['pages'] = $this->wppowerstack_search_pages( $query );
		}

		if ( $type === 'all' || $type === 'attachment' ) {
			$results['media'] = $this->wppowerstack_search_media( $query );
		}

		// Search WooCommerce content if available
		if ( $this->wppowerstack_is_woocommerce_active() ) {
			if ( $type === 'all' || $type === 'shop_order' ) {
				$results['orders'] = $this->wppowerstack_search_woocommerce_orders( $query );
			}

			if ( $type === 'all' || $type === 'product' ) {
				$results['products'] = $this->wppowerstack_search_woocommerce_products( $query );
			}

			if ( $type === 'all' || $type === 'customer' ) {
				$results['customers'] = $this->wppowerstack_search_customers( $query );
			}
		}

		return rest_ensure_response( array(
			'success' => true,
			'results' => $results,
			'query' => WPPowerStack_Security::wppowerstack_escape_output( $query, 'html' ),
			'total' => array_sum( array_map( 'count', $results ) ),
		) );
	}

	/**
	 * Search posts with optimized query
	 * @param string $query
	 * @return array
	 */
	private function wppowerstack_search_posts( $query ) {
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			's'              => $query,
			'orderby'        => 'relevance',
			'order'          => 'DESC',
			'fields'         => 'ids',
		);

		// Add capability check for post types
		if ( ! current_user_can( 'edit_posts' ) ) {
			return array();
		}

		$query_results = new WP_Query( $args );
		$results = array();

		foreach ( $query_results->posts as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$results[] = $this->wppowerstack_format_content_item( $post, 'post' );
			}
		}

		wp_reset_postdata();
		return $results;
	}

	/**
	 * Search pages with optimized query
	 * @param string $query
	 * @return array
	 */
	private function wppowerstack_search_pages( $query ) {
		$args = array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			's'              => $query,
			'orderby'        => 'relevance',
			'order'          => 'DESC',
			'fields'         => 'ids',
		);

		// Add capability check for pages
		if ( ! current_user_can( 'edit_pages' ) ) {
			return array();
		}

		$query_results = new WP_Query( $args );
		$results = array();

		foreach ( $query_results->posts as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$results[] = $this->wppowerstack_format_content_item( $post, 'page' );
			}
		}

		wp_reset_postdata();
		return $results;
	}

	/**
	 * Search media (attachments) with optimized query
	 * @param string $query
	 * @return array
	 */
	private function wppowerstack_search_media( $query ) {
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 5,
			's'              => $query,
			'orderby'        => 'relevance',
			'order'          => 'DESC',
			'fields'         => 'ids',
			'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf' ),
		);

		// Add capability check for media
		if ( ! current_user_can( 'upload_files' ) ) {
			return array();
		}

		$query_results = new WP_Query( $args );
		$results = array();

		foreach ( $query_results->posts as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$results[] = $this->wppowerstack_format_content_item( $post, 'attachment' );
			}
		}

		wp_reset_postdata();
		return $results;
	}

	/**
	 * Format content item for API response
	 * @param WP_Post $post
	 * @param string $type
	 * @return array
	 */
	private function wppowerstack_format_content_item( $post, $type ) {
		$item = array(
			'id'          => $post->ID,
			'title'       => WPPowerStack_Security::wppowerstack_escape_output( get_the_title( $post ), 'html' ),
			'type'        => $type,
			'status'      => $post->post_status,
			'date'        => get_the_date( 'Y-m-d H:i', $post ),
			'edit_url'    => '',
			'view_url'    => '',
			'icon'        => '',
			'description' => '',
			'image_url'   => '',
			'image_alt'   => '',
		);

		// Set URLs and icons based on type
		switch ( $type ) {
			case 'post':
				$item['edit_url'] = get_edit_post_link( $post->ID );
				$item['view_url'] = get_permalink( $post->ID );
				$item['icon'] = 'dashicons-post';
				/* translators: %s: publication date */
				$item['description'] = sprintf( __( 'Published on %s', 'wpst-admin-search-bar-quick-actions-navigation' ), get_the_date( 'F j, Y', $post ) );
				
				// Get featured image for posts
				if ( has_post_thumbnail( $post->ID ) ) {
					$thumbnail_id = get_post_thumbnail_id( $post->ID );
					$item['image_url'] = wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' );
					$item['image_alt'] = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ?: get_the_title( $post );
				}
				break;

			case 'page':
				$item['edit_url'] = get_edit_post_link( $post->ID );
				$item['view_url'] = get_permalink( $post->ID );
				$item['icon'] = 'dashicons-page';
				/* translators: %s: publication date */
				$item['description'] = sprintf( __( 'Page published on %s', 'wpst-admin-search-bar-quick-actions-navigation' ), get_the_date( 'F j, Y', $post ) );
				
				// Get featured image for pages
				if ( has_post_thumbnail( $post->ID ) ) {
					$thumbnail_id = get_post_thumbnail_id( $post->ID );
					$item['image_url'] = wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' );
					$item['image_alt'] = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ?: get_the_title( $post );
				}
				break;

			case 'attachment':
				// Use the correct media edit URL for attachments
				$item['edit_url'] = admin_url( 'upload.php?item=' . $post->ID );
				$item['view_url'] = wp_get_attachment_url( $post->ID );
				$item['icon'] = $this->wppowerstack_get_media_icon( $post );
				/* translators: %s: upload date */
				$item['description'] = sprintf( __( 'Uploaded on %s', 'wpst-admin-search-bar-quick-actions-navigation' ), get_the_date( 'F j, Y', $post ) );
				
				// Add file size for media
				$file_size = size_format( filesize( get_attached_file( $post->ID ) ) );
				$item['description'] .= ' • ' . $file_size;
				
				// Get thumbnail for image attachments
				if ( wp_attachment_is_image( $post->ID ) ) {
					$item['image_url'] = wp_get_attachment_image_url( $post->ID, 'thumbnail' );
					$item['image_alt'] = get_post_meta( $post->ID, '_wp_attachment_image_alt', true ) ?: get_the_title( $post );
				}
				break;
		}

		return $item;
	}

	/**
	 * Get appropriate icon for media type
	 * @param WP_Post $post
	 * @return string
	 */
	private function wppowerstack_get_media_icon( $post ) {
		$mime_type = get_post_mime_type( $post );

		if ( strpos( $mime_type, 'image/' ) === 0 ) {
			return 'dashicons-format-image';
		} elseif ( strpos( $mime_type, 'video/' ) === 0 ) {
			return 'dashicons-format-video';
		} elseif ( strpos( $mime_type, 'audio/' ) === 0 ) {
			return 'dashicons-format-audio';
		} elseif ( $mime_type === 'application/pdf' ) {
			return 'dashicons-format-pdf';
		} elseif ( strpos( $mime_type, 'text/' ) === 0 ) {
			return 'dashicons-text-page';
		} else {
			return 'dashicons-media-default';
		}
	}

	/**
	 * Search WooCommerce orders with HPOS compatibility
	 * @param string $query
	 * @return array
	 */
	private function wppowerstack_search_woocommerce_orders( $query ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return array();
		}

		// Extract order number from natural language queries like "order 33", "order #33", etc.
		$order_number = null;
		if ( preg_match( '/order\s*#?(\d+)/i', $query, $matches ) ) {
			$order_number = $matches[1];
		}

		$args = array(
			'limit' => 20, // Get more orders to filter for product matches
			'return' => 'objects',
			'type' => 'shop_order',
			'status' => array_keys( wc_get_order_statuses() ),
		);

		// If we found a specific order number, search for it directly
		if ( $order_number ) {
			if ( $this->wppowerstack_is_hpos_enabled() ) {
				$args['search'] = $order_number;
				$args['search_fields'] = array( 'order_number' );
			} else {
				$args['s'] = $order_number;
			}
		} else {
			// Basic search for order number, email, etc.
			if ( $this->wppowerstack_is_hpos_enabled() ) {
				$args['search'] = "*{$query}*";
				$args['search_fields'] = array( 'order_number', 'billing_email', 'customer_id' );
			} else {
				$args['s'] = $query;
			}
		}

		$orders = wc_get_orders( $args );
		$results = array();

		foreach ( $orders as $order ) {
			$matches = false;
			
			// If we have a specific order number, check if it matches
			if ( $order_number ) {
				if ( $order->get_order_number() == $order_number ) {
					$matches = true;
				}
			} else {
				// Check if query matches order number (basic match)
				if ( stripos( $order->get_order_number(), $query ) !== false ) {
					$matches = true;
				}
				
				// Check if query matches customer email
				if ( stripos( $order->get_billing_email(), $query ) !== false ) {
					$matches = true;
				}
				
				// Check if query matches any product in the order
				if ( ! $matches ) {
					foreach ( $order->get_items() as $item ) {
						$product = $item->get_product();
						if ( $product ) {
							// Search product name, SKU, and description
							if ( stripos( $product->get_name(), $query ) !== false ||
							     stripos( $product->get_sku(), $query ) !== false ||
							     stripos( $item->get_name(), $query ) !== false ) {
								$matches = true;
								break;
							}
						}
					}
				}
			}
			
			// Only include orders that actually match the search
			if ( $matches ) {
				$results[] = array(
					'id' => $order->get_id(),
					'title' => sprintf(
						/* translators: %s: order number */
						__( 'Order #%s', 'wpst-admin-search-bar-quick-actions-navigation' ),
						$order->get_order_number()
					),
					'description' => sprintf(
						'%s - %s',
						$order->get_status() ? ucfirst( $order->get_status() ) : 'Unknown',
						$order->get_total() ? wc_price( $order->get_total() ) : 'Unknown'
					),
					'type' => 'shop_order',
					'status' => $order->get_status(),
					'date' => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : '',
					'edit_url' => $order->get_edit_order_url(),
					'view_url' => $order->get_view_order_url(),
					'icon' => 'dashicons-cart',
				);
			}
		}

		// Limit to 5 results after filtering
		return array_slice( $results, 0, 5 );
	}

	/**
	 * Search WooCommerce products
	 * @param string $query
	 * @return array
	 */
	private function wppowerstack_search_woocommerce_products( $query ) {
		if ( ! current_user_can( 'edit_products' ) ) {
			return array();
		}

		$results = array();

		// First, search by SKU using meta query
		$sku_args = array(
			'post_type' => 'product',
			'post_status' => 'publish',
			'posts_per_page' => 5,
			'meta_query' => array(
				array(
					'key' => '_sku',
					'value' => $query,
					'compare' => 'LIKE',
				),
			),
		);

		$sku_query = new WP_Query( $sku_args );

		if ( $sku_query->have_posts() ) {
			while ( $sku_query->have_posts() ) {
				$sku_query->the_post();
				$product = wc_get_product( get_the_ID() );

				if ( $product ) {
					$image_url = '';
					$image_alt = '';
					
					// Get product image
					if ( has_post_thumbnail( $product->get_id() ) ) {
						$thumbnail_id = get_post_thumbnail_id( $product->get_id() );
						$image_url = wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' );
						$image_alt = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ?: get_the_title();
					}
					
					$sku = $product->get_sku() ? 'SKU: ' . $product->get_sku() : '';
					
					$results[] = array(
						'id' => $product->get_id(),
						'title' => get_the_title() . ($sku ? ' - ' . $sku : ''),
						'description' => sprintf(
							'%s - %s',
							$product->get_type() ? ucfirst( $product->get_type() ) : 'Simple',
							$product->get_price() ? wc_price( $product->get_price() ) : 'Price not set'
						),
						'type' => 'product',
						'status' => $product->get_status(),
						'date' => get_the_date( 'Y-m-d H:i:s' ),
						'edit_url' => admin_url( 'post.php?post=' . $product->get_id() . '&action=edit' ),
						'view_url' => get_permalink( $product->get_id() ),
						'icon' => 'dashicons-products',
						'image_url' => $image_url,
						'image_alt' => $image_alt,
					);
				}
			}
		}
		wp_reset_postdata();

		// If no SKU results, search by title and content
		if ( empty( $results ) ) {
			$args = array(
				'post_type' => 'product',
				'post_status' => 'publish',
				'posts_per_page' => 5,
				's' => $query,
			);

			$products_query = new WP_Query( $args );

		if ( $products_query->have_posts() ) {
			while ( $products_query->have_posts() ) {
				$products_query->the_post();
				$product = wc_get_product( get_the_ID() );

				if ( $product ) {
					$image_url = '';
					$image_alt = '';
					
					// Get product image
					if ( has_post_thumbnail( $product->get_id() ) ) {
						$thumbnail_id = get_post_thumbnail_id( $product->get_id() );
						$image_url = wp_get_attachment_image_url( $thumbnail_id, 'thumbnail' );
						$image_alt = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ?: get_the_title();
					}
					
					$results[] = array(
						'id' => $product->get_id(),
						'title' => get_the_title(),
						'description' => sprintf(
							'%s - %s',
							$product->get_type() ? ucfirst( $product->get_type() ) : 'Simple',
							$product->get_price() ? wc_price( $product->get_price() ) : 'Price not set'
						),
						'type' => 'product',
						'status' => $product->get_status(),
						'date' => get_the_date( 'Y-m-d H:i:s' ),
						'edit_url' => admin_url( 'post.php?post=' . $product->get_id() . '&action=edit' ),
						'view_url' => get_permalink( $product->get_id() ),
						'icon' => 'dashicons-products',
						'image_url' => $image_url,
						'image_alt' => $image_alt,
					);
				}
			}
			}
		}

		wp_reset_postdata();
		return $results;
	}

	/**
	 * Check if WooCommerce HPOS is enabled
	 * @return bool
	 */
	private function wppowerstack_is_hpos_enabled() {
		if ( ! class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}

		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Check if WooCommerce is active
	 * @return bool
	 */
	private function wppowerstack_is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Search WooCommerce customers
	 * @param string $query
	 * @return array
	 */
	private function wppowerstack_search_customers( $query ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return array();
		}

		$results = array();
		
		// Search customers by name, email, or ID - try broader search first
		$customers = get_users( array(
			'search' => '*' . $query . '*',
			'search_columns' => array( 'user_login', 'user_email', 'display_name', 'user_nicename' ),
			'number' => 5,
		) );

		foreach ( $customers as $customer ) {
			// Get customer's orders
			$customer_orders = wc_get_orders( array(
				'customer' => $customer->ID,
				'limit' => 3,
				'status' => array_keys( wc_get_order_statuses() ),
			) );

			// Only include users who have orders (they are customers)
			if ( empty( $customer_orders ) ) {
				continue;
			}

			$orders_info = array();
			foreach ( $customer_orders as $order ) {
				$orders_info[] = array(
					'id' => $order->get_id(),
					'number' => $order->get_order_number(),
					'status' => $order->get_status(),
					'total' => $order->get_total(),
					'date' => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d' ) : '',
					'edit_url' => $order->get_edit_order_url(),
				);
			}

			// Get total spent by customer
			$total_spent = wc_get_customer_total_spent( $customer->ID );
			
			$results[] = array(
				'id' => $customer->ID,
				'title' => $customer->display_name ? $customer->display_name : $customer->user_login,
				'description' => sprintf(
					'%s • %s orders • Total: %s',
					$customer->user_email,
					count( $customer_orders ),
					$total_spent ? wc_price( $total_spent ) : wc_price( 0 )
				),
				'type' => 'customer',
				'status' => 'active',
				'date' => $customer->user_registered,
				'edit_url' => get_edit_user_link( $customer->ID ),
				'view_url' => admin_url( 'admin.php?page=wc-orders&customer=' . $customer->ID ),
				'icon' => 'dashicons-users',
				'orders' => $orders_info,
			);
		}

		return $results;
	}
}
