<?php
/**
 * The Core Class for Opti Analytics Plugin.
 *
 * @package OptiAnalytics
 */

declare(strict_types=1);

namespace OptiAnalytics;

// Security Best Practice: Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 * * This class is used to define internationalization, admin-specific hooks,
 * and public-facing site hooks.
 */
class Core {
	/**
	 * Instance of Dashboard class.
	 *
	 * @var Dashboard
	 */
	private Dashboard $dashboard;

	/**
	 * Instance of Settings class.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Instance of Order_Snapshot class.
	 *
	 * @var Order_Snapshot
	 */
	private Order_Snapshot $order_snapshot;

	/**
	 * Constructor for the core class.
	 */
	public function __construct() {
		$this->dashboard      = new Dashboard();
		$this->settings       = new Settings();
		$this->order_snapshot = new Order_Snapshot();
	}

	/**
	 * Execute the main logic of the plugin.
	 * This is where we will register all of our WordPress hooks (actions and filters).
	 */
	public function run(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		$this->dashboard->register_hooks();
		$this->settings->register_hooks();
		$this->order_snapshot->register_hooks();

		// Clear dashboard transient caches when order data changes.
		add_action( 'woocommerce_new_order', array( Data_Engine::class, 'clear_cache' ) );
		add_action( 'woocommerce_order_status_changed', array( Data_Engine::class, 'clear_cache' ) );
		add_action( 'woocommerce_update_order', array( Data_Engine::class, 'clear_cache' ) );
	}



	/**
	 * Enqueues common admin scripts and styles for the plugin.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( false === strpos( $hook, 'opti-analytics' ) ) {
			return;
		}

		wp_enqueue_style(
			'opti-analytics-admin',
			plugins_url( '../assets/css/opti.css', __FILE__ ),
			array(),
			'1.0.0'
		);
	}

	/**
	 * Registers the Opti Analytics menu in the WordPress admin.
	 */
	public function register_menu(): void {

		// Cache the base64-encoded SVG to avoid reading from disk on every admin page load.
		static $icon_data = null;
		if ( null === $icon_data ) {
			$icon_path = OPTI_ANALYTICS_PLUGIN_DIR . 'assets/img/menu_icon.svg';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Reading a local bundled SVG icon and base64 encoding it for the menu icon.
			$icon_data = 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( $icon_path ) );
		}

		add_menu_page(
			__( 'Opti Analytics', 'opti-analytics' ),
			__( 'Opti Analytics', 'opti-analytics' ),
			'manage_woocommerce',
			'opti-analytics',
			array( $this->dashboard, 'render_dashboard_page' ), // Pointed to our new Dashboard class!
			$icon_data,
			57
		);
	}
}
