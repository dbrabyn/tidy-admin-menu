=== Tidy Admin Menu ===
Contributors: dbrabyn
Tags: admin menu, menu order, hide menu, admin customization, tidy admin menu
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.19
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Declutter your WordPress dashboard by sorting and hiding admin menu items with a simple Show All toggle.

== Description ==

Tidy Admin Menu helps you organize your WordPress admin sidebar by letting you:

* **Drag and drop** menu items to reorder them
* **Hide** menu items you don't use
* **Add separators** between menu groups
* **Configure by role** - different menus for admins, editors, etc.
* **Show All toggle** to temporarily reveal hidden items

Perfect for cleaning up cluttered admin menus on sites with many plugins, or for simplifying the dashboard for clients.

= Features =

* **Visual Drag-Drop Interface** - Easily reorder menu items with mouse or keyboard
* **One-Click Hide/Show** - Check or uncheck items to control visibility
* **Custom Separators** - Add visual dividers between menu sections
* **Show All Toggle** - Sticky button in the sidebar to temporarily reveal all hidden items
* **Flexible Configuration** - Apply settings to all users, by role, or current user only
* **Role-Based Menus** - Configure different menu layouts for each user role
* **Export/Import** - Save and restore your menu configuration
* **Keyboard Accessible** - Full keyboard navigation with Alt+Arrow keys for reordering
* **Screen Reader Friendly** - ARIA labels and live regions for accessibility
* **Zero Bloat** - Minimal footprint, assets only load where needed

= How It Works =

1. Go to Settings → Tidy Admin Menu
2. Drag menu items to reorder them
3. Uncheck items to hide them
4. Click "Add Separator" to create visual dividers
5. Use the "Show All" button at the bottom of the sidebar to temporarily reveal hidden items

= Privacy =

This plugin:

* Does not collect any user data
* Does not use cookies or persistent storage
* Stores settings in your WordPress database only
* Checks GitHub for plugin updates (no personal data is transmitted)

== Installation ==

1. Upload the `tidy-admin-menu` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Tidy Admin Menu to configure

== Frequently Asked Questions ==

= Can I reorder submenus? =

Not in the current version. This plugin focuses on top-level menu items only. Submenu reordering may be added in a future release.

= Will this affect other users? =

By default, menu changes apply to all users. You can change this to "Current user only" or configure different menus "By role" in the settings panel.

= How do I undo changes? =

Click the "Reset to Default" button to restore the original WordPress menu order and show all hidden items.

= Can I use this on a multisite network? =

Yes, the plugin works on multisite. Each site has its own menu configuration.

= The Show All toggle isn't appearing =

The toggle only appears if you have hidden at least one menu item.

== Screenshots ==

1. Settings page with drag-drop interface
2. Show All toggle in the admin sidebar
3. Hidden items revealed with visual indicator

== Changelog ==

= 1.0.19 =
* Fixed admin menu scrolling when Show All toggle is active using fixed positioning on menu wrapper

= 1.0.16 =
* Fixed menu ordering not working for plugin-added menu items (e.g., custom admin pages registered via add_menu_page)

= 1.0.15 =
* Fixed toggle button covering menu items when admin menu is long and requires scrolling

= 1.0.14 =
* Fixed handling of menu items with empty slugs (e.g., ACF options pages without menu_slug)
* Added warning notice on settings page when menu items cannot be managed due to missing slugs

= 1.0.13 =
* Changed toggle button text from "More/Less" to "More menu/Less menu" for clarity

= 1.0.12 =
* New separators now added to top of menu list instead of bottom

= 1.0.11 =
* Fixed hidden item opacity not restoring after drag-drop reordering on settings page

= 1.0.10 =
* Changed "More" toggle to reset on page reload (no longer persists across navigation)
* Hidden menu items now show when visiting their page, then hide again when navigating away

= 1.0.9 =
* Added internationalization support with French translation
* Fixed hardcoded JavaScript strings for full i18n compatibility
* Added proper singular/plural forms for conflict warning messages

= 1.0.8 =
* Fixed fatal error on activation caused by missing Parsedown library files

= 1.0.7 =
* Fixed menu titles with HTML line breaks (e.g., ACF options pages) displaying without spaces

= 1.0.6 =
* Hide consecutive separators when all items between them are hidden

= 1.0.5 =
* Fixed menu item titles losing spaces when stripping notification bubbles

= 1.0.4 =
* Added Reset to Default button on settings page
* Added conflict detection warning for other admin menu plugins

= 1.0.3 =
* Fixed incorrect GitHub repository URL for plugin updates

= 1.0.2 =
* Fixed separators appearing on fresh installs before configuration

= 1.0.1 =
* Fixed visibility of light-colored SVG menu icons on settings page

= 1.0.0 =
* Initial release
