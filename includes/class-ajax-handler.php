<?php
/**
 * AJAX Handler Class
 *
 * Handles all AJAX requests for the plugin.
 *
 * @package Tidy_Admin_Menu
 * @since 1.0.0
 */

namespace Tidy_Admin_Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Ajax_Handler class.
 */
class Ajax_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_tidy_save_menu_order', array( $this, 'save_menu_order' ) );
		add_action( 'wp_ajax_tidy_save_hidden_items', array( $this, 'save_hidden_items' ) );
		add_action( 'wp_ajax_tidy_save_all_settings', array( $this, 'save_all_settings' ) );
		add_action( 'wp_ajax_tidy_save_settings', array( $this, 'save_settings' ) );
		add_action( 'wp_ajax_tidy_reset_menu', array( $this, 'reset_menu' ) );
		add_action( 'wp_ajax_tidy_export_config', array( $this, 'export_config' ) );
		add_action( 'wp_ajax_tidy_import_config', array( $this, 'import_config' ) );
	}

	/**
	 * Verify nonce and capability.
	 *
	 * @return bool True if valid, sends JSON error and dies if not.
	 */
	private function verify_request() {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'tidy_admin_menu_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'tidy-admin-menu' ) ) );
		}

		// Verify capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'tidy-admin-menu' ) ) );
		}

		return true;
	}

	/**
	 * Sanitize menu slug.
	 *
	 * @param string $slug Raw slug.
	 * @return string Sanitized slug.
	 */
	private function sanitize_menu_slug( $slug ) {
		// Menu slugs can contain special characters like ? and =.
		// We need to preserve these while still sanitizing.
		$slug = wp_unslash( $slug );
		$slug = sanitize_text_field( $slug );
		return $slug;
	}

	/**
	 * Get storage method based on settings.
	 *
	 * @return string 'option', 'user', or 'role'.
	 */
	private function get_storage_method() {
		$settings = get_option( 'tidy_admin_menu_settings', array() );
		$apply_to = isset( $settings['apply_to'] ) ? $settings['apply_to'] : 'all';

		if ( 'role' === $apply_to ) {
			return 'role';
		}

		return 'user' === $apply_to ? 'user' : 'option';
	}

	/**
	 * Save menu order.
	 */
	public function save_menu_order() {
		$this->verify_request();

		// Get and sanitize menu order.
		$order = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : array();

		if ( ! is_array( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data format.', 'tidy-admin-menu' ) ) );
		}

		// Sanitize each slug.
		$sanitized_order = array_map( array( $this, 'sanitize_menu_slug' ), $order );
		$sanitized_order = array_filter( $sanitized_order ); // Remove empty values.
		$sanitized_order = array_values( $sanitized_order ); // Re-index.

		// Save based on storage method.
		if ( 'user' === $this->get_storage_method() ) {
			update_user_meta( get_current_user_id(), 'tidy_admin_menu_order', $sanitized_order );
		} else {
			update_option( 'tidy_admin_menu_order', $sanitized_order, false ); // Don't autoload large arrays.
		}

		wp_send_json_success( array( 'message' => __( 'Menu order saved.', 'tidy-admin-menu' ) ) );
	}

	/**
	 * Save all settings (order and hidden) in one request.
	 */
	public function save_all_settings() {
		$this->verify_request();

		// Get and sanitize menu order.
		$order = isset( $_POST['order'] ) ? wp_unslash( $_POST['order'] ) : array();

		if ( ! is_array( $order ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data format.', 'tidy-admin-menu' ) ) );
		}

		// Sanitize order.
		$sanitized_order = array_map( array( $this, 'sanitize_menu_slug' ), $order );
		$sanitized_order = array_filter( $sanitized_order );
		$sanitized_order = array_values( $sanitized_order );

		// Get and sanitize hidden items.
		$hidden = isset( $_POST['hidden'] ) ? wp_unslash( $_POST['hidden'] ) : array();

		if ( ! is_array( $hidden ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data format.', 'tidy-admin-menu' ) ) );
		}

		// Sanitize hidden.
		$sanitized_hidden = array_map( array( $this, 'sanitize_menu_slug' ), $hidden );
		$sanitized_hidden = array_filter( $sanitized_hidden );
		$sanitized_hidden = array_values( $sanitized_hidden );

		$storage_method = $this->get_storage_method();

		// Save based on storage method.
		if ( 'role' === $storage_method ) {
			// Get the role being edited.
			$role = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';

			// Validate role exists.
			$configurable_roles = Menu_Manager::get_configurable_roles();
			if ( empty( $role ) || ! isset( $configurable_roles[ $role ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid role specified.', 'tidy-admin-menu' ) ) );
			}

			// Save role-specific config.
			$role_config = array(
				'order'  => $sanitized_order,
				'hidden' => $sanitized_hidden,
			);
			update_option( 'tidy_admin_menu_role_' . $role, $role_config, false );

		} elseif ( 'user' === $storage_method ) {
			update_user_meta( get_current_user_id(), 'tidy_admin_menu_order', $sanitized_order );
			update_user_meta( get_current_user_id(), 'tidy_admin_menu_hidden', $sanitized_hidden );
		} else {
			update_option( 'tidy_admin_menu_order', $sanitized_order, false );
			update_option( 'tidy_admin_menu_hidden', $sanitized_hidden, false );
		}

		// Save hide_collapse_menu setting (global setting, not per-role/user).
		$hide_collapse_menu = isset( $_POST['hide_collapse_menu'] ) && 'true' === $_POST['hide_collapse_menu'];
		$settings           = get_option( 'tidy_admin_menu_settings', array() );
		$settings['hide_collapse_menu'] = $hide_collapse_menu;
		update_option( 'tidy_admin_menu_settings', $settings, true );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'tidy-admin-menu' ) ) );
	}

	/**
	 * Save hidden items.
	 */
	public function save_hidden_items() {
		$this->verify_request();

		// Get and sanitize hidden items.
		$hidden = isset( $_POST['hidden'] ) ? wp_unslash( $_POST['hidden'] ) : array();

		if ( ! is_array( $hidden ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data format.', 'tidy-admin-menu' ) ) );
		}

		// Sanitize each slug.
		$sanitized_hidden = array_map( array( $this, 'sanitize_menu_slug' ), $hidden );
		$sanitized_hidden = array_filter( $sanitized_hidden );
		$sanitized_hidden = array_values( $sanitized_hidden );

		// Save based on storage method.
		if ( 'user' === $this->get_storage_method() ) {
			update_user_meta( get_current_user_id(), 'tidy_admin_menu_hidden', $sanitized_hidden );
		} else {
			update_option( 'tidy_admin_menu_hidden', $sanitized_hidden, false );
		}

		wp_send_json_success( array( 'message' => __( 'Hidden items saved.', 'tidy-admin-menu' ) ) );
	}

	/**
	 * Save general settings.
	 */
	public function save_settings() {
		$this->verify_request();

		$apply_to = isset( $_POST['apply_to'] ) ? sanitize_text_field( wp_unslash( $_POST['apply_to'] ) ) : 'all';

		// Validate apply_to value.
		if ( ! in_array( $apply_to, array( 'all', 'user', 'role' ), true ) ) {
			$apply_to = 'all';
		}

		// Get hide_collapse_menu setting.
		$hide_collapse_menu = isset( $_POST['hide_collapse_menu'] ) && 'true' === $_POST['hide_collapse_menu'];

		$settings = array(
			'apply_to'           => $apply_to,
			'hide_collapse_menu' => $hide_collapse_menu,
		);

		update_option( 'tidy_admin_menu_settings', $settings, true ); // Autoload settings.

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'tidy-admin-menu' ) ) );
	}

	/**
	 * Reset menu to default.
	 */
	public function reset_menu() {
		$this->verify_request();

		$storage_method = $this->get_storage_method();

		// Delete stored order and hidden items.
		if ( 'role' === $storage_method ) {
			// Get the role being reset.
			$role = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';

			// Validate role exists.
			$configurable_roles = Menu_Manager::get_configurable_roles();
			if ( empty( $role ) || ! isset( $configurable_roles[ $role ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid role specified.', 'tidy-admin-menu' ) ) );
			}

			delete_option( 'tidy_admin_menu_role_' . $role );

		} elseif ( 'user' === $storage_method ) {
			delete_user_meta( get_current_user_id(), 'tidy_admin_menu_order' );
			delete_user_meta( get_current_user_id(), 'tidy_admin_menu_hidden' );
		} else {
			delete_option( 'tidy_admin_menu_order' );
			delete_option( 'tidy_admin_menu_hidden' );
		}

		wp_send_json_success( array( 'message' => __( 'Menu reset to default.', 'tidy-admin-menu' ) ) );
	}

	/**
	 * Export configuration.
	 */
	public function export_config() {
		$this->verify_request();

		$settings = get_option( 'tidy_admin_menu_settings', array() );
		$apply_to = isset( $settings['apply_to'] ) ? $settings['apply_to'] : 'all';

		// Get the role being exported (if in role mode).
		$export_role = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';

		if ( 'role' === $apply_to && ! empty( $export_role ) ) {
			// Export specific role config.
			$role_config = get_option( 'tidy_admin_menu_role_' . $export_role, array() );
			$order       = isset( $role_config['order'] ) ? $role_config['order'] : array();
			$hidden      = isset( $role_config['hidden'] ) ? $role_config['hidden'] : array();
		} elseif ( 'user' === $apply_to ) {
			$order  = get_user_meta( get_current_user_id(), 'tidy_admin_menu_order', true );
			$hidden = get_user_meta( get_current_user_id(), 'tidy_admin_menu_hidden', true );
		} else {
			$order  = get_option( 'tidy_admin_menu_order', array() );
			$hidden = get_option( 'tidy_admin_menu_hidden', array() );
		}

		$config = array(
			'version'  => TIDY_ADMIN_MENU_VERSION,
			'settings' => $settings,
			'order'    => is_array( $order ) ? $order : array(),
			'hidden'   => is_array( $hidden ) ? $hidden : array(),
		);

		// Include role in export if applicable.
		if ( 'role' === $apply_to && ! empty( $export_role ) ) {
			$config['role'] = $export_role;
		}

		wp_send_json_success( $config );
	}

	/**
	 * Import configuration.
	 */
	public function import_config() {
		$this->verify_request();

		$config = isset( $_POST['config'] ) ? wp_unslash( $_POST['config'] ) : '';

		if ( empty( $config ) ) {
			wp_send_json_error( array( 'message' => __( 'No configuration data provided.', 'tidy-admin-menu' ) ) );
		}

		// Decode JSON.
		$data = json_decode( $config, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( array( 'message' => __( 'Invalid JSON format.', 'tidy-admin-menu' ) ) );
		}

		// Validate structure.
		if ( ! isset( $data['order'] ) || ! isset( $data['hidden'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid configuration format.', 'tidy-admin-menu' ) ) );
		}

		// Sanitize order.
		$order = array_map( array( $this, 'sanitize_menu_slug' ), (array) $data['order'] );
		$order = array_filter( $order );
		$order = array_values( $order );

		// Sanitize hidden.
		$hidden = array_map( array( $this, 'sanitize_menu_slug' ), (array) $data['hidden'] );
		$hidden = array_filter( $hidden );
		$hidden = array_values( $hidden );

		// Get the role to import into (if in role mode).
		$import_role    = isset( $_POST['role'] ) ? sanitize_text_field( wp_unslash( $_POST['role'] ) ) : '';
		$storage_method = $this->get_storage_method();

		// Save based on storage method.
		if ( 'role' === $storage_method && ! empty( $import_role ) ) {
			// Validate role exists.
			$configurable_roles = Menu_Manager::get_configurable_roles();
			if ( ! isset( $configurable_roles[ $import_role ] ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid role specified.', 'tidy-admin-menu' ) ) );
			}

			// Save role-specific config.
			$role_config = array(
				'order'  => $order,
				'hidden' => $hidden,
			);
			update_option( 'tidy_admin_menu_role_' . $import_role, $role_config, false );

		} elseif ( 'user' === $storage_method ) {
			update_user_meta( get_current_user_id(), 'tidy_admin_menu_order', $order );
			update_user_meta( get_current_user_id(), 'tidy_admin_menu_hidden', $hidden );
		} else {
			update_option( 'tidy_admin_menu_order', $order, false );
			update_option( 'tidy_admin_menu_hidden', $hidden, false );
		}

		// Import settings if provided (but only if not in role mode - role mode doesn't change global settings).
		if ( 'role' !== $storage_method && isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$apply_to = isset( $data['settings']['apply_to'] ) ? sanitize_text_field( $data['settings']['apply_to'] ) : 'all';
			if ( ! in_array( $apply_to, array( 'all', 'user', 'role' ), true ) ) {
				$apply_to = 'all';
			}
			update_option(
				'tidy_admin_menu_settings',
				array( 'apply_to' => $apply_to ),
				true
			);
		}

		wp_send_json_success( array( 'message' => __( 'Configuration imported successfully.', 'tidy-admin-menu' ) ) );
	}
}
