<?php
/**
 * Plugin Name:       Opti Analytics
 * Plugin URI:        https://github.com/noor-siddiqui/Opti-Analytics
 * Description:       A custom sales analytics plugin for WooCommerce supporting dynamic custom fields and Excel exports.
 * Version:           0.0.10-beta
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Noor Nabiul Alam Siddiqui
 * GitHub Plugin URI: https://github.com/noor-siddiqui/Opti-Analytics
 * Text Domain:       opti-analytics
 * Domain Path:       /languages
 *
 * @package OptiAnalytics
 * @author  Noor Nabiul Alam Siddiqui <siddiqui.sazal@gmail.com>
 * @license https://github.com/noor-siddiqui/Opti-Analytics?tab=GPL-3.0-1-ov-file GNU General Public License v3.0
 * @link    https://github.com/noor-siddiqui/Opti-Analytics
 * @since   0.0.1-beta
 */

// Enforce strict typing to catch errors early.
declare(strict_types=1);

// Define our namespace to avoid function/class name conflicts.
namespace OptiAnalytics;

// Security Best Practice: Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define helpful plugin constants for paths and URLs.
define( 'OPTI_ANALYTICS_VERSION', '0.0.10-beta' );
define( 'OPTI_ANALYTICS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPTI_ANALYTICS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Require Composer's autoloader.
if ( file_exists( OPTI_ANALYTICS_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once OPTI_ANALYTICS_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	add_action(
		'admin_notices',
		function () {
			?>
		<div class="notice notice-error is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Opti Analytics:', 'opti-analytics' ); ?></strong> 
				<?php esc_html_e( 'There is some error. Please raise and issue in Github. Link: https://github.com/noor-siddiqui/Opti-Analytics/issues', 'opti-analytics' ); ?>
			</p>
		</div>
			<?php
		}
	);
	return;
}

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Bootstraps the plugin.
 * We hook this to 'plugins_loaded' to ensure all other plugins (like WooCommerce) are loaded first.
 */
function init(): void {

	// Check if WooCommerce is installed and active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		// If not, show an error notice and stop loading our plugin.
		add_action( 'admin_notices', __NAMESPACE__ . '\missing_woocommerce_notice' );
		return;
	}

	// If we reach this point, WooCommerce is active! We can safely start our plugin.
	// NOTE: We must NOT gate this behind is_admin() because Order_Snapshot hooks
	// (e.g. woocommerce_checkout_create_order_line_item) fire during frontend checkout.
	$plugin_core = new Core();
	$plugin_core->run();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\init' );

/**
 * Displays an admin notice if WooCommerce is not active.
 */
function missing_woocommerce_notice(): void {
	// Only show to users who can actually install/activate plugins.
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-error is-dismissible">
		<p>
			<strong><?php esc_html_e( 'Opti Analytics:', 'opti-analytics' ); ?></strong> 
			<?php esc_html_e( 'This plugin requires WooCommerce to be installed and active. Please activate WooCommerce to use Opti Analytics.', 'opti-analytics' ); ?>
		</p>
	</div>
	<?php
}

// Setup automatic plugin updates using Plugin Update Checker (PUC).
if ( file_exists( OPTI_ANALYTICS_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php' ) ) {
	include_once OPTI_ANALYTICS_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';

	try {
		$my_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/noor-siddiqui/Opti-Analytics',
			__FILE__,
			'opti-analytics'
		);
		/** @var \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\GitHubApi $vcs_api */
		$vcs_api = $my_update_checker->getVcsApi();
		if ( method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
			$vcs_api->enableReleaseAssets();
		}
	} catch ( \Exception $e ) {
		add_action(
			'admin_notices',
			function () {
				?>
	<div class="notice notice-success is-dismissible">
		<p>There is an error while Updating Opti Analytics. Please contact with admin.</p>
	</div>
				<?php
			}
		);
	}
}
