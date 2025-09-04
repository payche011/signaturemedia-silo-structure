# Signature Media Silo Structure

**Signature Media Silo Structure** is a WordPress plugin that creates an SEO-friendly silo content architecture:
custom post types, service taxonomies, query/rewrite rules, a silo archive editor (Rank Math–friendly), and ACF integrations.

It’s lightweight, safe-by-default, and supports automatic updates via **GitHub Releases**.

---

## Features

- Custom Post Types for siloed content
- Custom Taxonomy (`service_category`) with clean permalinks
- Query and rewrite rules tuned for silo archives
- Silo Archive “shadow CPT” (works with Rank Math + ACF)
- Admin helpers for ACF (optional)
- License check (optional) via MWS License Server
- **Auto-update** via GitHub Releases (optional license gating)

---

## Requirements

- WordPress 5.8+
- PHP 7.4+ (8.0+ recommended)
- (Optional) Rank Math / ACF installed

---

## Installation

1. Download the latest release ZIP from the **Releases** page.
2. In **WordPress → Plugins → Add New → Upload Plugin**, upload the ZIP and activate.
3. The plugin will register post types and taxonomies and flush permalinks on activation.

> **Note:** If you are installing from a release asset, the ZIP **must** contain a top-level folder named `signaturemedia-silo-structure/`.

---

## Auto-Updates (GitHub Releases)

Auto-updates are built in. On every release:

- Bump the `Version:` header in `signaturemedia-silo-structure.php`
- Tag the repo `vX.Y.Z`
- Publish a GitHub Release for that tag and attach a ZIP asset where the **top-level folder is** `signaturemedia-silo-structure/`.

WordPress sites with this plugin installed will detect the new version and update (you can enable forced auto-update for this plugin in code if desired).

---

## Configuration (optional)

The plugin file defines these constants to enable GitHub updates:

```php
define( 'SM_SILO_GH_USER', 'payche011' );
define( 'SM_SILO_GH_REPO', 'signaturemedia-silo-structure' );
// optional: force auto-updates for this plugin
define( 'SM_SILO_FORCE_AUTOUPDATE', true );
