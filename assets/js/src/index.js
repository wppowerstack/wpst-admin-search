/**
 * WPPowerStack Command Bar - Main React Component
 * 
 * A keyboard-first command palette for WordPress admin navigation
 * Launches with Cmd+K or Ctrl+K
 */

import './styles/command-bar.scss';
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

// Import fuse.js for fuzzy search
import Fuse from 'fuse.js';

/**
 * Main Command Bar Component
 */
const CommandBar = () => {
	const [isOpen, setIsOpen] = useState(false);
	const [query, setQuery] = useState('');
	const [results, setResults] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	const [menuItems] = useState(window.wppowerstack_data?.menu_items || []);
	const searchTimeoutRef = useRef(null);
	const abortControllerRef = useRef(null);

	// Initialize fuse.js for menu search
	const fuse = new Fuse(menuItems, {
		keys: ['title', 'keywords', 'category'],
		threshold: 0.3,
		includeScore: true
	});

	// Keyboard shortcuts
	useEffect(() => {
		const handleKeyDown = (event) => {
			// Cmd+K or Ctrl+K to open
			if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
				event.preventDefault();
				setIsOpen(true);
				return;
			}

			// Escape to close
			if (event.key === 'Escape' && isOpen) {
				setIsOpen(false);
				return;
			}
		};

		document.addEventListener('keydown', handleKeyDown);
		return () => document.removeEventListener('keydown', handleKeyDown);
	}, [isOpen]);

	// Close WordPress native command palette when ours opens
	useEffect(() => {
		if (isOpen) {
			// Close WordPress native command palette
			const wpCommandPalette = document.querySelector('[class*="command-palette"], [class*="command-palette__"]');
			if (wpCommandPalette) {
				wpCommandPalette.style.display = 'none';
			}
		}
	}, [isOpen]);

	// Search functionality
	const performSearch = async (searchQuery) => {
		if (!searchQuery.trim()) {
			setResults([]);
			return;
		}

		setIsLoading(true);

		// Cancel previous request
		if (abortControllerRef.current) {
			abortControllerRef.current.abort();
		}

		// Create new abort controller
		abortControllerRef.current = new AbortController();

		try {
			// First, show matching menu items instantly
			const menuResults = fuse.search(searchQuery).map(result => ({
				...result.item,
				type: 'menu',
				score: result.score
			}));

			setResults(menuResults);

			// Then fetch additional results from API
			const response = await fetch(
				`${window.wppowerstack_data.rest_url}search-content?q=${encodeURIComponent(searchQuery)}&type=all`,
				{
					signal: abortControllerRef.current.signal,
					headers: {
						'X-WP-Nonce': window.wpApiSettings?.nonce
					}
				}
			);

			if (response.ok) {
				const data = await response.json();
				const apiResults = [];

				// Process different content types from the API response
				if (data.success && data.results) {
					Object.keys(data.results).forEach(type => {
						if (Array.isArray(data.results[type])) {
							data.results[type].forEach(item => {
								apiResults.push({
									...item,
									type: type === 'posts' ? 'post' : 
										  type === 'pages' ? 'page' : 
										  type === 'attachments' ? 'attachment' :
										  type === 'products' ? 'product' :
										  type === 'orders' ? 'order' :
										  type === 'customers' ? 'customer' : type
								});
							});
						}
					});
				}

				// Combine menu and API results
				setResults([...menuResults, ...apiResults]);
			}
		} catch (error) {
			if (error.name !== 'AbortError') {
				console.error('Search error:', error);
			}
		} finally {
			setIsLoading(false);
		}
	};

	// Debounced search
	useEffect(() => {
		if (searchTimeoutRef.current) {
			clearTimeout(searchTimeoutRef.current);
		}

		searchTimeoutRef.current = setTimeout(() => {
			performSearch(query);
		}, 200);

		return () => {
			if (searchTimeoutRef.current) {
				clearTimeout(searchTimeoutRef.current);
			}
		};
	}, [query]);

	// Handle result click
	const handleResultClick = (result) => {
		if (result.edit_url) {
			window.open(result.edit_url, '_blank');
		} else if (result.url) {
			window.open(result.url, '_blank');
		}
		setIsOpen(false);
	};

	// Close command bar
	const closeCommandBar = () => {
		setIsOpen(false);
		setQuery('');
		setResults([]);
	};

	if (!isOpen) return null;

	return (
		<div className="wppowerstack-palette-overlay" onClick={closeCommandBar}>
			<div className="wppowerstack-palette" onClick={(e) => e.stopPropagation()}>
				{/* Header */}
				<div className="wppowerstack-palette-header">
					<div className="wppowerstack-search-container">
						<input
							type="text"
							className="wppowerstack-search-input"
							placeholder={window.wppowerstack_data?.i18n?.placeholder || __('Type a command or search...', 'wpst-admin-search-bar-quick-actions-navigation')}
							value={query}
							onChange={(e) => setQuery(e.target.value)}
							autoFocus
						/>
						{isLoading && <div className="wppowerstack-loading-spinner"></div>}
					</div>
					<button className="wppowerstack-close-button" onClick={closeCommandBar}>
						×
					</button>
				</div>

				{/* Results */}
				<div className="wppowerstack-results-container">
					{results.length === 0 && !isLoading && query && (
						<div className="wppowerstack-no-results">
							{window.wppowerstack_data?.i18n?.no_results || __('No results found.', 'wpst-admin-search-bar-quick-actions-navigation')}
						</div>
					)}

					{results.map((result, index) => (
						<div
							key={`${result.type}-${result.id || index}`}
							className="wppowerstack-result-item"
							onClick={() => handleResultClick(result)}
						>
							<div className="wppowerstack-result-icon">
								{result.image_url ? (
									<img src={result.image_url} alt={result.image_alt || result.title} />
								) : (
									<span className={`dashicons ${result.icon || 'dashicons-admin-generic'}`}></span>
								)}
							</div>
							<div className="wppowerstack-result-content">
								<div className="wppowerstack-result-title" dangerouslySetInnerHTML={{ __html: result.title }} />
								<div className="wppowerstack-result-description" dangerouslySetInnerHTML={{ __html: result.description }} />
							</div>
							<div className="wppowerstack-result-type">
								{result.type}
							</div>
						</div>
					))}
				</div>

				{/* Footer */}
				<div className="wppowerstack-palette-footer">
					<div className="wppowerstack-shortcuts">
						<span>↑↓ Navigate</span>
						<span>Enter Open</span>
						<span>ESC Close</span>
					</div>
				</div>
			</div>
		</div>
	);
};

// Mount the component when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
	const mountPoint = document.getElementById('wpst-admin-search-bar-root');
	if (mountPoint && window.wp && window.wp.element) {
		const { createElement } = window.wp.element;
		window.wp.element.createRoot(mountPoint).render(createElement(CommandBar));
	}
});

// Export for potential external use
window.WPPowerStack = { CommandBar };
