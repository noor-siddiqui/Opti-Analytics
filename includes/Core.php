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
	 * Constructor for the core class.
	 */
	public function __construct() {
		$this->dashboard = new Dashboard();
		$this->settings  = new Settings();
	}

	/**
	 * Execute the main logic of the plugin.
	 * This is where we will register all of our WordPress hooks (actions and filters).
	 */
	public function run(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		if ( get_option( Settings::MANUAL_SHIPPING_OPTION, false ) ) {
			add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'add_manual_shipping_field' ) );
			add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_manual_shipping_field' ), 45 );
		}

		$this->dashboard->register_hooks();
		$this->settings->register_hooks();
	}

	/**
	 * Adds the manual shipping field to the single order admin view.
	 *
	 * @param \WC_Order $order The WooCommerce order object.
	 */
	public function add_manual_shipping_field( $order ): void {
		$manual = $order->get_meta( '_opti_manual_shipping_cost' );
		?>
		<p class="form-field form-field-wide wc-order-data-row" style="width: 100%;">
			<label for="_opti_manual_shipping_cost" style="display: flex; align-items: center;">
				<?php esc_html_e( 'Manual Shipping Cost', 'opti-analytics' ); ?>
				<?php echo wp_kses_post( wc_help_tip( esc_html__( 'Actual shipping cost for profit calculation.', 'opti-analytics' ) ) ); ?>
			</label>
			<input type="number" step="0.01" min="0" id="_opti_manual_shipping_cost" name="_opti_manual_shipping_cost"
				value="<?php echo esc_attr( (string) $manual ); ?>"
				placeholder="<?php echo esc_attr( wc_format_localized_price( (string) $order->get_shipping_total() ) ); ?>" style="width: 100%;" />
		</p>
		<?php
	}

	/**
	 * Saves the manual shipping field when the order is saved.
	 * HPOS and non-HPOS compatible.
	 *
	 * @param int $order_id The ID of the order being saved.
	 */
	public function save_manual_shipping_field( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['_opti_manual_shipping_cost'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$value = sanitize_text_field( wp_unslash( $_POST['_opti_manual_shipping_cost'] ) );

			if ( '' !== $value && is_numeric( $value ) && 0 <= (float) $value ) {
				$order->update_meta_data( '_opti_manual_shipping_cost', $value );
			} else {
				$order->delete_meta_data( '_opti_manual_shipping_cost' );
			}

			// We must save meta data here since the hook processes post data.
			$order->save_meta_data();
		}
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
		add_menu_page(
			__( 'Opti Analytics', 'opti-analytics' ),
			__( 'Opti Analytics', 'opti-analytics' ),
			'manage_woocommerce',
			'opti-analytics',
			array( $this->dashboard, 'render_dashboard_page' ), // Pointed to our new Dashboard class!
			'dashicons-chart-bar',
			57
		);
	}
}
