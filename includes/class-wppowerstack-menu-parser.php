<?php
/**
 * Menu Parser Class
 * Extracts WordPress admin menus and converts to JSON structure
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPPowerStack_MenuParser {

	/**
	 * Singleton instance
	 * @var WPPowerStack_MenuParser|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 * @return WPPowerStack_MenuParser
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
		// Initialization will be done in Phase 2
	}

	/**
	 * Parse WordPress admin menus
	 * @return array
	 */
	public function wppowerstack_parse_admin_menus() {
		global $menu, $submenu;
		
		$menu_items = array();
		
		// Add common admin pages
		$menu_items = array_merge( $menu_items, $this->wppowerstack_get_common_admin_pages() );
		
		// Parse main menu items
		if ( isset( $menu ) && is_array( $menu ) ) {
			foreach ( $menu as $item ) {
				if ( ! empty( $item[0] ) && current_user_can( $item[1] ) ) {
					$menu_items[] = array(
						'title' => wp_strip_all_tags( $item[0] ),
						'url' => $item[2],
						'capability' => $item[1],
						'icon' => $item[6] ?? '',
						'type' => 'main_menu',
						'category' => 'WordPress Admin',
						'keywords' => $this->wppowerstack_generate_keywords( wp_strip_all_tags( $item[0] ) )
					);
				}
			}
		}
		
		// Parse submenu items
		if ( isset( $submenu ) && is_array( $submenu ) ) {
			foreach ( $submenu as $parent => $items ) {
				foreach ( $items as $item ) {
					if ( ! empty( $item[0] ) && current_user_can( $item[1] ) ) {
						$menu_items[] = array(
							'title' => wp_strip_all_tags( $item[0] ),
							'url' => $item[2],
							'capability' => $item[1],
							'parent' => $parent,
							'type' => 'submenu',
							'category' => 'WordPress Admin',
							'keywords' => $this->wppowerstack_generate_keywords( wp_strip_all_tags( $item[0] ) )
						);
					}
				}
			}
		}
		
		// Add post type quick links
		$menu_items = array_merge( $menu_items, $this->wppowerstack_get_post_type_links() );
		
		// Add settings pages
		$menu_items = array_merge( $menu_items, $this->wppowerstack_get_settings_pages() );
		
		return apply_filters( 'wppowerstack_parsed_menu_items', $menu_items );
	}

	/**
	 * Get common admin pages
	 * @return array
	 */
	private function wppowerstack_get_common_admin_pages() {
		$common_pages = array(
			array(
				'title' => __( 'Dashboard', 'wpst-admin-search-bar-quick-actions-navigation' ),
				'url' => admin_url(),
				'capability' => 'read',
				'icon' => 'dashicons-dashboard',
				'type' => 'common',
				'category' => 'Quick Access',
				'keywords' => array( 'home', 'main', 'overview' )
			),
			array(
				'title' => __( 'New Post', 'wpst-admin-search-bar-quick-actions-navigation' ),
				'url' => admin_url( 'post-new.php' ),
				'capability' => 'edit_posts',
				'icon' => 'dashicons-plus-alt',
				'type' => 'common',
				'category' => 'Quick Access',
				'keywords' => array( 'create', 'write', 'article', 'blog' )
			),
			array(
				'title' => __( 'New Page', 'wpst-admin-search-bar-quick-actions-navigation' ),
				'url' => admin_url( 'post-new.php?post_type=page' ),
				'capability' => 'edit_pages',
				'icon' => 'dashicons-plus-alt',
				'type' => 'common',
				'category' => 'Quick Access',
				'keywords' => array( 'create', 'write', 'content' )
			),
			array(
				'title' => __( 'Media Library', 'wpst-admin-search-bar-quick-actions-navigation' ),
				'url' => admin_url( 'upload.php' ),
				'capability' => 'upload_files',
				'icon' => 'dashicons-media-default',
				'type' => 'common',
				'category' => 'Content',
				'keywords' => array( 'images', 'files', 'uploads', 'photos' )
			),
			array(
				'title' => __( 'Comments', 'wpst-admin-search-bar-quick-actions-navigation' ),
				'url' => admin_url( 'edit-comments.php' ),
				'capability' => 'moderate_comments',
				'icon' => 'dashicons-admin-comments',
				'type' => 'common',
				'category' => 'Content',
				'keywords' => array( 'discussion', 'feedback', 'replies' )
			),
			array(
				'title' => __( 'Users', 'wpst-admin-search-bar-quick-actions-navigation' ),
				'url' => admin_url( 'users.php' ),
				'capability' => 'list_users',
				'icon' => 'dashicons-admin-users',
				'type' => 'common',
				'category' => 'Users',
				'keywords' => array( 'accounts', 'profiles', 'people' )
			),
			array(
				'title' => __( 'Plugins', 'wpst-admin-search-bar-quick-actions-navigation' ),
				'url' => admin_url( 'plugins.php' ),
				'capability' => 'activate_plugins',
				'icon' => 'dashicons-admin-plugins',
				'type' => 'common',
				'category' => 'Settings',
				'keywords' => array( 'extensions', 'add-ons', 'modules' )
			),
			array(
				'title' => __( 'Themes', 'wpst-admin-search-bar-quick-actions-navigation' ),
				'url' => admin_url( 'themes.php' ),
				'capability' => 'switch_themes',
				'icon' => 'dashicons-admin-appearance',
				'type' => 'common',
				'category' => 'Appearance',
				'keywords' => array( 'design', 'template', 'layout' )
			),
		);

		// Filter by user capabilities
		return array_filter( $common_pages, function( $page ) {
			return current_user_can( $page['capability'] );
		} );
	}

	/**
	 * Get post type links
	 * @return array
	 */
	private function wppowerstack_get_post_type_links() {
		$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
		$links = array();

		foreach ( $post_types as $post_type ) {
			if ( ! current_user_can( $post_type->cap->edit_posts ) ) {
				continue;
			}

			// Add list view
			$links[] = array(
				/* translators: %s: post type name */
				'title' => sprintf( __( 'All %s', 'wpst-admin-search-bar-quick-actions-navigation' ), $post_type->labels->name ),
				'url' => admin_url( 'edit.php?post_type=' . $post_type->name ),
				'capability' => $post_type->cap->edit_posts,
				'icon' => 'dashicons-list-view',
				'type' => 'post_type',
				'category' => 'Content',
				'keywords' => array_merge(
					array( strtolower( $post_type->labels->name ), strtolower( $post_type->labels->singular_name ) ),
					$this->wppowerstack_generate_keywords( $post_type->labels->name )
				)
			);

			// Add new post/page
			$links[] = array(
				/* translators: %s: post type singular name */
				'title' => sprintf( __( 'New %s', 'wpst-admin-search-bar-quick-actions-navigation' ), $post_type->labels->singular_name ),
				'url' => admin_url( 'post-new.php?post_type=' . $post_type->name ),
				'capability' => $post_type->cap->edit_posts,
				'icon' => 'dashicons-plus-alt',
				'type' => 'post_type',
				'category' => 'Content',
				'keywords' => array_merge(
					array( 'create', 'new', 'add', strtolower( $post_type->labels->singular_name ) ),
					$this->wppowerstack_generate_keywords( $post_type->labels->singular_name )
				)
			);
		}

		return $links;
	}

	/**
	 * Get settings pages
	 * @return array
	 */
	private function wppowerstack_get_settings_pages() {
		$settings_pages = array();

		// General settings
		if ( current_user_can( 'manage_options' ) ) {
			$settings_pages[] = array(
				'title' => __( 'General Settings', 'wpst-admin-search-bar-quick-actions-navigation' ),
				'url' => admin_url( 'options-general.php' ),
				'capability' => 'manage_options',
				'icon' => 'dashicons-admin-settings',
				'type' => 'settings',
				'category' => 'Settings',
				'keywords' => array( 'general', 'basic', 'configuration' )
			);

			$settings_pages[] = array(
				'title' => __( 'Permalinks', 'wpst-admin-search-bar-quick-actions-navigation' ),
				'url' => admin_url( 'options-permalink.php' ),
				'capability' => 'manage_options',
				'icon' => 'dashicons-admin-links',
				'type' => 'settings',
				'category' => 'Settings',
				'keywords' => array( 'urls', 'seo', 'links', 'slugs' )
			);
		}

		return $settings_pages;
	}

	/**
	 * Generate keywords for search
	 * @param string $title
	 * @return array
	 */
	private function wppowerstack_generate_keywords( $title ) {
		$keywords = array();
		$words = preg_split( '/[\s\-_]+/', strtolower( $title ) );
		
		foreach ( $words as $word ) {
			$word = trim( $word, '.,!?;:' );
			if ( strlen( $word ) > 2 ) {
				$keywords[] = $word;
			}
		}

		return array_unique( $keywords );
	}

	/**
	 * Get cached menu items
	 * @return array
	 */
	public function wppowerstack_get_cached_menu_items() {
		$cache_key = 'wppowerstack_menu_items_' . get_current_user_id();
		$cached_items = wp_cache_get( $cache_key );
		
		if ( false === $cached_items ) {
			$menu_items = $this->wppowerstack_parse_admin_menus();
			wp_cache_set( $cache_key, $menu_items, '', 300 ); // Cache for 5 minutes
			return $menu_items;
		}
		
		return $cached_items;
	}

	/**
	 * Clear menu cache
	 */
	public function wppowerstack_clear_menu_cache() {
		$cache_key = 'wppowerstack_menu_items_' . get_current_user_id();
		wp_cache_delete( $cache_key );
	}
}
