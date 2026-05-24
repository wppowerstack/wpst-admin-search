<?php
/**
 * Plugin Name:       WPPowerStack - Admin search and quick navigation
 * Description:       Instantly navigate WordPress and WooCommerce using a Spotlight-style command palette (Cmd+K). Fast, lightweight admin search with zero bloat! ⚡
 * Version:           1.0.0
 * Author:            WPPowerStack
 * Author URI:        https://profiles.wordpress.org/rajputravindra694/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpst-admin-search-bar-quick-actions-navigation
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.6
 * Woo: 5.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants
define( 'WPPOWERSTACK_COMMAND_BAR_VERSION', '1.0.0' );
define( 'WPPOWERSTACK_COMMAND_BAR_FILE', __FILE__ );
define( 'WPPOWERSTACK_COMMAND_BAR_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPPOWERSTACK_COMMAND_BAR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare WooCommerce HPOS compatibility
 */
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
});

/**
 * Main plugin class - Singleton Pattern
 */
final class WPPowerStack_CommandBar {

	/**
	 * Singleton instance
	 * @var WPPowerStack_CommandBar|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 * @return WPPowerStack_CommandBar
	 */
	public static function wppowerstack_get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		$this->wppowerstack_init();
	}

	/**
	 * Initialize plugin
	 */
	private function wppowerstack_init() {
		add_action( 'plugins_loaded', array( $this, 'wppowerstack_load_plugin' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'wppowerstack_enqueue_assets' ) );
		add_action( 'admin_footer', array( $this, 'wppowerstack_render_mount_point' ) );
	}

	/**
	 * Load plugin components
	 */
	public function wppowerstack_load_plugin() {
		// WordPress 4.6+ automatically loads text domains from wordpress.org
		
		// Load core functionality
		require_once WPPOWERSTACK_COMMAND_BAR_PATH . 'includes/class-wppowerstack-menu-parser.php';
		require_once WPPOWERSTACK_COMMAND_BAR_PATH . 'includes/class-wppowerstack-security.php';
		require_once WPPOWERSTACK_COMMAND_BAR_PATH . 'includes/class-wppowerstack-content-search.php';
		
		// Initialize content search to register REST API routes
		WPPowerStack_Content_Search::wppowerstack_get_instance();
	}

	/**
	 * Check if WooCommerce is active and compatible (supports both HPOS and Legacy)
	 * @return bool
	 */
	private function wppowerstack_is_woocommerce_active() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return false;
		}

		// Check WooCommerce version compatibility (supports 5.0+ for both storage types)
		if ( version_compare( WC()->version, '5.0', '<' ) ) {
			return false;
		}

		// Plugin is compatible with both HPOS and Legacy storage
		// No need to force HPOS compatibility check - we support both
		return true;
	}

	/**
	 * Render React mount point in admin footer
	 */
	public function wppowerstack_render_mount_point() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		echo '<div id="wpst-admin-search-bar-root"></div>';
	}

	/**
	 * Enqueue plugin assets
	 */
	public function wppowerstack_enqueue_assets() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Enqueue compiled styles
		$style_file = WPPOWERSTACK_COMMAND_BAR_PATH . 'assets/css/command-bar.css';
		$style_version = file_exists( $style_file ) ? filemtime( $style_file ) : WPPOWERSTACK_COMMAND_BAR_VERSION;
		
		wp_enqueue_style(
			'wpst-admin-search-bar-style',
			WPPOWERSTACK_COMMAND_BAR_URL . 'assets/css/command-bar.css',
			array(),
			$style_version
		);

		// Enqueue compiled React script with proper dependencies
		$this->wppowerstack_enqueue_compiled_script();

		// Enqueue WordPress REST API script to get proper nonce handling
		wp_enqueue_script( 'wp-api' );
		
		// Localize script with menu data and configuration
		wp_localize_script(
			'wpst-admin-search-bar-script',
			'wppowerstack_data',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'rest_url' => rest_url( 'wppowerstack/v1/' ),
				'menu_items' => $this->wppowerstack_get_menu_items(),
				'plugin_url' => WPPOWERSTACK_COMMAND_BAR_URL,
				'version' => WPPOWERSTACK_COMMAND_BAR_VERSION,
			)
		);

		// Add inline script for configuration and direct React initialization
		$inline_script = '
			window.wppowerstack = ' . wp_json_encode( array(
				'keyboardShortcut' => array(
					'modifier' => $this->wppowerstack_get_keyboard_modifier(),
					'key' => 'k',
				),
				'i18n' => array(
					'placeholder' => __( 'Type a command or search...', 'wpst-admin-search-bar-quick-actions-navigation' ),
					'no_results' => __( 'No results found.', 'wpst-admin-search-bar-quick-actions-navigation' ),
					'navigate' => __( 'Navigate to', 'wpst-admin-search-bar-quick-actions-navigation' ),
					'search' => __( 'Search', 'wpst-admin-search-bar-quick-actions-navigation' ),
					'edit' => __( 'Edit', 'wpst-admin-search-bar-quick-actions-navigation' ),
					'view' => __( 'View', 'wpst-admin-search-bar-quick-actions-navigation' ),
				),
			) ) . '; 
			
			setTimeout(function() { 
				console.log("WPPowerStack: Initializing React directly..."); 
				
				// Hide any legacy search bars
				var legacyElements = document.querySelectorAll("[id*=wppowerstack], [class*=wppowerstack]");
				legacyElements.forEach(function(el) {
					if (el.id !== "wpst-admin-search-bar-root") {
						el.style.display = "none";
					}
				});
				
				var mountPoint = document.getElementById("wpst-admin-search-bar-root"); 
				console.log("WPPowerStack: Mount point exists:", !!mountPoint); 
				if (mountPoint) { 
					console.log("WPPowerStack: Mount point content:", mountPoint.innerHTML); 
					// Direct React initialization without relying on global init
					if (window.wp && window.wp.element) {
						console.log("WPPowerStack: WordPress React available, mounting directly..."); 
						try {
							// Create a full-featured React component with search
							var React = window.wp.element;
							var useState = React.useState;
							var useEffect = React.useEffect;
							var createElement = React.createElement;
							
							var App = function() {
								var isOpen = useState(false);
								var setIsOpen = isOpen[1];
								var searchQuery = useState("");
								var searchResults = useState([]);
								var isLoading = useState(false);
								var setSearchQuery = searchQuery[1];
								var setSearchResults = searchResults[1];
								var setIsLoading = isLoading[1];
								
								// Function to close WordPress native command palette
								function closeWordPressCommandPalette() {
									// Try to find and close WordPress command palette
									var wpCommandPalette = document.querySelector(".components-modal__screen-overlay, .command-palette-modal, [data-testid=\"command-palette\"]");
									if (wpCommandPalette) {
										// Try to find the close button
										var closeButton = wpCommandPalette.querySelector("button[aria-label*=\"Close\"], .components-button[aria-label*=\"close\"], button[onclick*=\"close\"]");
										if (closeButton) {
											closeButton.click();
										} else {
											// Fallback: remove the modal directly
											wpCommandPalette.remove();
										}
									}
									
									// Also try to close by dispatching escape event to WordPress
									var escapeEvent = new KeyboardEvent(\'keydown\', {
										key: \'Escape\',
										code: \'Escape\',
										keyCode: 27,
										which: 27,
										bubbles: true
									});
									document.dispatchEvent(escapeEvent);
								}
								
								useEffect(function() {
									function handleKeyDown(e) {
										if ((e.metaKey || e.ctrlKey) && e.key === "k") {
											e.preventDefault();
											setIsOpen(true);
										}
										// Close on Escape key
										if (e.key === "Escape" && isOpen[0]) {
											e.preventDefault();
											setIsOpen(false);
											// Close WordPress native command palette
											closeWordPressCommandPalette();
										}
									}
									document.addEventListener("keydown", handleKeyDown);
									return function() { document.removeEventListener("keydown", handleKeyDown); };
								}, [isOpen[0]]);
								
								// Hook WordPress admin bar Ctrl+K button
								useEffect(function() {
									function handleAdminBarClick(e) {
										e.preventDefault();
										e.stopPropagation();
										setIsOpen(true);
									}
									
									// Try to find and hook the admin bar command palette button
									var adminBarButton = document.querySelector("#wp-admin-bar-command-palette .ab-item");
									if (adminBarButton) {
										adminBarButton.addEventListener("click", handleAdminBarClick);
										return function() { 
											adminBarButton.removeEventListener("click", handleAdminBarClick); 
										};
									}
								}, []);
								
								// Search functionality with request cancellation
								useEffect(function() {
									if (!searchQuery[0]) {
										setSearchResults(window.wppowerstack_data.menu_items || []);
										return;
									}
									
									setIsLoading(true);
									var timeout = setTimeout(function() {
										// Search menu items
										var menuItems = window.wppowerstack_data.menu_items || [];
										var filtered = menuItems.filter(function(item) {
											return item.title.toLowerCase().includes(searchQuery[0].toLowerCase());
										});
										
										// Search WooCommerce orders/products if available
										if (window.wppowerstack_data.rest_url) {
											// Create new AbortController for this request
											var abortController = new AbortController();
											
											fetch(window.wppowerstack_data.rest_url + "search-content?q=" + encodeURIComponent(searchQuery[0]) + "&type=all", {
												headers: {
													"X-WP-Nonce": wpApiSettings.nonce
												},
												signal: abortController.signal
											})
											.then(function(response) { 
												// Check if request was aborted
												if (abortController.signal.aborted) {
													return Promise.reject(new Error("Request aborted"));
												}
												return response.json(); 
											})
											.then(function(data) {
												// Check if request was aborted during JSON parsing
												if (abortController.signal.aborted) {
													return;
												}
												
												if (data && data.success && data.results) {
													// Flatten the results object to an array
													var allResults = [];
													if (data.results.posts) allResults = allResults.concat(data.results.posts);
													if (data.results.pages) allResults = allResults.concat(data.results.pages);
													if (data.results.media) allResults = allResults.concat(data.results.media);
													if (data.results.orders) allResults = allResults.concat(data.results.orders);
													if (data.results.products) allResults = allResults.concat(data.results.products);
													if (data.results.customers) allResults = allResults.concat(data.results.customers);
													setSearchResults(filtered.concat(allResults));
												} else {
													setSearchResults(filtered);
												}
												setIsLoading(false);
											})
											.catch(function(error) {
												// Don\'t update results if request was aborted
												if (error.message === "Request aborted" || abortController.signal.aborted) {
													return;
												}
												setSearchResults(filtered);
												setIsLoading(false);
											});
											
											// Store abort controller so it can be cancelled by cleanup
											window.wppowerstack_currentRequest = abortController;
										} else {
											setSearchResults(filtered);
											setIsLoading(false);
										}
									}, 300);
									
									return function() { 
										clearTimeout(timeout);
										// Cancel any pending request when component unmounts or query changes
										if (window.wppowerstack_currentRequest) {
											window.wppowerstack_currentRequest.abort();
											window.wppowerstack_currentRequest = null;
										}
									};
								}, [searchQuery[0]]);
								
								if (!isOpen[0]) return null;
								
								return createElement("div", {
									className: "wppowerstack-palette-overlay",
									onClick: function() { 
										setIsOpen(false);
										closeWordPressCommandPalette();
									}
								}, 
									createElement("div", {
										className: "wppowerstack-palette",
										onClick: function(e) { e.stopPropagation(); }
									}, [
										createElement("div", {
											key: "search",
											className: "wppowerstack-palette-search"
										}, 
											createElement("input", {
												type: "text",
												className: "wppowerstack-palette-input",
												placeholder: window.wppowerstack.i18n.placeholder,
												autoFocus: true,
												value: searchQuery[0],
												onChange: function(e) { setSearchQuery(e.target.value); }
											})
										),
										createElement("div", {
											key: "content",
											className: "wppowerstack-palette-content"
										}, 
											isLoading[0] ? 
												createElement("div", {className: "wppowerstack-palette-loading"}, 
													createElement("div", {className: "wppowerstack-palette-spinner"}),
													"Searching..."
												) :
											searchResults[0].length > 0 ?
												searchResults[0].map(function(item, index) {
													return createElement("div", {
														key: index,
														className: "wppowerstack-palette-content-item",
														"data-type": item.type || \'menu\',
														"data-id": item.id || \'\',
														onClick: function() { 
															// Handle different URL fields for different content types
															// Content search results use edit_url, menu items use url
															var targetUrl = \'\';
															if (item.edit_url) {
																targetUrl = item.edit_url;
															} else if (item.url) {
																targetUrl = item.url;
															} else if (item.view_url) {
																targetUrl = item.view_url;
															}
															
															// Open in new tab
															if (targetUrl) {
																window.open(targetUrl, \'_blank\');
															}
														}
													}, [
														// Show image if available, otherwise show icon
														item.image_url ?
															createElement("div", {
																className: "wppowerstack-palette-content-item-image"
															}, createElement("img", {
																src: item.image_url,
																alt: item.image_alt || item.title,
																className: "wppowerstack-palette-content-item-img"
															})) :
															createElement("div", {
																className: "wppowerstack-palette-content-item-icon"
															}, createElement("span", {className: "dashicons " + (item.icon || "dashicons-admin-generic")})),
														createElement("div", {
															className: "wppowerstack-palette-content-item-content"
														}, [
															createElement("div", {
																className: "wppowerstack-palette-content-item-title",
																dangerouslySetInnerHTML: {__html: item.title}
															}),
															item.description ? createElement("div", {
																className: "wppowerstack-palette-content-item-description",
																dangerouslySetInnerHTML: {__html: item.description}
															}) : null
														]),
														// Add Quick Edit button for premium upsell
														createElement("button", {
															className: "inline-edit-trigger premium-locked",
															onClick: function(e) {
																e.preventDefault();
																e.stopPropagation();
																console.log("Premium button clicked!");
																
																// Get the item type from the parent row
																var itemRow = e.target.closest(\'.wppowerstack-palette-content-item\');
																if (itemRow) {
																	var itemType = itemRow.getAttribute(\'data-type\');
																	console.log("Item type:", itemType);
																	
																	// Show upsell modal
																	showUpsellModal(itemType);
																} else {
																	console.log("Could not find parent row");
																}
															}
														}, [
															createElement("span", {className: "dashicons dashicons-lock"}),
															" Quick Edit"
														])
													]);
												}) :
												createElement("div", {className: "wppowerstack-palette-content"}, "No results found")
										)
									])
								);
							};
							
							// Add upsell modal functionality
							function showUpsellModal(itemType) {
								var overlay = document.createElement(\'div\');
								overlay.className = \'search-upsell-overlay\';
								
								var modal = document.createElement(\'div\');
								modal.className = \'search-upsell-modal\';
								
								var title = document.createElement(\'div\');
								title.className = \'search-upsell-title\';
								title.textContent = \'🔒 Premium Feature\';
								
								var description = document.createElement(\'div\');
								description.className = \'search-upsell-description\';
								
								// Context-specific marketing copy
								switch(itemType) {
									case \'order\':
										description.textContent = \'With Premium, you can switch order statuses (e.g., Processing to Completed) and add internal order notes instantly without leaving this popup!\';
										break;
									case \'product\':
										description.textContent = \'With Premium, you can tweak product prices, update inventory stock levels, and edit short descriptions instantly!\';
										break;
									case \'attachment\':
									case \'media\':
										description.textContent = \'With Premium, you can swap out featured images and update media alt tags right here!\';
										break;
									default:
										description.textContent = \'With Premium, you can run instant inline updates to titles, categories, and post statuses without page reloads!\';
								}
								
								var actions = document.createElement(\'div\');
								actions.className = \'search-upsell-actions\';
								
								var upgradeBtn = document.createElement(\'a\');
								upgradeBtn.className = \'search-upsell-upgrade\';
								upgradeBtn.href = \'https://example.com/premium\'; // Change this URL later
								upgradeBtn.textContent = \'Upgrade to Premium\';
								upgradeBtn.target = \'_blank\';
								
								var laterBtn = document.createElement(\'button\');
								laterBtn.className = \'search-upsell-later\';
								laterBtn.textContent = \'Maybe Later\';
								laterBtn.onclick = function() {
									document.body.removeChild(overlay);
								};
								
								actions.appendChild(laterBtn);
								actions.appendChild(upgradeBtn);
								
								modal.appendChild(title);
								modal.appendChild(description);
								modal.appendChild(actions);
								overlay.appendChild(modal);
								
								// Close on overlay click
								overlay.onclick = function(e) {
									if (e.target === overlay) {
										document.body.removeChild(overlay);
									}
								};
								
								document.body.appendChild(overlay);
							}
							
							React.render(createElement(App), mountPoint);
							console.log("WPPowerStack: React interface mounted successfully!"); 
						} catch (error) {
							console.error("WPPowerStack: Error mounting React:", error); 
						}
					} else {
						console.error("WPPowerStack: WordPress React not available!"); 
					}
				} else {
					console.error("WPPowerStack: Mount point not found!"); 
				}
			}, 100);';
		
		wp_add_inline_script( 'wpst-admin-search-bar-script', $inline_script );
	}

	/**
	 * Get keyboard modifier based on platform
	 * @return string
	 */
	private function wppowerstack_get_keyboard_modifier() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		
		// Check if it's a Mac
		if ( strpos( $user_agent, 'Mac' ) !== false ) {
			return 'cmd';
		}
		
		return 'ctrl';
	}

	/**
	 * Get menu items for localization
	 * @return array
	 */
	private function wppowerstack_get_menu_items() {
		$menu_parser = WPPowerStack_MenuParser::wppowerstack_get_instance();
		$menu_items = $menu_parser->wppowerstack_get_cached_menu_items();
		
		// Apply WooCommerce filter if available
		if ( $this->wppowerstack_is_woocommerce_active() ) {
			$menu_items = apply_filters( 'wppowerstack_menu_items', $menu_items );
		}
		
		return $menu_items;
	}

	/**
	 * Enqueue compiled React script with proper WordPress dependencies
	 */
	private function wppowerstack_enqueue_compiled_script() {
		// Force load React build - no fallback to vanilla JS
		$build_dir = WPPOWERSTACK_COMMAND_BAR_PATH . 'build';
		$asset_file = $build_dir . '/command-bar.asset.php';
		$script_file = $build_dir . '/command-bar.js';

		// Only proceed if build files exist
		if ( ! file_exists( $build_dir ) || ! file_exists( $asset_file ) || ! file_exists( $script_file ) ) {
			return;
		}

		// Include the asset file to get dependencies and version
		$assets = include $asset_file;
		$dependencies = $assets['dependencies'];
		$version = $assets['version'];

		
		// Enqueue the compiled script with proper dependencies
		wp_enqueue_script(
			'wpst-admin-search-bar-script',
			WPPOWERSTACK_COMMAND_BAR_URL . 'build/command-bar.js',
			$dependencies, // Dynamic dependencies from wp-scripts
			$version,      // Version hash from wp-scripts
			true
		);
	}
}

// Initialize the plugin
WPPowerStack_CommandBar::wppowerstack_get_instance();

