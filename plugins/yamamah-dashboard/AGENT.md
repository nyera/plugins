# Yamamah Dashboard Plugin Architecture & Integration Guide

This document serves as a guide for AI agents and developers working on the `yamamah-dashboard` plugin and its integration with compatible themes (like `eventhall-theme`, `med-rent-theme`, `villago-theme`).

## 1. Plugin Architecture

The plugin is structured as follows:
- `yamamah-dashboard.php`: Main plugin file. Handles initialization, text domain, asset loading, and **AJAX actions**.
- `includes/class-yamamah-admin.php`: Core admin class. Handles menu registration (`register_menu()`) and rendering methods for all dashboard pages.
- `templates/layout.php`: The main layout wrapper for all dashboard pages (handles sidebar, header, dark mode).
- `templates/pages/*.php`: Individual page templates. They generally use `ob_start()` at the beginning, store output in `$page_content`, and then `include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';` at the end.

## 2. Adding a New Settings Page

To add a new page to the dashboard:
1. **Register the Menu**: In `includes/class-yamamah-admin.php`, inside the `register_menu()` method, add an entry to the `$subpages` array.
   ```php
   array( 'yamamah-dashboard', 'Page Title', 'yamamah-page-slug', 'render_page_method_name' )
   ```
2. **Create the Render Method**: In `class-yamamah-admin.php`, create the method (e.g., `render_page_method_name()`). Inside this method, retrieve any necessary options or `ydash_*` filters and `include` the template file.
3. **Create the Template**: Create `templates/pages/your-page-slug.php`. It should look like:
   ```php
   <?php
   if ( ! defined( 'ABSPATH' ) ) exit;
   ob_start();
   ?>
   <div class="page active" id="page-your-page-slug">
       <!-- Your HTML/UI here -->
   </div>
   <?php
   $page_content = ob_get_clean();
   include YAMAMAH_PLUGIN_DIR . 'templates/layout.php';
   ```
4. **AJAX Handlers**: If the page needs to save data, add the JS in the template and add the corresponding `wp_ajax_*` hook in `yamamah-dashboard.php`.

## 3. The Theme Bridge (`ydash_*` Hook System)

The plugin and theme communicate exclusively through a set of predefined `ydash_*` hooks.
- **Filters (`apply_filters`)**: The plugin calls these to read settings, manifests, or defaults from the active theme.
- **Actions (`do_action`)**: The plugin calls these to tell the theme to save data (often into theme-specific options).

**Important Rule**: The dashboard plugin should *rarely* save theme-specific content (like homepage hero text or site colors) into its own options. Instead, it fires a `do_action('ydash_save_something', $data)`, and the active theme's `inc/ydash-bridge.php` listens to that action and saves it to its own `get_option()` key.

**Shared Plugin Options**: Settings that are independent of the theme (like `yamamah_api_keys`, `yamamah_store_name`, `yamamah_smtp_*`, `yamamah_floating_call_number`, `yamamah_social_links`) are saved directly by the plugin using `update_option()`. Themes can read these via `get_option()` if needed, or via bridge hooks (see §5 for social links).

### Common `ydash_*` Hooks
- `ydash_theme_manifest` (filter): Gets theme capabilities, supported fields, default colors.
- `ydash_get_homepage_settings` (filter) / `ydash_save_homepage_settings` (action): For editable homepage content.
- `ydash_get_contact_page_settings` (filter) / `ydash_save_contact_page_settings` (action): For editable contact page content.
- `ydash_blog_page_manifest` (filter): Defines blog page fields for themes that support a blog archive. Empty array = no «المدونة» menu item.
- `ydash_get_blog_page_settings` (filter) / `ydash_save_blog_page_settings` (action): Read/save blog page content. The dashboard calls `yamamah_theme_has_blog_settings()` which checks whether `ydash_blog_page_manifest` is non-empty.
- Blog **posts** (add/edit/delete) are managed via AJAX on the blog settings page (`yamamah_save_blog_post`, `yamamah_delete_blog_post`) as standard WordPress `post` entries. Page sync uses `do_action( 'ydash_sync_pages' )` — themes should hook their `*_setup_pages()` to this action.
- Blog **categories** use the WordPress `category` taxonomy on the same page (`yamamah_save_blog_category`, `yamamah_delete_blog_category`).
- `ydash_get_floating_buttons` (filter): Theme reads floating buttons config from plugin.

## 4. Working with Theme Manifests
Pages like "Home Content" or "Contact Content" rely on a manifest. The plugin template (e.g., `content-contact.php`) asks the theme (via a filter like `ydash_contact_page_manifest`) what fields are available (title, description, image, etc.), and renders the form dynamically. When saved, it sends all POST data back to the theme via an action.

## 5. Social media links (footer)

**Option:** `yamamah_social_links` — shared across all themes (store-level). Saved from **إعدادات المظهر → تواصل معنا** via AJAX `yamamah_save_contact_page_settings` (field group `social` in POST; the admin UI uses inputs `yamamah_social[platform_key]`).

**Shape:** associative array `platform_key => url` (only non-empty URLs are stored). Keys are normalized with `sanitize_key()`.

**Supported platform keys (fixed set):** `x`, `instagram`, `facebook`, `tiktok`, `snapchat`, `linkedin`, `youtube`.

**Reading in themes:** Prefer `yamamah_get_ordered_social_links()` (defined in `yamamah-dashboard.php`) which returns the same keys in display order and drops empty URLs. Each theme’s footer should **only output an icon/link for keys present** in that array (no placeholder `#` links).

**New themes:** Wire the footer social row to `yamamah_get_ordered_social_links()` (with `function_exists()` guard if the plugin may be inactive) and map each key to an icon + `esc_url()` for `href`, `target="_blank"` `rel="noopener noreferrer"`, and a translated `aria-label`.
