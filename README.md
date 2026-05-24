# WPPowerStack - Admin Search and Quick Navigation

A lightning-fast, keyboard-first command palette for WordPress admin navigation and WooCommerce search. Open with Cmd+K to search orders, products, and posts instantly.

## 🚀 Features

- **Keyboard-First Design**: Launch with Cmd+K or Ctrl+K from any admin screen
- **Zero-Latency Search**: Instant navigation with sub-100ms response times
- **WooCommerce Integration**: Search orders, products, and customers with HPOS compatibility
- **Fuzzy Search**: Powered by fuse.js for intelligent matching
- **Modern Tech Stack**: Built with WordPress React (@wordpress/element)
- **Lightweight**: Under 15KB total JavaScript bundle

## 🛠️ Tech Stack

- **Frontend**: React (@wordpress/element), fuse.js, SCSS
- **Build System**: WordPress @wordpress/scripts, Webpack
- **Backend**: PHP 7.4+, WordPress 5.8+
- **Compatibility**: WooCommerce 5.0+ (HPOS & Legacy)

## 📁 Project Structure

```
wppowerstack-command-bar/
├── assets/js/src/
│   ├── index.js              # Main React component
│   └── styles/
│       └── command-bar.scss  # SCSS styles
├── includes/
│   ├── class-wppowerstack-menu-parser.php      # Menu extraction
│   ├── class-wppowerstack-security.php         # Security layer
│   └── class-wppowerstack-content-search.php   # Search API
├── build/
│   ├── command-bar.js       # Compiled JavaScript
│   └── fuse.js              # Fuse.js library
├── wppowerstack-command-bar.php               # Main plugin file
├── package.json             # Dependencies and scripts
├── webpack.config.js        # Build configuration
└── readme.txt               # WordPress.org readme
```

## 🔧 Development

### Prerequisites
- Node.js 14+
- npm or yarn
- WordPress 5.8+
- WooCommerce 5.0+ (optional)

### Setup
```bash
# Clone the repository
git clone https://github.com/wppowerstack/admin-search-quick-navigation.git
cd admin-search-quick-navigation

# Install dependencies
npm install

# Build for production
npm run build

# Start development server
npm run start
```

### Available Scripts
```bash
npm run build      # Build production assets
npm run start      # Start development with hot reload
npm run dev        # Development mode
npm run lint:js    # Lint JavaScript
npm run lint:css   # Lint CSS/SCSS
npm run format     # Format code
npm run plugin-zip # Create WordPress plugin zip
```

## 🎯 Key Components

### React Command Bar (`assets/js/src/index.js`)
- Main React component with keyboard shortcuts
- Fuse.js integration for fuzzy search
- API integration for dynamic content
- Responsive design with dark theme support

### PHP Backend
- **Menu Parser**: Extracts WordPress admin menu structure
- **Security Layer**: Rate limiting, input sanitization, nonce verification
- **Search API**: REST endpoints for posts, pages, WooCommerce data

### Build System
- WordPress @wordpress/scripts for optimal compatibility
- Webpack configuration for React and SCSS compilation
- External WordPress dependencies to avoid bundling

## 🔒 Security Features

- Nonce verification on all API endpoints
- Rate limiting to prevent abuse
- Input sanitization and output escaping
- User capability checks
- HPOS-compatible WooCommerce queries

## 🌐 Browser Support

- Chrome 80+
- Firefox 75+
- Safari 13+
- Edge 80+

## 📝 License

GPL-2.0-or-later - See [LICENSE](LICENSE) file for details.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📄 WordPress.org

This plugin is submitted to the WordPress.org plugin repository.

**Plugin Page**: [Coming Soon]
**Support Forum**: [Coming Soon]

## 🔗 Links

- **WordPress.org**: https://wordpress.org/plugins/wppowerstack-command-bar
- **Documentation**: https://wppowerstack.com/docs
- **Support**: https://wppowerstack.com/support

---

Made with ❤️ by [WPPowerStack](https://wppowerstack.com)
