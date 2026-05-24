=== WPPowerStack - Admin search and quick navigation ===
Contributors: wppowerstack, rajputravindra694
Tags: admin search, command palette, command bar, woocommerce search, quick edit
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightning-fast, keyboard-first command palette for WordPress admin navigation and WooCommerce search. Open with Cmd+K to search orders, products, and posts instantly.

== Description ==

WPPowerStack - Admin search and quick navigation is a lightweight, zero-latency admin search tool and command palette designed to accelerate your WordPress and WooCommerce workflow.

== Source Code ==

Source code is available on GitHub: https://github.com/wppowerstack/admin-search-quick-navigation

Instantly navigate your entire WordPress dashboard using a Mac Spotlight-style or Alfred-style search overlay triggered with a quick keyboard shortcut.

Stop wasting time clicking through endless backend submenus. This plugin indexes your admin layout, posts, pages, and store registries, allowing you to jump straight to specific pages or look up database entries in milliseconds. 

Built on a modern engineering stack utilizing native WordPress Elements, `cmdk`, and `fuse.js`, it delivers smooth client-side fuzzy matching with zero dashboard bloat. If you run a high-volume WooCommerce store or manage client sites, this universal search engine transforms your daily site management.

= Optimized For WooCommerce & HPOS =
Fully compatible with WooCommerce High-Performance Order Storage (HPOS). Speed up store management by locating customer orders, checking order statuses, and matching product SKUs directly from the command bar overlay.

== Installation ==

1. Upload the plugin zip file through the WordPress 'Plugins -> Add New -> Upload Plugin' screen, or install it directly via the repository search.
2. Activate the WPPowerStack Command Bar plugin through the 'Plugins' menu in WordPress.
3. Press `Cmd+K` (Mac) or `Ctrl+K` (Windows/Linux) anywhere on your administrative dashboard to launch the command palette overlay.

== Features ==

* **Keyboard-First Admin Navigation:** Launch instantly using `Cmd+K` or `Ctrl+K` from any backend screen.
* **Zero-Latency Search Engine:** Sub-100ms search performance running client-side fuzzy matching for typo-tolerant queries.
* **WooCommerce Integration:** Deeply indexed order search and product search. Find orders by number, status, or customer name, and products by name or SKU.
* **Dynamic Post Content Lookup:** Instantly query standard WordPress posts and pages by title or content.
* **Lightweight Production Bundle:** Under 200KB total bundle size (<15KB JavaScript) ensures your admin speed stays perfectly uncompromised.
* **Secure Architecture:** Built with complete nonce verification, strict capability checks, and fully scoped CSS to prevent style leakage.

== Upgrade Notice ==

= 1.0.0 =
Initial public launch of the premium-ready free universal search and admin command bar.

== Changelog ==

= 1.0.0 =
* Initial repository release.
* Core integration of fuzzy search engine with native WordPress menu matching.
* Complete WooCommerce core search compatibility for products and orders.

== Shortcode / Shortcuts ==

= Keyboard Shortcuts Control =
* `Cmd + K` or `Ctrl + K` : Open / toggle the command palette overlay container.
* `Up Arrow (↑)` / `Down Arrow (↓)` : Cycle through the live search results items.
* `Escape (Esc)` : Instantly close the active search popup window.