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
	 * Retrieves dashboard metrics for a given date range using Direct Optimized SQL.
	 *
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date   End date in Y-m-d format.
	 * @return array<string, float|int> Array of metrics.
	 */
	public function get_dashboard_metrics( string $start_date, string $end_date ): array {
		global $wpdb;

		// 1. Fetch ONLY the Order IDs (Extremely fast, uses ~1MB memory for 100k orders)
		$args      = array(
			'date_created' => $start_date . ' 00:00:00...' . $end_date . ' 23:59:59',
			'limit'        => -1,
			'return'       => 'ids',
			'status'       => array( 'wc-completed', 'wc-processing' ),
		);
		$order_ids = wc_get_orders( $args );

		// 2. Setup Default Metrics Array
		$metrics = array(
			'total_sales'             => 0.0,
			'net_sales'               => 0.0,
			'gross_sales'             => 0.0,
			'orders_count'            => count( $order_ids ),
			'products_sold'           => 0.0,
			'average_items_per_order' => 0.0,
			'shipping'                => 0.0,
			'out_of_stock'            => 0.0,
			'aov'                     => 0.0,
			'discounted_total'        => 0.0,
			'off_total'               => 0.0,
			'cogs'                    => 0.0,
			'actual_shipping_cost'    => 0.0,
			'total_revenue'           => 0.0,
			'total_costs'             => 0.0,
			'gross_profit'            => 0.0,
			'net_profit'              => 0.0,
			'profit_margin'           => 0.0,
		);

		if ( empty( $order_ids ) ) {
			return $metrics; // Exit early if no orders.
		}

		// Convert IDs to a comma-separated string for SQL IN() clauses safely.
		$ids_list = implode( ',', array_map( 'intval', $order_ids ) );

		// Optimized Base Stats Query.
		// We use WooCommerce's built-in stats table which is indexed and blazing fast.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$base_stats = $wpdb->get_row(
			"SELECT 
				SUM(total_sales) as total_sales,
				SUM(net_total) as net_sales,
				SUM(shipping_total) as shipping,
				SUM(num_items_sold) as products_sold
			FROM {$wpdb->prefix}wc_order_stats 
			WHERE order_id IN ({$ids_list})"
		);

		if ( $base_stats ) {
			$metrics['total_sales']   = (float) $base_stats->total_sales;
			$metrics['net_sales']     = (float) $base_stats->net_sales;
			$metrics['shipping']      = (float) $base_stats->shipping;
			$metrics['products_sold'] = (float) $base_stats->products_sold;
		}

		// Setup Custom Fields Aggregation.
		$revenue_builtins       = get_option( Settings::PNL_REVENUE_BUILTINS, array() );
		$cost_builtins          = get_option( Settings::PNL_COST_BUILTINS, array() );
		$revenue_order_fields   = Settings::parse_csv_option( Settings::PNL_REVENUE_ORDER_FIELDS );
		$revenue_product_fields = Settings::parse_csv_option( Settings::PNL_REVENUE_PRODUCT_FIELDS );
		$cost_order_fields      = Settings::parse_csv_option( Settings::PNL_COST_ORDER_FIELDS );
		$cost_product_fields    = Settings::parse_csv_option( Settings::PNL_COST_PRODUCT_FIELDS );
		$vo_order_fields        = Settings::parse_csv_option( Settings::VIEWONLY_ORDER_FIELDS );
		$vo_product_fields      = Settings::parse_csv_option( Settings::VIEWONLY_PRODUCT_FIELDS );

		if ( ! is_array( $revenue_builtins ) ) {
			$revenue_builtins = array(); }
		if ( ! is_array( $cost_builtins ) ) {
			$cost_builtins = array(); }

		$all_order_fields   = array_unique( array_merge( $revenue_order_fields, $cost_order_fields, $vo_order_fields, array( '_opti_manual_shipping_cost', '_sa_manual_shipping_csv' ) ) );
		$all_product_fields = array_unique( array_merge( $revenue_product_fields, $cost_product_fields, $vo_product_fields, array( '_oa_historical_cogs', '_historical_cogs', '_oa_discount_amount' ) ) );

		// Initialize custom fields to 0.
		foreach ( $all_order_fields as $f ) {
			$metrics[ $f ] = 0.0; }
		foreach ( $all_product_fields as $f ) {
			$metrics[ $f ] = 0.0; }

		// Optimized Product / Line Item Meta Query.
		// This aggregates custom product data by multiplying (meta_value * _qty).
		if ( ! empty( $all_product_fields ) ) {
			$item_cases = array();
			foreach ( $all_product_fields as $field ) {
				$safe_field = esc_sql( $field );
				// We CAST the string meta values to decimals to safely multiply them in SQL.
				$item_cases[] = "SUM(CASE WHEN im.meta_key = '{$safe_field}' THEN (CAST(im.meta_value AS DECIMAL(10,4)) * CAST(qty_meta.meta_value AS DECIMAL(10,4))) ELSE 0 END) AS `{$safe_field}`";
			}
			$item_selects = implode( ', ', $item_cases );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$item_meta_stats = $wpdb->get_row(
				"SELECT {$item_selects}
				FROM {$wpdb->prefix}woocommerce_order_items i
				JOIN {$wpdb->prefix}woocommerce_order_itemmeta im 
					ON i.order_item_id = im.order_item_id
				JOIN {$wpdb->prefix}woocommerce_order_itemmeta qty_meta 
					ON i.order_item_id = qty_meta.order_item_id AND qty_meta.meta_key = '_qty'
				WHERE i.order_id IN ({$ids_list})
				AND i.order_item_type = 'line_item'"
			);

			if ( $item_meta_stats ) {
				foreach ( $all_product_fields as $field ) {
					$metrics[ $field ] = (float) ( $item_meta_stats->{$field} ?? 0.0 );
				}
			}
		}

		// Optimized Order Meta Query (HPOS + Legacy Hybrid).
		if ( ! empty( $all_order_fields ) ) {
			$is_hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

			// Safely prepare the meta keys for the SQL IN() clause.
			$meta_keys_in = "'" . implode( "','", array_map( 'esc_sql', $all_order_fields ) ) . "'";

			// Fetch from Legacy Postmeta.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$legacy_meta = $wpdb->get_results(
				"SELECT post_id as order_id, meta_key, meta_value 
				FROM {$wpdb->postmeta} 
				WHERE post_id IN ({$ids_list}) AND meta_key IN ({$meta_keys_in})"
			);

			// Fetch from HPOS Meta (if active).
			$hpos_meta = array();
			if ( $is_hpos ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$hpos_meta = $wpdb->get_results(
					"SELECT order_id, meta_key, meta_value 
					FROM {$wpdb->prefix}wc_orders_meta 
					WHERE order_id IN ({$ids_list}) AND meta_key IN ({$meta_keys_in})"
				);
			}

			// Merge and Deduplicate in PHP.
			$merged_meta = array();

			// Load legacy data first.
			if ( $legacy_meta ) {
				foreach ( $legacy_meta as $row ) {
					$merged_meta[ $row->order_id ][ $row->meta_key ] = $row->meta_value;
				}
			}

			// Overwrite with HPOS data (HPOS is the source of truth).
			if ( $hpos_meta ) {
				foreach ( $hpos_meta as $row ) {
					$merged_meta[ $row->order_id ][ $row->meta_key ] = $row->meta_value;
				}
			}

			// Sum up the cleanly merged data.
			foreach ( $merged_meta as $order_id => $keys ) {
				foreach ( $keys as $meta_key => $meta_value ) {
					if ( is_numeric( $meta_value ) ) {
						$metrics[ $meta_key ] += (float) $meta_value;
					}
				}
			}
		}

		// Map SQL Results to Metrics Array.
		// COGS Priority Mapping.
		$metrics['cogs']      = $metrics['_oa_historical_cogs'] > 0 ? $metrics['_oa_historical_cogs'] : $metrics['_historical_cogs'];
		$metrics['off_total'] = $metrics['_oa_discount_amount'];

		// Actual Shipping Cost Priority Mapping.
		if ( $metrics['_opti_manual_shipping_cost'] > 0 ) {
			$metrics['actual_shipping_cost'] = $metrics['_opti_manual_shipping_cost'] + $metrics['_sa_manual_shipping_csv'];
		} elseif ( $metrics['_sa_manual_shipping_csv'] > 0 ) {
			$metrics['actual_shipping_cost'] = $metrics['_sa_manual_shipping_csv'];
		} else {
			$metrics['actual_shipping_cost'] = $metrics['shipping'];
		}

		// Gross Sales Approximation (Since wc_order_stats handles net).
		$metrics['gross_sales'] = $metrics['net_sales'] + $metrics['discounted_total'];

		// Averages.
		if ( $metrics['orders_count'] > 0 ) {
			$metrics['average_items_per_order'] = $metrics['products_sold'] / $metrics['orders_count'];
			$metrics['aov']                     = $metrics['net_sales'] / $metrics['orders_count'];
		}

		// ⚡ Bolt: Optimized Out of stock products count.
		// Uses pagination to avoid loading all product IDs into memory at once.
		$oos_query               = wc_get_products(
			array(
				'status'       => 'publish',
				'stock_status' => 'outofstock',
				'paginate'     => true,
				'limit'        => 1,
			)
		);
		$metrics['out_of_stock'] = $oos_query->total;

		// P&L Calculation.
		foreach ( $revenue_builtins as $key ) {
			if ( isset( $metrics[ $key ] ) ) {
				$metrics['total_revenue'] += $metrics[ $key ]; }
		}
		foreach ( $revenue_order_fields as $field ) {
			$metrics['total_revenue'] += $metrics[ $field ] ?? 0.0; }
		foreach ( $revenue_product_fields as $field ) {
			$metrics['total_revenue'] += $metrics[ $field ] ?? 0.0; }

		foreach ( $cost_builtins as $key ) {
			if ( isset( $metrics[ $key ] ) ) {
				$metrics['total_costs'] += $metrics[ $key ]; }
		}
		foreach ( $cost_order_fields as $field ) {
			$metrics['total_costs'] += $metrics[ $field ] ?? 0.0; }
		foreach ( $cost_product_fields as $field ) {
			$metrics['total_costs'] += $metrics[ $field ] ?? 0.0; }

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
