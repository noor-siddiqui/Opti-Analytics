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
	 * Transient cache key prefix for dashboard data.
	 */
	private const CACHE_PREFIX = 'opti_cache_';

	/**
	 * Transient cache TTL in seconds (5 minutes).
	 */
	private const CACHE_TTL = 300;

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
	 * Retrieves dashboard metrics for pre-fetched order IDs using Direct Optimized SQL.
	 *
	 * @param array<int>                   $order_ids     Pre-fetched order IDs for the date range.
	 * @param array<string, array<string>> $custom_fields Pre-parsed custom field configuration with keys:
	 *   'revenue_builtins', 'cost_builtins', 'revenue_order_fields', 'revenue_product_fields',
	 *   'cost_order_fields', 'cost_product_fields', 'vo_order_fields', 'vo_product_fields'.
	 * @return array<string, float|int> Array of metrics.
	 */
	public function get_dashboard_metrics( array $order_ids, array $custom_fields = array() ): array {
		global $wpdb;

		// Check transient cache.
		$cache_key = self::CACHE_PREFIX . 'metrics_' . md5( wp_json_encode( $order_ids ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// 1. Setup Default Metrics Array
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

		// Build parameterized IN() placeholders for order IDs.
		$id_placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

		// 2. Optimized Base Stats Query.
		// We use WooCommerce's built-in stats table which is indexed and blazing fast.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$base_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					SUM(total_sales) as total_sales,
					SUM(net_total) as net_sales,
					SUM(shipping_total) as shipping,
					SUM(num_items_sold) as products_sold
				FROM {$wpdb->prefix}wc_order_stats 
				WHERE order_id IN ({$id_placeholders})",
				...$order_ids
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		if ( $base_stats ) {
			$metrics['total_sales']   = (float) $base_stats->total_sales;
			$metrics['net_sales']     = (float) $base_stats->net_sales;
			$metrics['shipping']      = (float) $base_stats->shipping;
			$metrics['products_sold'] = (float) $base_stats->products_sold;
		}

		// 3. Setup Custom Fields Aggregation (use pre-parsed fields or fetch fresh).
		$revenue_builtins       = $custom_fields['revenue_builtins'] ?? get_option( Settings::PNL_REVENUE_BUILTINS, array() );
		$cost_builtins          = $custom_fields['cost_builtins'] ?? get_option( Settings::PNL_COST_BUILTINS, array() );
		$revenue_order_fields   = $custom_fields['revenue_order_fields'] ?? Settings::parse_csv_option( Settings::PNL_REVENUE_ORDER_FIELDS );
		$revenue_product_fields = $custom_fields['revenue_product_fields'] ?? Settings::parse_csv_option( Settings::PNL_REVENUE_PRODUCT_FIELDS );
		$cost_order_fields      = $custom_fields['cost_order_fields'] ?? Settings::parse_csv_option( Settings::PNL_COST_ORDER_FIELDS );
		$cost_product_fields    = $custom_fields['cost_product_fields'] ?? Settings::parse_csv_option( Settings::PNL_COST_PRODUCT_FIELDS );
		$vo_order_fields        = $custom_fields['vo_order_fields'] ?? Settings::parse_csv_option( Settings::VIEWONLY_ORDER_FIELDS );
		$vo_product_fields      = $custom_fields['vo_product_fields'] ?? Settings::parse_csv_option( Settings::VIEWONLY_PRODUCT_FIELDS );

		if ( ! is_array( $revenue_builtins ) ) {
			$revenue_builtins = array();
		}
		if ( ! is_array( $cost_builtins ) ) {
			$cost_builtins = array();
		}

		$all_order_fields   = array_unique( array_merge( $revenue_order_fields, $cost_order_fields, $vo_order_fields, array( '_opti_manual_shipping_cost', '_sa_manual_shipping_csv' ) ) );
		$all_product_fields = array_unique( array_merge( $revenue_product_fields, $cost_product_fields, $vo_product_fields, array( '_oa_historical_cogs', '_historical_cogs', '_oa_discount_amount' ) ) );

		// Initialize custom fields to 0.
		foreach ( $all_order_fields as $f ) {
			$metrics[ $f ] = 0.0;
		}
		foreach ( $all_product_fields as $f ) {
			$metrics[ $f ] = 0.0;
		}

		// 4. Optimized Product / Line Item Meta Query.
		// This aggregates custom product data by multiplying (meta_value * _qty).
		if ( ! empty( $all_product_fields ) ) {
			$item_cases = array();
			foreach ( $all_product_fields as $field ) {
				$safe_field = esc_sql( $field );
				// We CAST the string meta values to decimals to safely multiply them in SQL.
				$item_cases[] = "SUM(CASE WHEN im.meta_key = '{$safe_field}' THEN (CAST(im.meta_value AS DECIMAL(10,4)) * CAST(qty_meta.meta_value AS DECIMAL(10,4))) ELSE 0 END) AS `{$safe_field}`";
			}
			$item_selects = implode( ', ', $item_cases );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$item_meta_stats = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT {$item_selects}
					FROM {$wpdb->prefix}woocommerce_order_items i
					JOIN {$wpdb->prefix}woocommerce_order_itemmeta im 
						ON i.order_item_id = im.order_item_id
					JOIN {$wpdb->prefix}woocommerce_order_itemmeta qty_meta 
						ON i.order_item_id = qty_meta.order_item_id AND qty_meta.meta_key = '_qty'
					WHERE i.order_id IN ({$id_placeholders})
					AND i.order_item_type = 'line_item'",
					...$order_ids
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

			if ( $item_meta_stats ) {
				foreach ( $all_product_fields as $field ) {
					$metrics[ $field ] = (float) ( $item_meta_stats->{$field} ?? 0.0 );
				}
			}
		}

		// 5. Optimized Order Meta Query (HPOS + Legacy Hybrid).
		if ( ! empty( $all_order_fields ) ) {
			$is_hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

			// Build parameterized IN() placeholders for meta keys.
			$meta_key_placeholders = implode( ',', array_fill( 0, count( $all_order_fields ), '%s' ) );

			// Combined args: order IDs (ints) + meta keys (strings).
			$legacy_args = array_merge( $order_ids, $all_order_fields );

			// Fetch from Legacy Postmeta.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$legacy_meta = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT post_id as order_id, meta_key, meta_value 
					FROM {$wpdb->postmeta} 
					WHERE post_id IN ({$id_placeholders}) AND meta_key IN ({$meta_key_placeholders})",
					...$legacy_args
				)
			);

			// Fetch from HPOS Meta (if active).
			$hpos_meta = array();
			if ( $is_hpos ) {
				$hpos_args = array_merge( $order_ids, $all_order_fields );
				$hpos_meta = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT order_id, meta_key, meta_value 
						FROM {$wpdb->prefix}wc_orders_meta 
						WHERE order_id IN ({$id_placeholders}) AND meta_key IN ({$meta_key_placeholders})",
						...$hpos_args
					)
				);
			}
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

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

		// 6. Map SQL Results to Metrics Array.
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

		// 7. P&L Calculation.
		foreach ( $revenue_builtins as $key ) {
			if ( isset( $metrics[ $key ] ) ) {
				$metrics['total_revenue'] += $metrics[ $key ];
			}
		}
		foreach ( $revenue_order_fields as $field ) {
			$metrics['total_revenue'] += $metrics[ $field ] ?? 0.0;
		}
		foreach ( $revenue_product_fields as $field ) {
			$metrics['total_revenue'] += $metrics[ $field ] ?? 0.0;
		}

		foreach ( $cost_builtins as $key ) {
			if ( isset( $metrics[ $key ] ) ) {
				$metrics['total_costs'] += $metrics[ $key ];
			}
		}
		foreach ( $cost_order_fields as $field ) {
			$metrics['total_costs'] += $metrics[ $field ] ?? 0.0;
		}
		foreach ( $cost_product_fields as $field ) {
			$metrics['total_costs'] += $metrics[ $field ] ?? 0.0;
		}

		// Final P&L metrics.
		$metrics['gross_profit']  = $metrics['total_revenue'] - ( $metrics['cogs'] ?? 0.0 );
		$metrics['net_profit']    = $metrics['total_revenue'] - $metrics['total_costs'];
		$metrics['profit_margin'] = $metrics['total_revenue'] > 0
			? ( $metrics['net_profit'] / $metrics['total_revenue'] ) * 100
			: 0.0;

		// Cache the result.
		set_transient( $cache_key, $metrics, self::CACHE_TTL );

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

	/**
	 * Retrieves product sales velocity (Top Moving, Slow Moving, and Dead Stock) for given order IDs and dates.
	 *
	 * @param array<int>                        $order_ids List of WooCommerce order IDs in date range.
	 * @param array{start: string, end: string} $dates     Date range array.
	 * @return array{top_moving: array<array{name: string, qty: float|int, velocity: float, runway: float|int|null, stock: int}>, slow_moving: array<array{name: string, stock: int, qty: float|int, days_idle: float|int|null}>, dead_stock: array<array{name: string, stock: int, total_amount: float, last_sale_date: string}>}
	 */
	public function get_product_velocity( array $order_ids, array $dates ): array {
		global $wpdb;

		$start_date = $dates['start'] ?? '';
		$end_date   = $dates['end'] ?? '';

		// Calculate the absolute number of days between the two dates.
		$days_in_period = 1;
		if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
			$diff_days      = (int) round( abs( strtotime( $end_date ) - strtotime( $start_date ) ) / 86400 );
			$days_in_period = max( 1, $diff_days + 1 );
		}

		// Check transient cache.
		$cache_key = self::CACHE_PREFIX . 'velocity_' . md5( wp_json_encode( $order_ids ) . '_' . $start_date . '_' . $end_date );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$sales = array();
		if ( ! empty( $order_ids ) ) {
			$id_placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

			// Query for sum of quantities per product_id.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
						product_id_meta.meta_value AS product_id,
						SUM(CAST(qty_meta.meta_value AS DECIMAL(10,2))) AS sold_qty
					FROM {$wpdb->prefix}woocommerce_order_items i
					JOIN {$wpdb->prefix}woocommerce_order_itemmeta qty_meta 
						ON i.order_item_id = qty_meta.order_item_id AND qty_meta.meta_key = '_qty'
					JOIN {$wpdb->prefix}woocommerce_order_itemmeta product_id_meta 
						ON i.order_item_id = product_id_meta.order_item_id AND product_id_meta.meta_key = '_product_id'
					WHERE i.order_id IN ({$id_placeholders})
					AND i.order_item_type = 'line_item'
					GROUP BY product_id_meta.meta_value",
					...$order_ids
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

			if ( $results ) {
				foreach ( $results as $row ) {
					$p_id = (int) $row->product_id;
					if ( $p_id > 0 ) {
						$sales[ $p_id ] = (float) $row->sold_qty;
					}
				}
			}
		}

		// Fetch all published products to check stock and price.
		$products = wc_get_products(
			array(
				'status' => 'publish',
				'limit'  => -1,
			)
		);

		$all_candidates = array();
		foreach ( $products as $product ) {
			$pid      = $product->get_id();
			$name     = $product->get_name();
			$price    = (float) $product->get_price();
			$stock    = $product->managing_stock() ? (int) $product->get_stock_quantity() : 0;
			$qty_sold = $sales[ $pid ] ?? 0.0;

			// Daily Velocity = Total Unit Sold / Days in Period.
			$daily_velocity = $qty_sold / $days_in_period;

			// Days of stock left = Current stock level / Daily Velocity.
			$runway = null;
			if ( $daily_velocity > 0 ) {
				$runway = $stock / $daily_velocity;
			}

			$all_candidates[ $pid ] = array(
				'id'             => $pid,
				'name'           => $name,
				'price'          => $price,
				'stock'          => $stock,
				'qty_sold'       => $qty_sold,
				'daily_velocity' => $daily_velocity,
				'runway'         => $runway,
			);
		}

		// Also handle any products that had sales but are not in the main published list (e.g. private products).
		foreach ( array_keys( $sales ) as $pid ) {
			if ( ! isset( $all_candidates[ $pid ] ) ) {
				$product = wc_get_product( $pid );
				if ( $product ) {
					$name     = $product->get_name();
					$price    = (float) $product->get_price();
					$stock    = $product->managing_stock() ? (int) $product->get_stock_quantity() : 0;
					$qty_sold = $sales[ $pid ];

					$daily_velocity = $qty_sold / $days_in_period;
					$runway         = null;
					if ( $daily_velocity > 0 ) {
						$runway = $stock / $daily_velocity;
					}

					$all_candidates[ $pid ] = array(
						'id'             => $pid,
						'name'           => $name,
						'price'          => $price,
						'stock'          => $stock,
						'qty_sold'       => $qty_sold,
						'daily_velocity' => $daily_velocity,
						'runway'         => $runway,
					);
				}
			}
		}

		// 1. Top Moving: Sort by daily velocity DESC, filter out items with 0 sales.
		$top_moving_candidates = array_filter(
			$all_candidates,
			function ( $c ) {
				return $c['qty_sold'] > 0;
			}
		);
		usort(
			$top_moving_candidates,
			function ( $a, $b ) {
				return $b['daily_velocity'] <=> $a['daily_velocity'];
			}
		);
		$top_moving = array_slice( $top_moving_candidates, 0, 5 );

		// 2. Slow Moving: Sort by daily velocity ASC, filter out items with 0 stock or 0 sales.
		$slow_moving_candidates = array_filter(
			$all_candidates,
			function ( $c ) {
				return $c['stock'] > 0 && $c['qty_sold'] > 0;
			}
		);
		usort(
			$slow_moving_candidates,
			function ( $a, $b ) {
				if ( $a['daily_velocity'] !== $b['daily_velocity'] ) {
					return $a['daily_velocity'] <=> $b['daily_velocity'];
				}
				return $b['stock'] <=> $a['stock'];
			}
		);
		$slow_moving = array_slice( $slow_moving_candidates, 0, 5 );

		// 3. Dead Stock: Stock > 0 with 0 sales. Sort by total value DESC.
		$dead_stock_candidates = array_filter(
			$all_candidates,
			function ( $c ) {
				return $c['stock'] > 0 && 0.0 === $c['qty_sold'];
			}
		);
		usort(
			$dead_stock_candidates,
			function ( $a, $b ) {
				$val_a = $a['stock'] * $a['price'];
				$val_b = $b['stock'] * $b['price'];
				return $val_b <=> $val_a;
			}
		);
		$dead_stock = array_slice( $dead_stock_candidates, 0, 5 );

		// Formatting results.
		$top_moving_formatted = array();
		foreach ( $top_moving as $item ) {
			$top_moving_formatted[] = array(
				'name'     => $item['name'],
				'qty'      => $item['qty_sold'],
				'velocity' => $item['daily_velocity'],
				'runway'   => $item['runway'],
				'stock'    => $item['stock'],
			);
		}

		$slow_moving_formatted = array();
		foreach ( $slow_moving as $item ) {
			$slow_moving_formatted[] = array(
				'name'      => $item['name'],
				'stock'     => $item['stock'],
				'qty'       => $item['qty_sold'],
				'days_idle' => $item['runway'],
			);
		}

		$dead_stock_formatted = array();
		foreach ( $dead_stock as $item ) {
			$dead_stock_formatted[] = array(
				'name'         => $item['name'],
				'stock'        => $item['stock'],
				'total_amount' => $item['stock'] * $item['price'],
			);
		}

		$velocity = array(
			'top_moving'  => $top_moving_formatted,
			'slow_moving' => $slow_moving_formatted,
			'dead_stock'  => $dead_stock_formatted,
		);

		// Cache the result.
		set_transient( $cache_key, $velocity, self::CACHE_TTL );

		return $velocity;
	}

	/**
	 * Retrieves customer insights (VIP Spenders, New vs Returning, Repeat rate, Avg spend) for given order IDs.
	 *
	 * @param array<int> $order_ids  List of WooCommerce order IDs in date range.
	 * @param string     $start_date Date range start (Y-m-d).
	 * @return array{vip: array<array{name: string, spend: float, count: int}>, new_count: int, returning_count: int, repeat_count: int, unique_count: int, avg_spend: float}
	 */
	public function get_customer_insiders( array $order_ids, string $start_date ): array {
		global $wpdb;

		// Check transient cache.
		$cache_key = self::CACHE_PREFIX . 'insiders_' . md5( wp_json_encode( $order_ids ) . $start_date );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$insiders = array(
			'vip'             => array(),
			'new_count'       => 0,
			'returning_count' => 0,
			'repeat_count'    => 0,
			'unique_count'    => 0,
			'avg_spend'       => 0.0,
		);

		if ( empty( $order_ids ) ) {
			return $insiders;
		}

		$id_placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
		$is_hpos         = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

		if ( $is_hpos ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$db_results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
						o.customer_id, 
						o.billing_email, 
						CONCAT(a.first_name, ' ', a.last_name) AS name, 
						o.total_amount AS order_total
					FROM {$wpdb->prefix}wc_orders o
					LEFT JOIN {$wpdb->prefix}wc_order_addresses a 
						ON o.id = a.order_id AND a.address_type = 'billing'
					WHERE o.id IN ({$id_placeholders})",
					...$order_ids
				)
			);
		} else {
			$db_results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT 
						o.ID as id,
						MAX(CASE WHEN m.meta_key = '_customer_user' THEN m.meta_value END) AS customer_id,
						MAX(CASE WHEN m.meta_key = '_billing_email' THEN m.meta_value END) AS billing_email,
						CONCAT(
							MAX(CASE WHEN m.meta_key = '_billing_first_name' THEN m.meta_value END), 
							' ', 
							MAX(CASE WHEN m.meta_key = '_billing_last_name' THEN m.meta_value END)
						) AS name,
						MAX(CASE WHEN m.meta_key = '_order_total' THEN m.meta_value END) AS order_total
					FROM {$wpdb->posts} o
					JOIN {$wpdb->postmeta} m ON o.ID = m.post_id
					WHERE o.ID IN ({$id_placeholders})
					GROUP BY o.ID",
					...$order_ids
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		$customers       = array();
		$total_sales_sum = 0.0;
		foreach ( $db_results as $row ) {
			$email = trim( strtolower( $row->billing_email ?? '' ) );
			if ( empty( $email ) ) {
				continue;
			}
			$name = trim( $row->name ?? '' );
			if ( empty( $name ) ) {
				$name = $email;
			}
			$total            = (float) ( $row->order_total ?? 0.0 );
			$total_sales_sum += $total;

			if ( ! isset( $customers[ $email ] ) ) {
				$customers[ $email ] = array(
					'name'        => $name,
					'total_spend' => 0.0,
					'order_count' => 0,
				);
			}
			$customers[ $email ]['total_spend'] += $total;
			$customers[ $email ]['order_count'] += 1;
		}

		if ( empty( $customers ) ) {
			return $insiders;
		}

		$insiders['unique_count'] = count( $customers );
		$insiders['avg_spend']    = $total_sales_sum / $insiders['unique_count'];

		// Calculate Repeat Customers.
		$repeat_count = 0;
		foreach ( $customers as $c ) {
			if ( $c['order_count'] > 1 ) {
				++$repeat_count;
			}
		}
		$insiders['repeat_count'] = $repeat_count;

		// Calculate VIP.
		$sorted_customers = $customers;
		uasort(
			$sorted_customers,
			function ( $a, $b ) {
				return $b['total_spend'] <=> $a['total_spend'];
			}
		);
		$vip_sliced = array_slice( $sorted_customers, 0, 3 );
		foreach ( $vip_sliced as $email => $data ) {
			$insiders['vip'][] = array(
				'name'  => $data['name'],
				'spend' => $data['total_spend'],
				'count' => $data['order_count'],
			);
		}

		// Calculate New vs Returning (emails with orders before start date).
		$customer_emails    = array_keys( $customers );
		$email_placeholders = implode( ',', array_fill( 0, count( $customer_emails ), '%s' ) );

		if ( $is_hpos ) {
			$prior_args = array_merge( $customer_emails, array( $start_date . ' 00:00:00' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$prior_emails = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT billing_email 
					FROM {$wpdb->prefix}wc_orders
					WHERE billing_email IN ({$email_placeholders})
					AND date_created_gmt < %s
					AND status IN ('wc-completed', 'wc-processing')",
					...$prior_args
				)
			);
		} else {
			$prior_args = array_merge( $customer_emails, array( $start_date . ' 00:00:00' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$prior_emails = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT m.meta_value 
					FROM {$wpdb->posts} p
					JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '_billing_email'
					WHERE m.meta_value IN ({$email_placeholders})
					AND p.post_date_gmt < %s
					AND p.post_status IN ('wc-completed', 'wc-processing')
					AND p.post_type = 'shop_order'",
					...$prior_args
				)
			);
		}

		$returning_emails = array_flip( $prior_emails );
		$new_count        = 0;
		$returning_count  = 0;
		foreach ( $customers as $email => $data ) {
			if ( isset( $returning_emails[ $email ] ) ) {
				++$returning_count;
			} else {
				++$new_count;
			}
		}

		$insiders['new_count']       = $new_count;
		$insiders['returning_count'] = $returning_count;

		// Cache the result.
		set_transient( $cache_key, $insiders, self::CACHE_TTL );

		return $insiders;
	}

	/**
	 * Clears all Opti Analytics transient caches.
	 *
	 * Called on order status changes to ensure dashboard data stays fresh.
	 */
	public static function clear_cache(): void {
		global $wpdb;

		// Delete all transients matching our cache prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . self::CACHE_PREFIX . '%',
				'_transient_timeout_' . self::CACHE_PREFIX . '%'
			)
		);
	}
}
