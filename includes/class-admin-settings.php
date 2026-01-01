<?php
/**
 * Admin Settings Class
 *
 * Handles the plugin settings page UI.
 *
 * @package Tidy_Admin_Menu
 * @since 1.0.0
 */

namespace Tidy_Admin_Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Admin_Settings class.
 */
class Admin_Settings {

	/**
	 * Settings page hook suffix.
	 *
	 * @var string
	 */
	private $page_hook;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add settings page under Settings menu.
	 */
	public function add_settings_page() {
		$this->page_hook = add_options_page(
			__( 'Tidy Admin Menu', 'tidy-admin-menu' ),
			__( 'Tidy Admin Menu', 'tidy-admin-menu' ),
			'manage_options',
			'tidy-admin-menu',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue assets for settings page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( $this->page_hook !== $hook ) {
			return;
		}

		// WordPress jQuery UI Sortable.
		wp_enqueue_script( 'jquery-ui-sortable' );

		// Plugin styles.
		wp_enqueue_style(
			'tidy-admin-menu-settings',
			TIDY_ADMIN_MENU_URL . 'admin/css/admin-settings.css',
			array(),
			TIDY_ADMIN_MENU_VERSION
		);

		// Plugin scripts.
		wp_enqueue_script(
			'tidy-admin-menu-settings',
			TIDY_ADMIN_MENU_URL . 'admin/js/admin-settings.js',
			array( 'jquery', 'jquery-ui-sortable' ),
			TIDY_ADMIN_MENU_VERSION,
			true
		);

		// Get current settings.
		$settings = get_option( 'tidy_admin_menu_settings', array() );
		$apply_to = isset( $settings['apply_to'] ) ? $settings['apply_to'] : 'all';

		// Get standard roles for tabs.
		$standard_roles = Menu_Manager::get_standard_roles_for_tabs();

		// Determine active role for editing.
		$active_role = '';
		if ( 'role' === $apply_to ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$active_role = isset( $_GET['role'] ) ? sanitize_text_field( wp_unslash( $_GET['role'] ) ) : '';
			// Validate role exists and has users.
			if ( empty( $active_role ) || ! isset( $standard_roles[ $active_role ] ) || ! $standard_roles[ $active_role ]['has_users'] ) {
				// Find first role with users.
				foreach ( $standard_roles as $role_slug => $role_data ) {
					if ( $role_data['has_users'] && $role_data['can_admin'] ) {
						$active_role = $role_slug;
						break;
					}
				}
			}
		}

		// Get config for current context.
		$config       = $this->get_current_config( $apply_to, $active_role );
		$menu_order   = $config['order'];
		$hidden_items = $config['hidden'];

		// Localize script.
		wp_localize_script(
			'tidy-admin-menu-settings',
			'tidyAdminMenu',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'tidy_admin_menu_nonce' ),
				'menuOrder'     => $menu_order,
				'hiddenItems'   => $hidden_items,
				'applyTo'       => $apply_to,
				'activeRole'    => $active_role,
				'standardRoles' => $standard_roles,
				'strings'       => array(
					'unsaved'            => __( 'Unsaved changes', 'tidy-admin-menu' ),
					'saving'             => __( 'Saving...', 'tidy-admin-menu' ),
					'saved'              => __( 'Saved', 'tidy-admin-menu' ),
					'error'              => __( 'Error saving. Please try again.', 'tidy-admin-menu' ),
					'separator'          => __( 'Separator', 'tidy-admin-menu' ),
					'noFileChosen'       => __( 'No file chosen', 'tidy-admin-menu' ),
					'confirmReset'       => __( 'Are you sure you want to reset the menu to default? This will restore the original WordPress menu order and show all hidden items.', 'tidy-admin-menu' ),
					'resetSuccess'       => __( 'Menu reset to default.', 'tidy-admin-menu' ),
					'resetError'         => __( 'Error resetting menu. Please try again.', 'tidy-admin-menu' ),
					'confirmSwitchRole'  => __( 'You have unsaved changes. Are you sure you want to switch roles? Your changes will be lost.', 'tidy-admin-menu' ),
					'selectFileToImport' => __( 'Please select a file to import.', 'tidy-admin-menu' ),
					'removeSeparator'    => __( 'Remove separator', 'tidy-admin-menu' ),
				),
			)
		);
	}

	/**
	 * Get menu order and hidden items based on current settings and active role.
	 *
	 * @param string $apply_to    The apply_to setting value.
	 * @param string $active_role The active role being edited (for role mode).
	 * @return array Array with 'order' and 'hidden' keys.
	 */
	private function get_current_config( $apply_to, $active_role = '' ) {
		$menu_order   = array();
		$hidden_items = array();

		if ( 'role' === $apply_to && ! empty( $active_role ) ) {
			$role_config  = get_option( 'tidy_admin_menu_role_' . $active_role, array() );
			$menu_order   = isset( $role_config['order'] ) ? $role_config['order'] : array();
			$hidden_items = isset( $role_config['hidden'] ) ? $role_config['hidden'] : array();
		} elseif ( 'user' === $apply_to ) {
			$menu_order   = get_user_meta( get_current_user_id(), 'tidy_admin_menu_order', true );
			$hidden_items = get_user_meta( get_current_user_id(), 'tidy_admin_menu_hidden', true );
		} else {
			$menu_order   = get_option( 'tidy_admin_menu_order', array() );
			$hidden_items = get_option( 'tidy_admin_menu_hidden', array() );
		}

		if ( ! is_array( $menu_order ) ) {
			$menu_order = array();
		}
		if ( ! is_array( $hidden_items ) ) {
			$hidden_items = array();
		}

		return array(
			'order'  => $menu_order,
			'hidden' => $hidden_items,
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		// Security check.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'tidy-admin-menu' ) );
		}

		// Get current settings first to determine role context.
		$settings = get_option( 'tidy_admin_menu_settings', array() );
		$apply_to = isset( $settings['apply_to'] ) ? $settings['apply_to'] : 'all';

		// Get standard roles for tabs.
		$standard_roles = Menu_Manager::get_standard_roles_for_tabs();

		// Determine active role for editing (first role with users by default).
		$active_role = '';
		if ( 'role' === $apply_to ) {
			// Check if role is specified in URL.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$active_role = isset( $_GET['role'] ) ? sanitize_text_field( wp_unslash( $_GET['role'] ) ) : '';
			// Validate role exists and has users.
			if ( empty( $active_role ) || ! isset( $standard_roles[ $active_role ] ) || ! $standard_roles[ $active_role ]['has_users'] ) {
				// Find first role with users.
				foreach ( $standard_roles as $role_slug => $role_data ) {
					if ( $role_data['has_users'] && $role_data['can_admin'] ) {
						$active_role = $role_slug;
						break;
					}
				}
			}
		}

		// Get menu items - filter by role if in role mode.
		$menu_items = Menu_Manager::get_all_menu_items( $active_role );

		// Check for unmanageable menu items (empty slugs).
		$unmanageable_items = Menu_Manager::get_unmanageable_menu_items();

		// Get config for current context.
		$config       = $this->get_current_config( $apply_to, $active_role );
		$menu_order   = $config['order'];
		$hidden_items = $config['hidden'];

		// Sort menu items by saved order if exists.
		if ( ! empty( $menu_order ) ) {
			usort(
				$menu_items,
				function ( $a, $b ) use ( $menu_order ) {
					$pos_a = array_search( $a['slug'], $menu_order, true );
					$pos_b = array_search( $b['slug'], $menu_order, true );

					// Items not in order go to end.
					if ( false === $pos_a ) {
						$pos_a = 9999;
					}
					if ( false === $pos_b ) {
						$pos_b = 9999;
					}

					return $pos_a - $pos_b;
				}
			);
		}

		?>
		<div class="wrap tidy-admin-menu-wrap">
			<h1><?php esc_html_e( 'Tidy Admin Menu', 'tidy-admin-menu' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Drag and drop menu items to reorder. Uncheck items to hide them from the admin menu.', 'tidy-admin-menu' ); ?></p>

			<?php if ( ! empty( $unmanageable_items ) ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							/* translators: %s: comma-separated list of menu item names */
							esc_html__( 'The following menu items cannot be reordered because they are missing a menu slug: %s. The plugin or theme registering these items should specify a menu_slug parameter.', 'tidy-admin-menu' ),
							'<strong>' . esc_html( implode( ', ', $unmanageable_items ) ) . '</strong>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<div class="tidy-admin-menu-container">
				<div class="tidy-menu-list-wrapper">
					<div class="tidy-role-tabs-wrapper<?php echo 'role' !== $apply_to ? ' tidy-tabs-hidden' : ''; ?>">
						<nav class="tidy-role-tabs nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Role configuration tabs', 'tidy-admin-menu' ); ?>">
							<?php foreach ( $standard_roles as $role_slug => $role_data ) : ?>
								<?php
								$is_active   = $active_role === $role_slug;
								$is_disabled = ! $role_data['has_users'] || ! $role_data['can_admin'];
								$tab_classes = 'nav-tab';
								if ( $is_active ) {
									$tab_classes .= ' nav-tab-active';
								}
								if ( $is_disabled ) {
									$tab_classes .= ' nav-tab-disabled';
								}
								?>
								<?php if ( $is_disabled ) : ?>
									<span class="<?php echo esc_attr( $tab_classes ); ?>"
										  data-role="<?php echo esc_attr( $role_slug ); ?>"
										  title="<?php esc_attr_e( 'No users with this role', 'tidy-admin-menu' ); ?>">
										<?php echo esc_html( $role_data['name'] ); ?>
									</span>
								<?php else : ?>
									<a href="<?php echo esc_url( add_query_arg( 'role', $role_slug ) ); ?>"
									   class="<?php echo esc_attr( $tab_classes ); ?>"
									   data-role="<?php echo esc_attr( $role_slug ); ?>">
										<?php echo esc_html( $role_data['name'] ); ?>
									</a>
								<?php endif; ?>
							<?php endforeach; ?>
						</nav>
					</div>

					<div class="tidy-toolbar">
						<label class="tidy-show-all-label">
							<input type="checkbox" id="tidy-show-all" <?php checked( empty( $hidden_items ) ); ?>>
							<?php esc_html_e( 'Show All Menu Items', 'tidy-admin-menu' ); ?>
						</label>
						<button type="button" id="tidy-add-separator" class="button">
							<span class="dashicons dashicons-minus"></span>
							<?php esc_html_e( 'Add Separator', 'tidy-admin-menu' ); ?>
						</button>
					</div>

					<ul id="tidy-menu-list" class="tidy-menu-list" role="listbox" aria-label="<?php esc_attr_e( 'Admin menu items', 'tidy-admin-menu' ); ?>">
						<?php foreach ( $menu_items as $item ) : ?>
							<?php
							$is_hidden = in_array( $item['slug'], $hidden_items, true );
							$item_id   = 'tidy-item-' . sanitize_title( $item['slug'] );
							?>
							<li class="tidy-menu-item<?php echo $item['is_separator'] ? ' tidy-separator-item' : ''; ?><?php echo $is_hidden ? ' tidy-is-hidden' : ''; ?>"
								data-slug="<?php echo esc_attr( $item['slug'] ); ?>"
								role="option"
								aria-selected="false"
								tabindex="0">

								<span class="tidy-drag-handle" aria-hidden="true">
									<span class="dashicons dashicons-menu"></span>
								</span>

								<?php if ( $item['is_separator'] ) : ?>
									<span class="tidy-item-content tidy-separator-content">
										<span class="tidy-separator-line"></span>
										<span class="tidy-separator-label"><?php esc_html_e( 'Separator', 'tidy-admin-menu' ); ?></span>
										<span class="tidy-separator-line"></span>
									</span>
									<button type="button" class="tidy-remove-separator button-link" aria-label="<?php esc_attr_e( 'Remove separator', 'tidy-admin-menu' ); ?>">
										<span class="dashicons dashicons-no-alt"></span>
									</button>
								<?php else : ?>
									<label class="tidy-item-content">
										<input type="checkbox"
											class="tidy-visibility-toggle"
											<?php checked( ! $is_hidden ); ?>
											aria-label="<?php
									/* translators: %s: Menu item title */
									echo esc_attr( sprintf( __( 'Show %s in menu', 'tidy-admin-menu' ), $item['title'] ) );
									?>">
										<?php if ( $item['icon'] && strpos( $item['icon'], 'dashicons-' ) === 0 ) : ?>
											<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>"></span>
										<?php elseif ( $item['icon'] && strpos( $item['icon'], 'data:' ) === 0 ) : ?>
											<img src="<?php echo esc_attr( $item['icon'] ); ?>" alt="" class="tidy-menu-icon">
										<?php elseif ( $item['icon'] ) : ?>
											<span class="dashicons dashicons-admin-generic"></span>
										<?php else : ?>
											<span class="dashicons dashicons-admin-generic"></span>
										<?php endif; ?>
										<span class="tidy-item-title"><?php echo esc_html( $item['title'] ); ?></span>
									</label>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>

					<p class="tidy-keyboard-hint">
						<span class="dashicons dashicons-info-outline"></span>
						<?php esc_html_e( 'Tip: Use keyboard arrows with Alt key to reorder items.', 'tidy-admin-menu' ); ?>
					</p>

					<div class="tidy-extra-options">
						<label>
							<input type="checkbox" id="tidy-hide-collapse-menu" <?php checked( ! empty( $settings['hide_collapse_menu'] ) ); ?>>
							<?php esc_html_e( 'Hide "Collapse menu" toggle', 'tidy-admin-menu' ); ?>
						</label>
					</div>
				</div>

				<div class="tidy-settings-panel">
					<h2><?php esc_html_e( 'Settings', 'tidy-admin-menu' ); ?></h2>

					<h3><?php esc_html_e( 'Export / Import', 'tidy-admin-menu' ); ?></h3>
					<p>
						<button type="button" id="tidy-export" class="button">
							<?php esc_html_e( 'Export Configuration', 'tidy-admin-menu' ); ?>
						</button>
					</p>
					<div class="tidy-import-section">
						<input type="file" id="tidy-import-file" class="tidy-import-file" accept=".json">
						<label for="tidy-import-file" class="button tidy-import-file-label">
							<?php esc_html_e( 'Choose File', 'tidy-admin-menu' ); ?>
						</label>
						<span class="tidy-import-filename"><?php esc_html_e( 'No file chosen', 'tidy-admin-menu' ); ?></span>
						<button type="button" id="tidy-import" class="button tidy-import-hidden">
							<?php esc_html_e( 'Import', 'tidy-admin-menu' ); ?>
						</button>
					</div>

					<hr>

					<fieldset>
						<legend><?php esc_html_e( 'Apply settings to:', 'tidy-admin-menu' ); ?></legend>
						<label>
							<input type="radio" name="tidy_apply_to" value="all" <?php checked( 'all', $apply_to ); ?>>
							<?php esc_html_e( 'All users', 'tidy-admin-menu' ); ?>
						</label>
						<label>
							<input type="radio" name="tidy_apply_to" value="role" <?php checked( 'role', $apply_to ); ?>>
							<?php esc_html_e( 'By role', 'tidy-admin-menu' ); ?>
						</label>
						<label>
							<input type="radio" name="tidy_apply_to" value="user" <?php checked( 'user', $apply_to ); ?>>
							<?php esc_html_e( 'Current user only', 'tidy-admin-menu' ); ?>
						</label>
					</fieldset>

					<hr>

					<div class="tidy-save-section">
						<button type="button" id="tidy-save-settings" class="button button-large" disabled>
							<?php esc_html_e( 'Save Settings', 'tidy-admin-menu' ); ?>
						</button>
						<span id="tidy-save-status" class="tidy-save-status" aria-live="polite"></span>
					</div>

					<hr>

					<div class="tidy-reset-section">
						<button type="button" id="tidy-reset-menu" class="button button-link-delete">
							<?php esc_html_e( 'Reset to Default', 'tidy-admin-menu' ); ?>
						</button>
						<p class="description"><?php esc_html_e( 'Restore the original WordPress menu order and show all hidden items.', 'tidy-admin-menu' ); ?></p>
					</div>

				</div>
			</div>
		</div>
		<?php
	}
}
