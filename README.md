# Signature Media Silo Structure

A WordPress plugin for building an SEO‑friendly **silo content architecture**: custom post types, a shared service taxonomy, smart rewrite/query rules, a Rank Math–compatible “shadow” archive editor, and ACF integrations.

Lightweight, safe-by-default, and supports automatic updates via **GitHub Releases** (with optional license gating).

---

## At a Glance

- **CPTs**: `silo_service` (Sub Services), `silo_problem` (Problem Signs), `silo_solution` (Solutions), and `locations` (Locations Served).
- **Taxonomy**: `service_category` (hierarchical) shared by Services/Problems/Solutions.
- **Archives**: Per‑service archives for **Problem Signs** and **Solutions** with clean permalinks.
- **Shadow “Silo Archive” CPT**: `silo_archive` stores Rank Math SEO and ACF field content for each per‑service archive.
- **Admin UI**: Silo Settings (permalink style), Service Silos tools, and “Problem/Solutions ACF Fields” admin pages.
- **Permalinks**: Optional removal of the `/services/` prefix site‑wide, with automatic 301s in both directions.
- **Queries**: Main queries for service tax archives are scoped correctly and default to **9** items per page.
- **Updates**: GitHub Releases via Plugin Update Checker; optional license activation page (MWS).

---

## Requirements

- WordPress **5.8+**
- PHP **7.4+** (8.0+ recommended)
- Optional: Rank Math, Advanced Custom Fields (ACF)

---

## Installation

1) Download the latest release ZIP built with the top‑level folder **`signaturemedia-silo-structure/`**.  
2) WordPress → Plugins → Add New → Upload Plugin → choose the ZIP and **Activate**.  
3) On activation the plugin registers CPTs/Taxonomy, flushes permalinks, and sets `posts_per_page=9` if unset.

> **Note:** The Plugin Update Checker library must be present at `lib/plugin-update-checker/` if you want GitHub auto‑updates (see **Updates**).

---

## Content Model

### Custom Post Types
- **Sub Services**: `silo_service` – hierarchical, REST‑enabled. No built‑in archive; taxonomy pages act as archives.
- **Problem Signs**: `silo_problem` – hierarchical, REST‑enabled. Archive output handled by custom rewrite/query.
- **Solutions**: `silo_solution` – hierarchical, REST‑enabled. Archive output handled by custom rewrite/query.
- **Locations Served**: `locations` – hierarchical, REST‑enabled. Public, permalink base: `/service-area/...`.

### Shared Taxonomy
- **Service Categories**: `service_category` – hierarchical taxonomy used by Services/Problems/Solutions. Shows in admin and REST. Custom rewrite is handled by the plugin logic (see below).

---

## URLs & Rewrite Logic

The plugin installs rewrite tags and rules that produce clean, predictable URLs for your silo structure. Highlights:

- **Service Category base**: By default, URLs live under `/services/{service}/...`.  
- **Strip Base (optional)**: An admin toggle enables clean URLs like `/{service}/{post}`. When enabled, legacy `/services/...` requests 301 to the new structure; turning it off reverts gracefully (with reverse 301s).  
- **Archive types**: Each service category automatically gets two archive endpoints:  
  - **Problem Signs** → `/services/{service}/problem-signs/` (or `/{service}/problem-signs/` if base is stripped)  
  - **Solutions** → `/services/{service}/solutions/` (or `/{service}/solutions/`)  
- **Locations Served** CPT uses the slug **`service-area`**, e.g., `/service-area/{location}/`.

There’s also a dev utility to manually flush rules by visiting `?flush_rules=1` as an administrator.

---

## Query Behavior

- Frontend **main queries** for service category archives are scoped to the current term (no leaking of child terms on parent listings).  
- **Archive paginations** are limited to the site setting; on activation the plugin ensures `posts_per_page` is **9** by default.  
- Special handling ensures the Problem Signs/Solutions endpoints behave as true CPT archives for **`silo_problem`** and **`silo_solution`** respectively.

---

## Shadow “Silo Archive” CPT (Rank Math + ACF)

To make SEO and content editing comfortable, the plugin registers a **shadow** CPT named `silo_archive` that acts as a container for each service archive:

- Each shadow post links a **Service Category** + **Archive Type** (Problem Signs or Solutions).
- Rank Math’s **Title/Description/Robots** can be edited on the shadow post and are applied dynamically to the matching archive.  
- You can attach **ACF** fields to `silo_archive` and render them on the archive templates.  
- The shadow CPT is not publicly routed (no frontend single), but it’s visible in the admin for editing and auditing.

---

## Admin UI

- **Service Silos**: overview and handy links for working with each service’s archives.
- **Silo Settings**: checkbox to strip `/services/` from URLs globally (with safe redirects).
- **Problem Signs ACF Fields** and **Solutions ACF Fields** pages: pick a Service Category and edit its ACF flexible content for the respective archive. Includes **Preview** links to the live archives.

---

## Template Hints

The plugin resolves archive templates in this order (use these in your theme as needed):

- `archive-silo_problem.php` → Problem Signs archives  
- `archive-silo_solution.php` → Solutions archives  
- Falls back to `archive.php` then `index.php`

For taxonomy listings, provide a `taxonomy-service_category.php` or use conditional logic to render sections for services/problems/solutions.

---

## Updates (GitHub Releases)

The plugin uses **Plugin Update Checker**. To enable:

1) Ensure `lib/plugin-update-checker/plugin-update-checker.php` exists inside the plugin.  
2) Create a GitHub tag (e.g., `v2.1.0`) and a **Release** with a ZIP whose **top‑level folder name is `signaturemedia-silo-structure/`**.  
3) Bump the `Version:` in `signaturemedia-silo-structure.php`.  
4) (Optional) Define `SM_SILO_GH_TOKEN` in `wp-config.php` to raise GitHub API limits.  

> Sites can opt into “Enable auto‑updates” from the Plugins screen; the updater will then fetch new releases automatically.

### Constants (optional)
```php
define( 'SM_SILO_GH_USER', 'payche011' );
define( 'SM_SILO_GH_REPO', 'signaturemedia-silo-structure' );
// optional: force auto‑update logic in your own code if you want
define( 'SM_SILO_FORCE_AUTOUPDATE', true );
```

---

## Licensing (Optional)

Manage the license under **Settings → Signature Media License**.

- **Add/Change license:** Paste a license key and click **Activate** (or **Revalidate**).
- **Security:** The key is **encrypted at rest** in the database and **masked** in the UI.
- **Revalidate:** Leave the field empty and click the button to revalidate the stored key.
- **Remove license:** Check **“Remove stored license and deactivate”** to clear the key and remotely deactivate it.
- **Updates:** GitHub-based updates work regardless of licensing; server-driven updates are optional and only used if enabled in code.

---

## Uninstall / Deactivation

Deactivation flushes rewrite rules. No custom tables are created by this plugin. Export taxonomy terms and posts if you plan to migrate.

---

## Support

Signature Media • https://signaturemedia.com/