<?php
/**
 * Plugin Name: Tidy Admin Menu
 * Plugin URI: https://github.com/dbrabyn/tidy-admin-menu
 * Description: Declutter your WordPress dashboard by sorting and hiding admin menu items with a simple Show All toggle.
 * Version: 1.0.16
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: David Brabyn
 * Author URI: https://9wdigital.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tidy-admin-menu
 * Domain Path: /languages
 *
 * @package Tidy_Admin_Menu
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'TIDY_ADMIN_MENU_FILE', __FILE__ );
define( 'TIDY_ADMIN_MENU_PATH', plugin_dir_path( __FILE__ ) );
define( 'TIDY_ADMIN_MENU_URL', plugin_dir_url( __FILE__ ) );
define( 'TIDY_ADMIN_MENU_BASENAME', plugin_basename( __FILE__ ) );

// Get version from plugin header (single source of truth).
$tidy_plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
define( 'TIDY_ADMIN_MENU_VERSION', $tidy_plugin_data['Version'] );

// Plugin Update Checker.
require_once TIDY_ADMIN_MENU_PATH . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$tidy_admin_menu_update_checker = PucFactory::buildUpdateChecker(
	'https://github.com/dbrabyn/tidy-admin-menu/',
	__FILE__,
	'tidy-admin-menu'
);

// Set the branch that contains the stable release.
$tidy_admin_menu_update_checker->setBranch( 'main' );

/**
 * Load plugin textdomain for translations.
 *
 * @since 1.0.0
 */
function tidy_admin_menu_load_textdomain() {
	load_plugin_textdomain(
		'tidy-admin-menu',
		false,
		dirname( TIDY_ADMIN_MENU_BASENAME ) . '/languages'
	);
}
add_action( 'init', 'tidy_admin_menu_load_textdomain' );

// Include required files.
require_once TIDY_ADMIN_MENU_PATH . 'includes/class-menu-manager.php';
require_once TIDY_ADMIN_MENU_PATH . 'includes/class-admin-settings.php';
require_once TIDY_ADMIN_MENU_PATH . 'includes/class-ajax-handler.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */
function tidy_admin_menu_init() {
	// Only load in admin.
	if ( ! is_admin() ) {
		return;
	}

	// Initialize classes.
	new Tidy_Admin_Menu\Menu_Manager();
	new Tidy_Admin_Menu\Admin_Settings();
	new Tidy_Admin_Menu\Ajax_Handler();
}
add_action( 'plugins_loaded', 'tidy_admin_menu_init' );

/**
 * Activation hook.
 *
 * @since 1.0.0
 */
function tidy_admin_menu_activate() {
	// Set default options if they don't exist.
	if ( false === get_option( 'tidy_admin_menu_settings' ) ) {
		add_option(
			'tidy_admin_menu_settings',
			array(
				'apply_to'    => 'all', // 'all', 'user', or 'role'.
				'target_role' => 'administrator',
			),
			'',
			'yes' // Autoload.
		);
	}
}
register_activation_hook( __FILE__, 'tidy_admin_menu_activate' );

/**
 * Add settings link to plugins page.
 *
 * @since 1.0.0
 * @param array $links Plugin action links.
 * @return array Modified action links.
 */
function tidy_admin_menu_plugin_links( $links ) {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( admin_url( 'options-general.php?page=tidy-admin-menu' ) ),
		esc_html__( 'Settings', 'tidy-admin-menu' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . TIDY_ADMIN_MENU_BASENAME, 'tidy_admin_menu_plugin_links' );

/**
 * Display admin notice if conflicting plugins are active.
 *
 * @since 1.0.4
 */
function tidy_admin_menu_conflict_notice() {
	// Only show to users who can manage plugins.
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	// List of known conflicting plugins: path => name.
	$conflicts = array(
		'wp-clean-admin-menu/wp-clean-admin-menu.php'  => 'WP Clean Admin Menu',
		'admin-menu-editor/menu-editor.php'            => 'Admin Menu Editor',
		'admin-menu-editor-pro/menu-editor.php'        => 'Admin Menu Editor Pro',
		'adminimize/adminimize.php'                    => 'Adminimize',
		'adminify/adminify.php'                        => 'WP Adminify',
		'jejedev-dashboard/jejedev-dashboard.php'         => 'Jejedev Dashboard',
		'jejedev-dashboard/jejedev-admin.php'          => 'Jejedev Dashboard',
		'white-label-cms/white-label-cms.php'          => 'White Label CMS',
	);

	$active_conflicts = array();

	foreach ( $conflicts as $plugin_path => $plugin_name ) {
		if ( is_plugin_active( $plugin_path ) ) {
			$active_conflicts[] = $plugin_name;
		}
	}

	if ( empty( $active_conflicts ) ) {
		return;
	}

	$plugin_list = implode( ', ', $active_conflicts );
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<strong><?php esc_html_e( 'Tidy Admin Menu:', 'tidy-admin-menu' ); ?></strong>
			<?php
				printf(
					/* translators: %s: list of conflicting plugin names */
					esc_html( _n(
						'The following plugin may conflict with admin menu ordering: %s. Consider deactivating it for best results.',
						'The following plugins may conflict with admin menu ordering: %s. Consider deactivating them for best results.',
						count( $active_conflicts ),
						'tidy-admin-menu'
					) ),
					'<strong>' . esc_html( $plugin_list ) . '</strong>'
				);
			?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'tidy_admin_menu_conflict_notice' );
