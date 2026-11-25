# Changelog

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-25

### Added
- Daily or weekly stock report email with all low and out-of-stock products and variations.
- Dashboard widget, admin bar badge and a "Low stock" view in the products list.
- Uses WooCommerce's own per-product and store low stock thresholds.
- Report stays at the chosen local hour when the site time zone changes.
- "Save and send a report now" button.
- Hooks: `lsa_items`, `lsa_recipients`, `lsa_digest_subject`, `lsa_option_{key}`.
