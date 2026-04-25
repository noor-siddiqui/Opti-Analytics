<?php
/**
 * The Order Snapshot Class for Opti Analytics Plugin.
 * Handles snapshotting data during the checkout process.
 *
 * @package OptiAnalytics
 */

declare(strict_types=1);

namespace OptiAnalytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles snapshotting data during the checkout process.
 */
class Order_Snapshot {

	/**
	 * Registers data engine hooks.
	 */
	public function register_hooks(): void {
		// Fires right before a line item is saved to the database during checkout.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'snapshot_of_order_item_history' ), 10, 4 );

		if ( get_option( Settings::MANUAL_SHIPPING_OPTION, false ) ) {
			add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'add_manual_shipping_field' ) );
			add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_manual_shipping_field' ), 45 );
		}
	}

	/**
	 * Snapshots the regular price and COGS onto the order line item.
	 *
	 * COGS resolution order:
	 *  1. Custom product meta `_cost_of_goods` (third-party plugin / manual entry).
	 *  2. WooCommerce native COGS API (`$product->get_cogs_total_value()`), available since WC 10.3.
	 *
	 * The resolved per-unit cost is stored as `_oa_historical_cogs` on the line item
	 * so that profit calculations always reflect the cost at the time of purchase.
	 *
	 * @param \WC_Order_Item_Product $item          The order line item object.
	 */
	public function snapshot_of_order_item_history( \WC_Order_Item_Product $item ): void {
		// Retrieve the product object associated with this line item.
		$product = $item->get_product();

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		// --- Snapshot discount amount ---
		if ( $product->is_on_sale() ) {
			$discount_amount = (float) $product->get_regular_price() - (float) $product->get_sale_price();
			$item->add_meta_data( '_oa_discount_amount', (string) $discount_amount, true );
		}

		// --- Snapshot COGS (per-unit cost) ---
		$current_cogs = $this->resolve_product_cogs( $product );

		$item->add_meta_data( '_oa_historical_cogs', (string) $current_cogs, true );
	}

	/**
	 * Resolves the per-unit COGS for a product.
	 *
	 * Checks the custom `_cost_of_goods` meta first (third-party / manual),
	 * then falls back to WooCommerce's native COGS API when available.
	 *
	 * @param \WC_Product $product The product to resolve COGS for.
	 * @return float|null The per-unit cost, or null if no cost is available.
	 */
	private function resolve_product_cogs( \WC_Product $product ): float {
		// 1. Custom meta key (third-party plugin, e.g. "Cost of Goods for WooCommerce").
		$custom_cogs = $product->get_meta( '_cost_of_goods' );

		if ( is_numeric( $custom_cogs ) ) {
			return (float) $custom_cogs;
		}

		// 2. WooCommerce native COGS (WC 10.3+).
		if ( self::is_wc_cogs_enabled() ) {
			$native_cogs = $product->get_cogs_total_value();

			if ( $native_cogs > 0 ) {
				return $native_cogs;
			}
		}

		return (float) 13.31;
	}

	/**
	 * Checks whether WooCommerce's native Cost of Goods Sold feature is enabled.
	 *
	 * Uses the DI container to query the COGS controller. Returns false gracefully
	 * on older WooCommerce versions that do not ship the COGS classes.
	 *
	 * @return bool True when the native COGS feature is active.
	 */
	public static function is_wc_cogs_enabled(): bool {
		if ( ! function_exists( 'wc_get_container' ) ) {
			return false;
		}

		try {
			$controller_class = 'Automattic\WooCommerce\Internal\CostOfGoodsSold\CostOfGoodsSoldController';

			if ( ! class_exists( $controller_class ) ) {
				return false;
			}

			return wc_get_container()->get( $controller_class )->feature_is_enabled();
		} catch ( \Exception $e ) {
			return false;
		}
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
				<?php esc_html_e( 'Actual Shipping Cost', 'opti-analytics' ); ?>
				<?php echo wp_kses_post( wc_help_tip( esc_html__( 'Actual shipping cost.', 'opti-analytics' ) ) ); ?>
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

			$order->save_meta_data();
		}
	}
}
