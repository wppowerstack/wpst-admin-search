/**
 * WPPowerStack Command Bar - Main React Component
 * 
 * A keyboard-first command palette for WordPress admin navigation
 * Launches with Cmd+K or Ctrl+K
 */

// CSS will be loaded via WordPress enqueue system
import { useState, useEffect, useRef } from '@wordpress/element';
// Note: Text domain updated to powerstack-admin-search-quick-navigation
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
	const [isApiLoading, setIsApiLoading] = useState(false);
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

	// Search functionality with proper instant menu display
	const performSearch = async (searchQuery) => {
		if (!searchQuery.trim()) {
			setResults([]);
			setIsLoading(false);
			return;
		}

		// Cancel previous request
		if (abortControllerRef.current) {
			abortControllerRef.current.abort();
		}

		// Create new abort controller
		abortControllerRef.current = new AbortController();

		try {
			// INSTANT: Show matching menu items immediately (zero-latency)
			const menuResults = fuse.search(searchQuery).map(result => ({
				...result.item,
				type: 'menu',
				score: result.score,
				isInstant: true // Mark as instant result
			}));

			// Show menu items instantly - NO loading spinner for instant results
			setResults(menuResults);
			setIsLoading(false); // Always false when showing instant results
			setIsApiLoading(true); // Show API loading indicator below instant results

			// BACKGROUND: Fetch additional results from API (non-blocking)
			// This happens in background while users can already see and interact with instant results
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
										  type === 'customers' ? 'customer' : type,
									isInstant: false // Mark as API result
								});
							});
						}
					});
				}

				// Append API results below instant results
				// Users see instant results immediately, then API results appear below
				setResults(prevResults => {
					// Filter out any duplicate items (same ID and type)
					const existingIds = new Set(prevResults.map(r => `${r.type}-${r.id}`));
					const uniqueApiResults = apiResults.filter(r => !existingIds.has(`${r.type}-${r.id}`));
					return [...prevResults, ...uniqueApiResults];
				});
			}
		} catch (error) {
			if (error.name !== 'AbortError') {
				console.error('Search error:', error);
			}
		} finally {
			setIsApiLoading(false); // Hide API loading indicator
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

					{results.map((result, index) => {
						// Check if this is the first API result (after instant results)
						const isFirstApiResult = !result.isInstant && (
							index === 0 || results[index - 1]?.isInstant
						);

						return (
							<React.Fragment key={`${result.type}-${result.id || index}`}>
								{/* Show separator before first API result */}
								{isFirstApiResult && (
									<div className="wppowerstack-results-separator">
										<span>Additional Results</span>
									</div>
								)}
								
								<div
									className={`wppowerstack-result-item ${result.isInstant ? 'wppowerstack-instant-result' : 'wppowerstack-api-result'}`}
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
										{result.isInstant && (
											<span className="wppowerstack-instant-badge">⚡</span>
										)}
									</div>
								</div>
							</React.Fragment>
						);
					})}

					{/* Show API loading indicator */}
					{isApiLoading && results.some(r => r.isInstant) && (
						<div className="wppowerstack-api-loading">
							<div className="wppowerstack-api-loading-spinner"></div>
							<span>Searching additional content...</span>
						</div>
					)}
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
