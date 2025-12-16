<?php
/**
 * Menu Manager Class
 *
 * Handles admin menu ordering, hiding, and separator management.
 *
 * @package Tidy_Admin_Menu
 * @since 1.0.0
 */

namespace Tidy_Admin_Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Menu_Manager class.
 */
class Menu_Manager {

	/**
	 * Cached menu order.
	 *
	 * @var array|null
	 */
	private $menu_order = null;

	/**
	 * Cached hidden items.
	 *
	 * @var array|null
	 */
	private $hidden_items = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Enable custom menu order only if user has configured one.
		add_filter( 'custom_menu_order', array( $this, 'should_use_custom_order' ) );

		// Apply custom menu order.
		add_filter( 'menu_order', array( $this, 'apply_menu_order' ), 999 );

		// Register custom separators.
		add_action( 'admin_menu', array( $this, 'register_separators' ), 998 );

		// Hide menu items.
		add_action( 'admin_menu', array( $this, 'hide_menu_items' ), 999 );

		// Add Show All toggle to admin menu.
		add_action( 'adminmenu', array( $this, 'render_show_all_toggle' ) );

		// Add body class for Show All state.
		add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );

		// Enqueue toggle script on all admin pages.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_toggle_assets' ) );

		// Add separator styles.
		add_action( 'admin_head', array( $this, 'separator_styles' ) );
	}

	/**
	 * Check if custom menu order should be used.
	 *
	 * Only returns true if the user has actually configured a menu order.
	 *
	 * @return bool Whether to use custom menu order.
	 */
	public function should_use_custom_order() {
		$order = $this->get_menu_order();
		return ! empty( $order );
	}

	/**
	 * Get menu order from options based on settings.
	 *
	 * @return array Menu order array.
	 */
	public function get_menu_order() {
		if ( null !== $this->menu_order ) {
			return $this->menu_order;
		}

		$settings = get_option( 'tidy_admin_menu_settings', array() );
		$apply_to = isset( $settings['apply_to'] ) ? $settings['apply_to'] : 'all';

		if ( 'role' === $apply_to ) {
			// Get role-specific config.
			$role = self::get_user_primary_role();
			if ( $role ) {
				$role_config = get_option( 'tidy_admin_menu_role_' . $role, array() );
				if ( ! empty( $role_config ) && isset( $role_config['order'] ) ) {
					$this->menu_order = $role_config['order'];
				}
			}
			// Fall back to global if no role config.
			if ( null === $this->menu_order ) {
				$this->menu_order = get_option( 'tidy_admin_menu_order', array() );
			}
		} elseif ( 'user' === $apply_to ) {
			$this->menu_order = get_user_meta( get_current_user_id(), 'tidy_admin_menu_order', true );
		} else {
			$this->menu_order = get_option( 'tidy_admin_menu_order', array() );
		}

		if ( ! is_array( $this->menu_order ) ) {
			$this->menu_order = array();
		}

		return $this->menu_order;
	}

	/**
	 * Get hidden items from options based on settings.
	 *
	 * @return array Hidden items array.
	 */
	public function get_hidden_items() {
		if ( null !== $this->hidden_items ) {
			return $this->hidden_items;
		}

		$settings = get_option( 'tidy_admin_menu_settings', array() );
		$apply_to = isset( $settings['apply_to'] ) ? $settings['apply_to'] : 'all';

		if ( 'role' === $apply_to ) {
			// Get role-specific config.
			$role = self::get_user_primary_role();
			if ( $role ) {
				$role_config = get_option( 'tidy_admin_menu_role_' . $role, array() );
				if ( ! empty( $role_config ) && isset( $role_config['hidden'] ) ) {
					$this->hidden_items = $role_config['hidden'];
				}
			}
			// Fall back to global if no role config.
			if ( null === $this->hidden_items ) {
				$this->hidden_items = get_option( 'tidy_admin_menu_hidden', array() );
			}
		} elseif ( 'user' === $apply_to ) {
			$this->hidden_items = get_user_meta( get_current_user_id(), 'tidy_admin_menu_hidden', true );
		} else {
			$this->hidden_items = get_option( 'tidy_admin_menu_hidden', array() );
		}

		if ( ! is_array( $this->hidden_items ) ) {
			$this->hidden_items = array();
		}

		return $this->hidden_items;
	}

	/**
	 * Apply custom menu order.
	 *
	 * @param array $menu_order Default menu order.
	 * @return array Modified menu order.
	 */
	public function apply_menu_order( $menu_order ) {
		$custom_order = $this->get_menu_order();

		if ( empty( $custom_order ) ) {
			return $menu_order;
		}

		// Start with custom order.
		$new_order = array();

		// Add items from custom order that exist in the menu.
		foreach ( $custom_order as $item ) {
			if ( in_array( $item, $menu_order, true ) || strpos( $item, 'separator' ) === 0 ) {
				$new_order[] = $item;
			}
		}

		// Add remaining items that weren't in custom order.
		// Skip empty slugs which can't be reliably ordered.
		foreach ( $menu_order as $item ) {
			if ( '' !== $item && ! in_array( $item, $new_order, true ) ) {
				$new_order[] = $item;
			}
		}

		return $new_order;
	}

	/**
	 * Register custom separators and reorder menu.
	 *
	 * WordPress only has separator1 and separator2 by default.
	 * We need to add custom separators AND reposition items in $menu.
	 */
	public function register_separators() {
		global $menu;

		$custom_order = $this->get_menu_order();

		if ( empty( $custom_order ) || ! is_array( $menu ) ) {
			return;
		}

		// Find all separator references in the order.
		$separators_needed = array();
		foreach ( $custom_order as $item ) {
			if ( preg_match( '/^separator(\d+)$/', $item, $matches ) ) {
				$num = (int) $matches[1];
				if ( $num > 2 ) { // separator1 and separator2 exist by default.
					$separators_needed[] = $item;
				}
			}
		}

		// Register any custom separators that don't exist.
		foreach ( $separators_needed as $separator ) {
			$exists = false;
			foreach ( $menu as $menu_item ) {
				if ( isset( $menu_item[2] ) && $menu_item[2] === $separator ) {
					$exists = true;
					break;
				}
			}

			if ( ! $exists ) {
				// Add separator at a high position temporarily.
				$position        = 500 + (int) filter_var( $separator, FILTER_SANITIZE_NUMBER_INT );
				$menu[ $position ] = array( '', 'read', $separator, '', 'wp-menu-separator' );
			}
		}

		// Rebuild menu array with correct positions based on custom order.
		$new_menu     = array();
		$position     = 1;
		$menu_by_slug = array();

		// Index menu items by slug for quick lookup.
		// Skip items with empty slugs (e.g., ACF options pages without menu_slug).
		foreach ( $menu as $key => $item ) {
			if ( isset( $item[2] ) && '' !== $item[2] ) {
				$menu_by_slug[ $item[2] ] = $item;
			}
		}

		// Add items from custom order first.
		foreach ( $custom_order as $slug ) {
			if ( isset( $menu_by_slug[ $slug ] ) ) {
				$new_menu[ $position ] = $menu_by_slug[ $slug ];
				unset( $menu_by_slug[ $slug ] );
				$position++;
			}
		}

		// Add remaining items that weren't in custom order.
		foreach ( $menu_by_slug as $slug => $item ) {
			$new_menu[ $position ] = $item;
			$position++;
		}

		// Replace global menu.
		$menu = $new_menu;
	}

	/**
	 * Hide menu items based on settings.
	 */
	public function hide_menu_items() {
		global $menu;

		$hidden = $this->get_hidden_items();

		if ( empty( $hidden ) || ! is_array( $menu ) ) {
			return;
		}

		// Check for Show All toggle via cookie/session (client-side handles this).
		// The actual hiding is done via CSS when Show All is not active.
		// We add a data attribute to hidden items for CSS targeting.
		foreach ( $menu as $key => $item ) {
			// Skip items with empty slugs.
			if ( ! isset( $item[2] ) || '' === $item[2] ) {
				continue;
			}
			if ( in_array( $item[2], $hidden, true ) ) {
				// Add a class to mark as hidden by Tidy Admin Menu.
				if ( ! isset( $menu[ $key ][4] ) ) {
					$menu[ $key ][4] = '';
				}
				$menu[ $key ][4] .= ' tidy-hidden-item';
			}
		}
	}

	/**
	 * Render the Show All toggle button in admin menu.
	 *
	 * Uses JavaScript to append to the menu since the adminmenu hook
	 * fires after the menu element closes.
	 */
	public function render_show_all_toggle() {
		$hidden = $this->get_hidden_items();

		// Only show toggle if there are hidden items.
		if ( empty( $hidden ) ) {
			return;
		}

		$button_label = esc_attr__( 'Show hidden menu items', 'tidy-admin-menu' );
		$text_more    = esc_html__( 'More menu', 'tidy-admin-menu' );
		$text_less    = esc_html__( 'Less menu', 'tidy-admin-menu' );

		?>
		<script>
		(function() {
			var adminMenu = document.getElementById('adminmenu');
			if (adminMenu) {
				var li = document.createElement('li');
				li.id = 'tidy-show-all-toggle';
				li.className = 'tidy-show-all-wrapper';
				li.innerHTML = '<button type="button" class="tidy-show-all-btn" aria-pressed="false" aria-label="<?php echo $button_label; ?>" data-text-more="<?php echo $text_more; ?>" data-text-less="<?php echo $text_less; ?>">' +
					'<span class="tidy-toggle-icon">+</span>' +
					'<span class="tidy-toggle-text"><?php echo $text_more; ?></span>' +
					'</button>';
				adminMenu.appendChild(li);
			}
		})();
		</script>
		<?php
	}

	/**
	 * Add body class for Show All state.
	 *
	 * @param string $classes Body classes.
	 * @return string Modified body classes.
	 */
	public function add_body_class( $classes ) {
		// The actual toggle state is handled by JavaScript.
		// This just ensures the base class is available.
		return $classes;
	}

	/**
	 * Output inline styles for admin menu separators.
	 */
	public function separator_styles() {
		?>
		<style>
		#adminmenu li.wp-menu-separator {
			padding: 0;
			margin: 6px 0;
		}
		#adminmenu div.separator {
			height: 1px;
			padding: 0;
			border-top: 1px solid #555;
		}
		</style>
		<?php
	}

	/**
	 * Enqueue assets for the Show All toggle.
	 */
	public function enqueue_toggle_assets() {
		$hidden = $this->get_hidden_items();

		// Only load if there are hidden items.
		if ( empty( $hidden ) ) {
			return;
		}

		wp_enqueue_style(
			'tidy-admin-menu-toggle',
			TIDY_ADMIN_MENU_URL . 'admin/css/toggle.css',
			array(),
			TIDY_ADMIN_MENU_VERSION
		);

		wp_enqueue_script(
			'tidy-admin-menu-toggle',
			TIDY_ADMIN_MENU_URL . 'admin/js/toggle.js',
			array(),
			TIDY_ADMIN_MENU_VERSION,
			true
		);
	}

	/**
	 * Get menu items that have empty slugs and cannot be managed.
	 *
	 * @return array Array of menu item titles that have empty slugs.
	 */
	public static function get_unmanageable_menu_items() {
		global $menu;

		$unmanageable = array();

		if ( ! is_array( $menu ) ) {
			return $unmanageable;
		}

		foreach ( $menu as $item ) {
			// Skip completely empty items and separators.
			if ( empty( $item[0] ) && empty( $item[2] ) ) {
				continue;
			}

			$slug  = isset( $item[2] ) ? $item[2] : '';
			$title = isset( $item[0] ) ? $item[0] : '';
			$class = isset( $item[4] ) ? $item[4] : '';

			// Skip separators.
			if ( strpos( $class, 'wp-menu-separator' ) !== false ) {
				continue;
			}

			// Check for empty slug with a title (actual menu item, not separator).
			if ( '' === $slug && '' !== $title ) {
				// Clean up the title for display.
				$title = preg_replace( '/[\s\xC2\xA0]?<span[^>]*>.*?<\/span>/su', '', $title );
				$title = preg_replace( '/<br\s*\/?>/i', ' ', $title );
				$title = wp_strip_all_tags( $title );
				$title = trim( $title );

				if ( '' !== $title ) {
					$unmanageable[] = $title;
				}
			}
		}

		return $unmanageable;
	}

	/**
	 * Get all current menu items for the settings page.
	 *
	 * @param string $for_role Optional. Filter items by what this role can access.
	 * @return array Menu items with slug, title, and icon.
	 */
	public static function get_all_menu_items( $for_role = '' ) {
		global $menu;

		$items = array();

		if ( ! is_array( $menu ) ) {
			return $items;
		}

		// Get role capabilities if filtering by role.
		$role_caps = array();
		if ( ! empty( $for_role ) ) {
			$role_obj = get_role( $for_role );
			if ( $role_obj ) {
				$role_caps = array_keys( array_filter( $role_obj->capabilities ) );
			}
		}

		foreach ( $menu as $position => $item ) {
			// Skip empty items.
			if ( empty( $item[0] ) && empty( $item[2] ) ) {
				continue;
			}

			$slug       = isset( $item[2] ) ? $item[2] : '';
			$title      = isset( $item[0] ) ? $item[0] : '';
			$capability = isset( $item[1] ) ? $item[1] : '';

			// Skip items with empty slugs (e.g., ACF options pages without menu_slug).
			// These items can't be reliably reordered since they have no identifier.
			if ( '' === $slug ) {
				continue;
			}

			// Filter by role capability if specified.
			if ( ! empty( $for_role ) && ! empty( $capability ) ) {
				// Check if role has this capability.
				if ( ! in_array( $capability, $role_caps, true ) ) {
					continue;
				}
			}

			// Remove notification bubbles (e.g., "Comments 5" -> "Comments").
			// Match optional space (regular or non-breaking) before span tags.
			$title = preg_replace( '/[\s\xC2\xA0]?<span[^>]*>.*?<\/span>/su', '', $title );
			// Replace <br> tags with spaces before stripping (ACF options pages use <br> in menu titles).
			$title = preg_replace( '/<br\s*\/?>/i', ' ', $title );
			$title = wp_strip_all_tags( $title );
			$title = trim( $title );
			$icon  = isset( $item[6] ) ? $item[6] : '';
			$class = isset( $item[4] ) ? $item[4] : '';

			// Check if it's a separator.
			$is_separator = strpos( $class, 'wp-menu-separator' ) !== false;

			$items[] = array(
				'slug'         => $slug,
				'title'        => $title,
				'icon'         => $icon,
				'position'     => $position,
				'is_separator' => $is_separator,
			);
		}

		return $items;
	}

	/**
	 * Get the current user's primary (highest-privilege) role.
	 *
	 * For users with multiple roles, returns the role with the most capabilities.
	 *
	 * @return string|null Role slug or null if no roles.
	 */
	public static function get_user_primary_role() {
		$user = wp_get_current_user();

		if ( ! $user->exists() || empty( $user->roles ) ) {
			return null;
		}

		$roles = $user->roles;

		// If only one role, return it.
		if ( count( $roles ) === 1 ) {
			return reset( $roles );
		}

		// Sort by capability count (highest = most privileged).
		$role_caps = array();
		foreach ( $roles as $role_slug ) {
			$role_obj = get_role( $role_slug );
			$role_caps[ $role_slug ] = $role_obj ? count( array_filter( $role_obj->capabilities ) ) : 0;
		}

		arsort( $role_caps );
		return array_key_first( $role_caps );
	}

	/**
	 * Get all roles that have admin menu access.
	 *
	 * Returns roles that have the 'read' capability, which is required
	 * to access the WordPress admin area. Sorted by capability count (highest first).
	 *
	 * @return array Associative array of role_slug => role_name.
	 */
	public static function get_configurable_roles() {
		$all_roles    = wp_roles()->roles;
		$configurable = array();

		// Build array with capability counts for sorting.
		$role_data = array();
		foreach ( $all_roles as $role_slug => $role_info ) {
			// Check if role can access admin (has 'read' capability).
			if ( ! empty( $role_info['capabilities']['read'] ) ) {
				$role_data[ $role_slug ] = array(
					'name'      => translate_user_role( $role_info['name'] ),
					'cap_count' => count( array_filter( $role_info['capabilities'] ) ),
				);
			}
		}

		// Sort by capability count (highest first).
		uasort(
			$role_data,
			function ( $a, $b ) {
				return $b['cap_count'] - $a['cap_count'];
			}
		);

		// Extract just the names.
		foreach ( $role_data as $role_slug => $data ) {
			$configurable[ $role_slug ] = $data['name'];
		}

		return $configurable;
	}

	/**
	 * Get standard WordPress roles for the role tabs UI.
	 *
	 * Returns the 6 standard WP roles in order of privilege, with info about
	 * whether each role has users and can access admin.
	 *
	 * @return array Array of role data with slug, name, has_users, and can_admin keys.
	 */
	public static function get_standard_roles_for_tabs() {
		// Standard WordPress roles in order of privilege.
		$standard_roles = array(
			'administrator' => __( 'Administrator', 'tidy-admin-menu' ),
			'editor'        => __( 'Editor', 'tidy-admin-menu' ),
			'author'        => __( 'Author', 'tidy-admin-menu' ),
			'contributor'   => __( 'Contributor', 'tidy-admin-menu' ),
			'subscriber'    => __( 'Subscriber', 'tidy-admin-menu' ),
		);

		// Add Super Admin for multisite.
		if ( is_multisite() ) {
			$standard_roles = array_merge(
				array( 'super_admin' => __( 'Super Admin', 'tidy-admin-menu' ) ),
				$standard_roles
			);
		}

		$all_roles = wp_roles()->roles;
		$result    = array();

		foreach ( $standard_roles as $role_slug => $role_name ) {
			// Check if role exists and can access admin.
			$can_admin = false;
			if ( isset( $all_roles[ $role_slug ] ) ) {
				$can_admin = ! empty( $all_roles[ $role_slug ]['capabilities']['read'] );
			}

			// Super Admin is special - check differently.
			if ( 'super_admin' === $role_slug ) {
				$can_admin = true;
			}

			// Count users with this role using direct query for reliability.
			$user_count = 0;
			if ( 'super_admin' === $role_slug ) {
				// Count super admins.
				$super_admins = get_super_admins();
				$user_count   = count( $super_admins );
			} else {
				// Use get_users() for accurate count.
				$users_with_role = get_users(
					array(
						'role'   => $role_slug,
						'fields' => 'ID',
					)
				);
				$user_count      = count( $users_with_role );
			}

			$result[ $role_slug ] = array(
				'slug'      => $role_slug,
				'name'      => $role_name,
				'has_users' => $user_count > 0,
				'can_admin' => $can_admin,
			);
		}

		return $result;
	}
}
