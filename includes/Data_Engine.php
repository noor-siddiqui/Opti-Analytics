<?php
/**
 * The Data Engine Class for Opti Analytics Plugin.
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
 * Handles the retrieval and formatting of WooCommerce order data.
 */
class Data_Engine {

	/**
	 * Retrieves a batch of orders.
	 *
	 * @param int $page  The current page of results.
	 * @param int $limit The number of orders to fetch per batch.
	 * @return array<int, \WC_Order> Array of WooCommerce order objects.
	 */
	public function get_orders( int $page = 1, int $limit = 50 ): array {
		$args = array(
			'limit'    => $limit,
			'paged'    => $page,
			'orderby'  => 'date',
			'order'    => 'DESC',
			'paginate' => false, // We only want the objects, not pagination HTML.
		);

		return wc_get_orders( $args );
	}

	/**
	 * Retrieves dashboard metrics for a given date range.
	 *
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date   End date in Y-m-d format.
	 * @return array<string, float|int> Array of metrics.
	 */
	public function get_dashboard_metrics( string $start_date, string $end_date ): array {
		$args = array(
			'date_created' => $start_date . ' 00:00:00...' . $end_date . ' 23:59:59',
			'limit'        => -1,
			'status'       => array( 'wc-completed', 'wc-processing' ),
		);

		$orders = wc_get_orders( $args );

		$metrics = array(
			'total_sales'             => 0.0,
			'net_sales'               => 0.0,
			'gross_sales'             => 0.0,
			'orders_count'            => count( $orders ),
			'products_sold'           => 0.0,
			'average_items_per_order' => 0.0,
			'shipping'                => 0.0,
			'out_of_stock'            => 0.0,
			'aov'                     => 0.0,
			'discounted_total'        => 0.0,
			'off_total'               => 0.0,
			'cogs'                    => 0.0,
			'actual_shipping_cost'    => 0.0,
			// P&L computed metrics.
			'total_revenue'           => 0.0,
			'total_costs'             => 0.0,
			'gross_profit'            => 0.0,
			'net_profit'              => 0.0,
			'profit_margin'           => 0.0,
		);

		// ── Load P&L configuration ──────────────────────────────────
		$revenue_builtins       = get_option( Settings::PNL_REVENUE_BUILTINS, array() );
		$cost_builtins          = get_option( Settings::PNL_COST_BUILTINS, array() );
		$revenue_order_fields   = Settings::parse_csv_option( Settings::PNL_REVENUE_ORDER_FIELDS );
		$revenue_product_fields = Settings::parse_csv_option( Settings::PNL_REVENUE_PRODUCT_FIELDS );
		$cost_order_fields      = Settings::parse_csv_option( Settings::PNL_COST_ORDER_FIELDS );
		$cost_product_fields    = Settings::parse_csv_option( Settings::PNL_COST_PRODUCT_FIELDS );

		if ( ! is_array( $revenue_builtins ) ) {
			$revenue_builtins = array();
		}
		if ( ! is_array( $cost_builtins ) ) {
			$cost_builtins = array();
		}

		// Merge all custom order-level fields (for aggregation in the loop).
		$all_order_fields = array_unique( array_merge( $revenue_order_fields, $cost_order_fields ) );

		// Merge all custom line-item-level fields.
		$all_product_fields = array_unique( array_merge( $revenue_product_fields, $cost_product_fields ) );

		// Initialize metrics for every custom field.
		foreach ( $all_order_fields as $field ) {
			$metrics[ $field ] = 0.0;
		}
		foreach ( $all_product_fields as $field ) {
			$metrics[ $field ] = 0.0;
		}

		// ── Aggregate per-order metrics ─────────────────────────────
		foreach ( $orders as $order ) {

			$total    = (float) $order->get_total();
			$tax      = (float) $order->get_total_tax();
			$shipping = (float) $order->get_shipping_total();
			$refunds  = (float) $order->get_total_refunded();
			$subtotal = (float) $order->get_subtotal();

			$metrics['total_sales']      += $total;
			$metrics['net_sales']        += ( $total - $tax - $shipping - $refunds );
			$metrics['gross_sales']      += $subtotal;
			$metrics['shipping']         += $shipping;
			$metrics['discounted_total'] += (float) $order->get_total_discount();

			$actual_shipping_cost = self::get_order_meta_value( $order, '_opti_manual_shipping_cost' );

			if ( is_numeric( $actual_shipping_cost ) ) {
				$metrics['actual_shipping_cost'] += (float) $actual_shipping_cost;
			} else {
				$actual_shipping_cost = self::get_order_meta_value( $order, '_sa_manual_shipping_csv' );
				if ( is_numeric( $actual_shipping_cost ) ) {
					$metrics['actual_shipping_cost'] += (float) $actual_shipping_cost;
				} else {
					$metrics['actual_shipping_cost'] += $shipping;
				}
			}

			// ── Per-item aggregation ────────────────────────────────
			foreach ( $order->get_items() as $item ) {

				$qty                       = $item->get_quantity();
				$metrics['products_sold'] += $qty;

				// COGS: per-item cost × quantity.
				// Use product-level COGS when available, otherwise order-level.
				// check if meta exists else return 0.
				if ( $item->meta_exists( '_oa_historical_cogs' ) ) {
					$per_item_cogs    = (float) $item->get_meta( '_oa_historical_cogs' );
					$metrics['cogs'] += $per_item_cogs * $qty;
				} elseif ( $item->meta_exists( '_historical_cogs' ) ) {
					$per_item_cogs    = (float) $item->get_meta( '_historical_cogs' );
					$metrics['cogs'] += $per_item_cogs * $qty;
				}

				// Aggregate custom line-item-level fields: value × quantity.
				foreach ( $all_product_fields as $field ) {
					$val = $item->get_meta( $field );
					if ( is_numeric( $val ) ) {
						$metrics[ $field ] += (float) $val * $qty;
					}
				}

				if ( $item->meta_exists( '_oa_discount_amount' ) ) {
					$discount_amount       = (float) $item->get_meta( '_oa_discount_amount' );
					$metrics['off_total'] += $discount_amount * $qty;
				}
			}

			// Aggregate custom order-level fields.
			foreach ( $all_order_fields as $field ) {
				$val = self::get_order_meta_value( $order, $field );
				if ( is_numeric( $val ) ) {
					$metrics[ $field ] += (float) $val;
				}
			}
		}

		// ── Out of stock products ───────────────────────────────────
		$args_oos                = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => '_stock_status',
					'value'   => 'outofstock',
					'compare' => '=',
				),
			),
			'fields'         => 'ids',
		);
		$oos_query               = new \WP_Query( $args_oos );
		$metrics['out_of_stock'] = $oos_query->found_posts;

		// ── Computed averages ───────────────────────────────────────
		if ( $metrics['orders_count'] > 0 ) {
			$metrics['average_items_per_order'] = $metrics['products_sold'] / $metrics['orders_count'];
			$metrics['aov']                     = $metrics['net_sales'] / $metrics['orders_count'];
		}

		// ── P&L Calculation ─────────────────────────────────────────
		// Sum selected built-in revenue sources.
		foreach ( $revenue_builtins as $key ) {
			if ( isset( $metrics[ $key ] ) ) {
				$metrics['total_revenue'] += $metrics[ $key ];
			}
		}
		// Add custom revenue order fields.
		foreach ( $revenue_order_fields as $field ) {
			$metrics['total_revenue'] += $metrics[ $field ] ?? 0.0;
		}
		// Add custom revenue product fields.
		foreach ( $revenue_product_fields as $field ) {
			$metrics['total_revenue'] += $metrics[ $field ] ?? 0.0;
		}

		// Sum selected built-in cost sources.
		foreach ( $cost_builtins as $key ) {
			if ( isset( $metrics[ $key ] ) ) {
				$metrics['total_costs'] += $metrics[ $key ];
			}
		}
		// Add custom cost order fields.
		foreach ( $cost_order_fields as $field ) {
			$metrics['total_costs'] += $metrics[ $field ] ?? 0.0;
		}
		// Add custom cost product fields.
		foreach ( $cost_product_fields as $field ) {
			$metrics['total_costs'] += $metrics[ $field ] ?? 0.0;
		}

		// Final P&L metrics.
		$metrics['gross_profit']  = $metrics['total_revenue'] - ( $metrics['cogs'] ?? 0.0 );
		$metrics['net_profit']    = $metrics['total_revenue'] - $metrics['total_costs'];
		$metrics['profit_margin'] = $metrics['total_revenue'] > 0
			? ( $metrics['net_profit'] / $metrics['total_revenue'] ) * 100
			: 0.0;

		return $metrics;
	}

	/**
	 * Retrieves a meta value from an order, checking both HPOS and legacy postmeta.
	 *
	 * Some plugins write directly to wp_postmeta even when HPOS is active.
	 * This helper ensures we capture values from both storage backends.
	 *
	 * @param \WC_Order $order    The order object.
	 * @param string    $meta_key The meta key to retrieve.
	 * @return mixed The meta value, or empty string if not found.
	 */
	public static function get_order_meta_value( \WC_Order $order, string $meta_key ) {
		// Try HPOS-aware CRUD method first.
		$val = $order->get_meta( $meta_key );

		// Fall back to legacy postmeta if empty.
		if ( '' === $val || null === $val ) {
			$val = get_post_meta( $order->get_id(), $meta_key, true );
		}

		return $val;
	}

	/**
	 * Resolves a human-readable label for a custom order meta key.
	 *
	 * Strips leading underscores, converts separators to spaces, and title-cases the result.
	 * E.g. "_stripe_fee" → "Stripe Fee", "_cod_charge" → "Cod Charge".
	 *
	 * @param string $meta_key The raw meta key (e.g. "_stripe_fee").
	 * @return string The human-readable label.
	 */
	public static function get_custom_field_label( string $meta_key ): string {
		// Strip leading underscores.
		$label = ltrim( $meta_key, '_' );

		// Convert underscores and hyphens to spaces, then title-case.
		$label = ucwords( str_replace( array( '_', '-' ), ' ', $label ) );

		/**
		 * Filters the display label for a custom field on the dashboard.
		 *
		 * @param string $label    The auto-generated label.
		 * @param string $meta_key The original meta key.
		 */
		return (string) apply_filters( 'opti_analytics_custom_field_label', $label, $meta_key );
	}
}
