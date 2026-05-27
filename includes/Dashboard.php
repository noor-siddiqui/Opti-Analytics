<?php
/**
 * The Dashboard Class for Opti Analytics Plugin.
 *
 * @package OptiAnalytics
 */

declare(strict_types=1);

namespace OptiAnalytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the main dashboard page and tab navigation.
 */
class Dashboard {

	/**
	 * Registers admin hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_dashboard_menu' ) );
	}

	/**
	 * Registers the Opti Analytics menu under WooCommerce.
	 */
	public function register_dashboard_menu(): void {
		add_submenu_page(
			'opti-analytics',
			__( 'Dashboard &lsaquo; Opti Analytics', 'opti-analytics' ),
			__( 'Dashboard', 'opti-analytics' ),
			'manage_woocommerce',
			'opti-analytics',
			array( $this, 'render_dashboard_page' )
		);
	}

	/**
	 * Renders the tabbed dashboard page.
	 */
	public function render_dashboard_page(): void {
		?>
		<div class="wrap">
			<h1 class="opti-dashboard-header">
				<?php esc_html_e( 'Opti Analytics', 'opti-analytics' ); ?>
				<button type="button" class="page-title-action opti-rearrange-btn" id="opti_rearrange_btn">
					<span class="dashicons dashicons-move" style="font-size: 16px; vertical-align: middle; margin-top: -3px; margin-right: 4px;"></span><?php esc_html_e( 'Rearrange Layout', 'opti-analytics' ); ?>
				</button>
				<button type="button" class="button button-link opti-layout-cancel-btn" id="opti_layout_cancel_btn" style="display: none; margin-left: 10px; line-height: 2.15384615; vertical-align: middle;">
					<?php esc_html_e( 'Cancel', 'opti-analytics' ); ?>
				</button>
				<button type="button" class="button button-link opti-layout-reset-btn" id="opti_layout_reset_btn" style="display: none; margin-left: 10px; line-height: 2.15384615; vertical-align: middle; color: #dc2626;">
					<?php esc_html_e( 'Reset Order', 'opti-analytics' ); ?>
				</button>
			</h1>
			<div class="opti-analytics-tab-content">
				<?php $this->render_dashboard_tab(); ?>
			</div>
		</div>

		<script>
			document.addEventListener('DOMContentLoaded', function() {
				var container = document.getElementById('opti_sortable_dashboard');
				var rearrangeBtn = document.getElementById('opti_rearrange_btn');
				var cancelBtn = document.getElementById('opti_layout_cancel_btn');
				var resetBtn = document.getElementById('opti_layout_reset_btn');
				
				if (!container) return;

				var defaultOrder = ['sales', 'shipping', 'fees', 'inventory', 'pnl', 'chart'];
				var originalOrderHtml = [];

				// 1. Instant Pre-sorting on DOM load to prevent layout flashing
				function applySavedLayout() {
					var savedOrder = localStorage.getItem('opti_dashboard_layout_order');
					if (savedOrder) {
						var orderArray = JSON.parse(savedOrder);
						orderArray.forEach(function(id) {
							var item = container.querySelector('.opti-dashboard-sort-item[data-block-id="' + id + '"]');
							if (item) {
								container.appendChild(item);
							}
						});
					}
				}
				applySavedLayout();

				// 2. Drag & Drop Helper for placement calculations
				function getDragAfterElement(container, y) {
					var draggableElements = Array.from(container.querySelectorAll('.opti-dashboard-sort-item:not(.opti-dragging)'));
					
					return draggableElements.reduce(function(closest, child) {
						var box = child.getBoundingClientRect();
						var offset = y - box.top - box.height / 2;
						if (offset < 0 && offset > closest.offset) {
							return { offset: offset, element: child };
						} else {
							return closest;
						}
					}, { offset: Number.NEGATIVE_INFINITY }).element;
				}

				// 3. Drag Event Listeners setup
				function initDragEvents() {
					var sortItems = container.querySelectorAll('.opti-dashboard-sort-item');
					sortItems.forEach(function(item) {
						item.addEventListener('dragstart', handleDragStart);
						item.addEventListener('dragend', handleDragEnd);
					});
					container.addEventListener('dragover', handleDragOver);
				}

				function removeDragEvents() {
					var sortItems = container.querySelectorAll('.opti-dashboard-sort-item');
					sortItems.forEach(function(item) {
						item.removeEventListener('dragstart', handleDragStart);
						item.removeEventListener('dragend', handleDragEnd);
					});
					container.removeEventListener('dragover', handleDragOver);
				}

				function handleDragStart(e) {
					e.dataTransfer.setData('text/plain', this.getAttribute('data-block-id'));
					this.classList.add('opti-dragging');
				}

				function handleDragEnd() {
					this.classList.remove('opti-dragging');
				}

				function handleDragOver(e) {
					e.preventDefault();
					var draggingEl = container.querySelector('.opti-dragging');
					if (!draggingEl) return;
					var nextSibling = getDragAfterElement(container, e.clientY);
					if (nextSibling == null) {
						container.appendChild(draggingEl);
					} else {
						container.insertBefore(draggingEl, nextSibling);
					}
				}

				// 4. Mode Toggles (Rearrange, Cancel, Save, Reset)
				var isRearrangeMode = false;

				if (rearrangeBtn) {
					rearrangeBtn.addEventListener('click', function() {
						if (!isRearrangeMode) {
							// ENTER REARRANGE MODE
							isRearrangeMode = true;
							container.classList.add('rearrange-active');
							rearrangeBtn.innerHTML = '<span class="dashicons dashicons-yes" style="font-size: 16px; vertical-align: middle; margin-top: -3px; margin-right: 4px;"></span><?php esc_html_e( 'Save Layout', 'opti-analytics' ); ?>';
							rearrangeBtn.classList.add('button', 'button-primary');
							rearrangeBtn.classList.remove('page-title-action');
							
							if (cancelBtn) cancelBtn.style.display = 'inline-block';
							if (resetBtn) resetBtn.style.display = 'inline-block';

							// Capture current state to restore on Cancel
							originalOrderHtml = Array.from(container.children);

							// Enable drag capabilities
							var sortItems = container.querySelectorAll('.opti-dashboard-sort-item');
							sortItems.forEach(function(item) {
								item.setAttribute('draggable', 'true');
							});
							initDragEvents();
						} else {
							// SAVE LAYOUT & EXIT
							var currentOrder = [];
							var sortItems = container.querySelectorAll('.opti-dashboard-sort-item');
							sortItems.forEach(function(item) {
								currentOrder.push(item.getAttribute('data-block-id'));
							});
							
							localStorage.setItem('opti_dashboard_layout_order', JSON.stringify(currentOrder));
							exitRearrangeMode();
						}
					});
				}

				if (cancelBtn) {
					cancelBtn.addEventListener('click', function(e) {
						e.preventDefault();
						// Restore original HTML nodes sorting
						originalOrderHtml.forEach(function(node) {
							container.appendChild(node);
						});
						exitRearrangeMode();
					});
				}

				if (resetBtn) {
					resetBtn.addEventListener('click', function(e) {
						e.preventDefault();
						if (confirm('<?php esc_html_e( 'Are you sure you want to reset the dashboard to the default layout?', 'opti-analytics' ); ?>')) {
							localStorage.removeItem('opti_dashboard_layout_order');
							// Restore nodes to default order list
							defaultOrder.forEach(function(id) {
								var item = container.querySelector('.opti-dashboard-sort-item[data-block-id="' + id + '"]');
								if (item) {
									container.appendChild(item);
								}
							});
							exitRearrangeMode();
						}
					});
				}

				function exitRearrangeMode() {
					isRearrangeMode = false;
					container.classList.remove('rearrange-active');
					
					rearrangeBtn.innerHTML = '<span class="dashicons dashicons-move" style="font-size: 16px; vertical-align: middle; margin-top: -3px; margin-right: 4px;"></span><?php esc_html_e( 'Rearrange Layout', 'opti-analytics' ); ?>';
					rearrangeBtn.classList.remove('button', 'button-primary');
					rearrangeBtn.classList.add('page-title-action');
					
					if (cancelBtn) cancelBtn.style.display = 'none';
					if (resetBtn) resetBtn.style.display = 'none';

					// Disable drag capabilities
					var sortItems = container.querySelectorAll('.opti-dashboard-sort-item');
					sortItems.forEach(function(item) {
						item.removeAttribute('draggable');
					});
					removeDragEvents();
				}
			});
		</script>
		<?php
	}

	/**
	 * Calculates the start and end dates based on the filter.
	 *
	 * @param string $filter    The date filter string.
	 * @param string $date_from Custom from date.
	 * @param string $date_to   Custom to date.
	 * @return array<string, string> Array containing 'start' and 'end' date strings (Y-m-d).
	 */
	private function get_date_range( string $filter, string $date_from, string $date_to ): array {
		$start_date = '';
		$end_date   = '';

		// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$now = current_time( 'timestamp' );

		switch ( $filter ) {
			case 'today':
				$start_date = gmdate( 'Y-m-d', $now );
				$end_date   = gmdate( 'Y-m-d', $now );
				break;
			case 'yesterday':
				$yesterday  = strtotime( '-1 day', $now );
				$start_date = gmdate( 'Y-m-d', (int) $yesterday );
				$end_date   = gmdate( 'Y-m-d', (int) $yesterday );
				break;
			case 'this_week':
				$start_of_week = (int) get_option( 'start_of_week', 1 );
				$current_day   = (int) gmdate( 'w', $now );
				$days_to_start = $current_day - $start_of_week;
				if ( $days_to_start < 0 ) {
					$days_to_start += 7;
				}
				$start_timestamp = $now - ( $days_to_start * DAY_IN_SECONDS );
				$end_timestamp   = $start_timestamp + ( 6 * DAY_IN_SECONDS );

				$start_date = gmdate( 'Y-m-d', (int) $start_timestamp );
				$end_date   = gmdate( 'Y-m-d', (int) $end_timestamp );
				break;
			case 'last_week':
				$start_of_week = (int) get_option( 'start_of_week', 1 );
				$current_day   = (int) gmdate( 'w', $now );
				$days_to_start = $current_day - $start_of_week;
				if ( $days_to_start < 0 ) {
					$days_to_start += 7;
				}
				$start_timestamp = $now - ( $days_to_start * DAY_IN_SECONDS ) - ( 7 * DAY_IN_SECONDS );
				$end_timestamp   = $start_timestamp + ( 6 * DAY_IN_SECONDS );

				$start_date = gmdate( 'Y-m-d', (int) $start_timestamp );
				$end_date   = gmdate( 'Y-m-d', (int) $end_timestamp );
				break;
			case 'this_month':
				$start_date = gmdate( 'Y-m-01', $now );
				$end_date   = gmdate( 'Y-m-t', $now );
				break;
			case 'last_month':
				$last_month = strtotime( 'first day of last month', $now );
				$start_date = gmdate( 'Y-m-01', (int) $last_month );
				$end_date   = gmdate( 'Y-m-t', (int) $last_month );
				break;
			case 'this_year':
				$start_date = gmdate( 'Y-01-01', $now );
				$end_date   = gmdate( 'Y-12-31', $now );
				break;
			case 'last_year':
				$last_year  = strtotime( 'last year', $now );
				$start_date = gmdate( 'Y-01-01', (int) $last_year );
				$end_date   = gmdate( 'Y-12-31', (int) $last_year );
				break;
			case 'custom':
				$start_date = $date_from;
				$end_date   = $date_to;
				break;
		}

		return array(
			'start' => $start_date,
			'end'   => $end_date,
		);
	}

	/**
	 * Renders the Dashboard tab content.
	 */
	private function render_dashboard_tab(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Simple GET filter form without state mutation.
		$date_filter = isset( $_GET['date_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['date_filter'] ) ) : 'this_week';
		$date_from   = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to     = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$dates = $this->get_date_range( $date_filter, $date_from, $date_to );
		$this->render_filters( $dates, $date_filter, $date_from, $date_to );

		?>
		<div class="opti-sortable-dashboard" id="opti_sortable_dashboard">
			<?php
			$this->render_performance_kpis( $dates );
			$this->render_sales_chart();
			?>
		</div>
		<?php
	}

	/**
	 * Renders the filter form for the dashboard.
	 *
	 * @param array<string, string> $dates       Computed start and end dates.
	 * @param string                $date_filter Active date filter.
	 * @param string                $date_from   Custom from date.
	 * @param string                $date_to     Custom to date.
	 */
	private function render_filters( array $dates, string $date_filter, string $date_from, string $date_to ): void {
		$filters = array(
			'today'      => __( 'Today', 'opti-analytics' ),
			'yesterday'  => __( 'Yesterday', 'opti-analytics' ),
			'this_week'  => __( 'This Week', 'opti-analytics' ),
			'last_week'  => __( 'Last Week', 'opti-analytics' ),
			'this_month' => __( 'This Month', 'opti-analytics' ),
			'last_month' => __( 'Last Month', 'opti-analytics' ),
			'this_year'  => __( 'This Year', 'opti-analytics' ),
			'last_year'  => __( 'Last Year', 'opti-analytics' ),
			'custom'     => __( 'Custom Date Range', 'opti-analytics' ),
		);
		?>
		<div class="opti-analytics-filters">
			<form method="get" action="" class="opti-filters-form">
				<input type="hidden" name="page" value="opti-analytics" />
				<div class="opti-filters-wrap">
					<div>
						<label for="date_filter" class="opti-filter-label"><?php esc_html_e( 'Date Range:', 'opti-analytics' ); ?></label>
						<select name="date_filter" id="date_filter">
							<?php foreach ( $filters as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $date_filter, $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div id="custom_date_wrap" style="display: <?php echo 'custom' === $date_filter ? 'flex' : 'none'; ?>;">
						<label for="date_from"><?php esc_html_e( 'From:', 'opti-analytics' ); ?></label>
						<input type="date" name="date_from" id="date_from" value="<?php echo esc_attr( $date_from ); ?>" />
						
						<label for="date_to"><?php esc_html_e( 'To:', 'opti-analytics' ); ?></label>
						<input type="date" name="date_to" id="date_to" value="<?php echo esc_attr( $date_to ); ?>" />
					</div>

					<div>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Filter', 'opti-analytics' ); ?></button>
					</div>
				</div>
			</form>

			<?php if ( ! empty( $dates['start'] ) && ! empty( $dates['end'] ) ) : ?>
				<div class="opti-date-range-display">
					<?php
					$date_format     = get_option( 'date_format', 'Y-m-d' );
					$timezone        = new \DateTimeZone( 'UTC' ); // Prevent shifting the day due to site timezone.
					$formatted_start = wp_date( $date_format, (int) strtotime( $dates['start'] ), $timezone );
					$formatted_end   = wp_date( $date_format, (int) strtotime( $dates['end'] ), $timezone );

					/* translators: 1: Start date, 2: End date */
					printf( esc_html__( 'Showing data from: %1$s to %2$s', 'opti-analytics' ), esc_html( (string) $formatted_start ), esc_html( (string) $formatted_end ) );
					?>
				</div>
			<?php endif; ?>
		</div>

		<script>
			document.addEventListener('DOMContentLoaded', function() {
				var dateFilter = document.getElementById('date_filter');
				var customDateWrap = document.getElementById('custom_date_wrap');

				if (dateFilter && customDateWrap) {
					dateFilter.addEventListener('change', function() {
						if (this.value === 'custom') {
							customDateWrap.style.display = 'flex';
						} else {
							customDateWrap.style.display = 'none';
						}
					});
				}
			});
		</script>
		<?php
	}

	/**
	 * Renders the Performance KPI grid.
	 *
	 * @param array<string, string> $dates Computed start and end dates.
	 */
	private function render_performance_kpis( array $dates ): void {
		$engine  = new Data_Engine();
		$metrics = array(
			'total_sales'             => 0.0,
			'net_sales'               => 0.0,
			'gross_sales'             => 0.0,
			'orders_count'            => 0.0,
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
		if ( ! empty( $dates['start'] ) && ! empty( $dates['end'] ) ) {
			$metrics = $engine->get_dashboard_metrics( $dates['start'], $dates['end'] );
		}

		$kpis = array(
			'total_sales'             => array(
				'label'   => __( 'Total sales', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['total_sales'] ) ),
				'desc'    => __( 'What customer paid', 'opti-analytics' ),
				'default' => true,
			),
			'gross_sales'             => array(
				'label'   => __( 'Gross sales', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['gross_sales'] ) ),
				'desc'    => __( 'Selling price × quantity ordered', 'opti-analytics' ),
				'default' => true,
			),
			'net_sales'               => array(
				'label'   => __( 'Net sales', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['net_sales'] ) ),
				'desc'    => __( 'Gross sales minus refunds & discounts', 'opti-analytics' ),
				'default' => true,
			),
			'aov'                     => array(
				'label'   => __( 'Average order value', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['aov'] ) ),
				'desc'    => __( 'Net sales divided by number of orders', 'opti-analytics' ),
				'default' => true,
			),
			'shipping'                => array(
				'label'   => __( 'Shipping collected', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['shipping'] ) ),
				'desc'    => __( 'Total shipping charges collected', 'opti-analytics' ),
				'default' => true,
			),
			'actual_shipping_cost'    => array(
				'label'   => __( 'Actual shipping cost', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['actual_shipping_cost'] ) ),
				'desc'    => __( 'Total actual shipping cost', 'opti-analytics' ),
				'default' => true,
			),
			'cogs'                    => array(
				'label'   => __( 'COGS', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['cogs'] ) ),
				'desc'    => __( 'Total cost of goods sold', 'opti-analytics' ),
				'default' => true,
			),
			'discounted_total'        => array(
				'label'   => __( 'Discounted / Sale', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['discounted_total'] ) . ' / ' . wc_price( $metrics['off_total'] ) ),
				'desc'    => __( 'Total value of discounts & sales', 'opti-analytics' ),
				'default' => true,
			),
			'orders_count'            => array(
				'label'   => __( 'Orders', 'opti-analytics' ),
				'value'   => esc_html( (string) $metrics['orders_count'] ),
				'desc'    => __( 'The number of new orders placed', 'opti-analytics' ),
				'default' => true,
			),
			'products_sold'           => array(
				'label'   => __( 'Products sold', 'opti-analytics' ),
				'value'   => esc_html( (string) $metrics['products_sold'] ),
				'desc'    => __( 'Total quantity of all items purchased', 'opti-analytics' ),
				'default' => true,
			),
			'average_items_per_order' => array(
				'label'   => __( 'Average items per order', 'opti-analytics' ),
				'value'   => esc_html( (string) round( $metrics['average_items_per_order'], 1 ) ),
				'desc'    => __( 'Average number of items per order', 'opti-analytics' ),
				'default' => true,
			),
			'out_of_stock'            => array(
				'label'   => __( 'Out of stock', 'opti-analytics' ),
				'value'   => esc_html( (string) $metrics['out_of_stock'] ),
				'desc'    => __( 'Number of products currently out of stock', 'opti-analytics' ),
				'default' => true,
				'color'   => 'red',
			),
		);

		// Add custom revenue & cost fields as KPI cards.
		$pnl_custom_groups = array(
			array(
				'fields' => Settings::parse_csv_option( Settings::PNL_REVENUE_ORDER_FIELDS ),
				'desc'   => __( 'Revenue — order-level (summed per order)', 'opti-analytics' ),
			),
			array(
				'fields' => Settings::parse_csv_option( Settings::PNL_REVENUE_PRODUCT_FIELDS ),
				'desc'   => __( 'Revenue — line item (value × qty)', 'opti-analytics' ),
			),
			array(
				'fields' => Settings::parse_csv_option( Settings::PNL_COST_ORDER_FIELDS ),
				'desc'   => __( 'Cost — order-level (summed per order)', 'opti-analytics' ),
			),
			array(
				'fields' => Settings::parse_csv_option( Settings::PNL_COST_PRODUCT_FIELDS ),
				'desc'   => __( 'Cost — line item (value × qty)', 'opti-analytics' ),
			),
		);

		foreach ( $pnl_custom_groups as $group ) {
			foreach ( $group['fields'] as $field ) {
				if ( isset( $metrics[ $field ] ) ) {
					$kpis[ $field ] = array(
						'label'   => Data_Engine::get_custom_field_label( $field ),
						'value'   => wp_kses_post( wc_price( $metrics[ $field ] ) ),
						'desc'    => $group['desc'],
						'default' => true,
					);
				}
			}
		}

		// Add View Only fields (dashboard display, no P&L impact).
		$vo_groups = array(
			array(
				'fields' => Settings::parse_csv_option( Settings::VIEWONLY_ORDER_FIELDS ),
				'desc'   => __( 'View only — order-level (summed per order)', 'opti-analytics' ),
			),
			array(
				'fields' => Settings::parse_csv_option( Settings::VIEWONLY_PRODUCT_FIELDS ),
				'desc'   => __( 'View only — line item (value × qty)', 'opti-analytics' ),
			),
		);

		foreach ( $vo_groups as $group ) {
			foreach ( $group['fields'] as $field ) {
				if ( isset( $metrics[ $field ] ) ) {
					$kpis[ $field ] = array(
						'label'   => Data_Engine::get_custom_field_label( $field ),
						'value'   => wp_kses_post( wc_price( $metrics[ $field ] ) ),
						'desc'    => $group['desc'],
						'default' => true,
					);
				}
			}
		}

		// ── P&L KPI cards ───────────────────────────────────────────
		$profit_color = $metrics['net_profit'] >= 0 ? '#16a34a' : '#dc2626';
		$gross_color  = $metrics['gross_profit'] >= 0 ? '#16a34a' : '#dc2626';
		$margin_color = $metrics['profit_margin'] >= 0 ? '#16a34a' : '#dc2626';

		$pnl_kpis = array(
			'total_revenue' => array(
				'label'   => __( 'Total Revenue', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['total_revenue'] ) ),
				'desc'    => __( 'Sum of all selected revenue sources', 'opti-analytics' ),
				'default' => true,
			),
			'total_costs'   => array(
				'label'   => __( 'Total Costs', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['total_costs'] ) ),
				'desc'    => __( 'Sum of all selected cost sources', 'opti-analytics' ),
				'default' => true,
				'color'   => '#dc2626',
			),
			'gross_profit'  => array(
				'label'   => __( 'Gross Profit', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['gross_profit'] ) ),
				'desc'    => __( 'Total Revenue − COGS', 'opti-analytics' ),
				'default' => true,
				'color'   => $gross_color,
			),
			'net_profit'    => array(
				'label'   => __( 'Net Profit', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['net_profit'] ) ),
				'desc'    => __( 'Total Revenue − All Costs', 'opti-analytics' ),
				'default' => true,
				'color'   => $profit_color,
			),
			'profit_margin' => array(
				'label'   => __( 'Profit Margin', 'opti-analytics' ),
				'value'   => esc_html( round( $metrics['profit_margin'], 1 ) . '%' ),
				'desc'    => __( '(Net Profit ÷ Revenue) × 100', 'opti-analytics' ),
				'default' => true,
				'color'   => $margin_color,
			),
		);
		?>

		<!-- ═══════════ PERFORMANCE SECTION ═══════════ -->
		<div class="opti-performance-blocks">
			
			<!-- Block 1: Sales & Orders Overview -->
			<div class="opti-dashboard-sort-item" data-block-id="sales">
				<div class="opti-metric-block opti-theme-green">
					<div class="opti-metric-block-header">
						<h3>
							<span class="dashicons dashicons-menu opti-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'opti-analytics' ); ?>"></span>
							<?php esc_html_e( 'Sales & Orders Overview', 'opti-analytics' ); ?>
						</h3>
					</div>
					<!-- First Row: Sales Overview (4 columns) -->
					<div class="opti-metric-block-grid grid-4-col opti-row-divider">
						<div class="kpi-cell">
							<div class="kpi-cell-title"><?php esc_html_e( 'Total Sales', 'opti-analytics' ); ?></div>
							<div class="kpi-cell-value">
								<?php echo wp_kses_post( wc_price( $metrics['total_sales'] ?? 0.0 ) ); ?>
							</div>
							<div class="kpi-cell-desc">(<?php esc_html_e( 'What customer paid', 'opti-analytics' ); ?>)</div>
						</div>
						<div class="kpi-cell">
							<div class="kpi-cell-title"><?php esc_html_e( 'Gross Sales', 'opti-analytics' ); ?></div>
							<div class="kpi-cell-value">
								<?php echo wp_kses_post( wc_price( $metrics['gross_sales'] ?? 0.0 ) ); ?>
							</div>
							<div class="kpi-cell-desc">(<?php esc_html_e( 'Selling price × quantity ordered', 'opti-analytics' ); ?>)</div>
						</div>
						<div class="kpi-cell">
							<div class="kpi-cell-title"><?php esc_html_e( 'Net Sales', 'opti-analytics' ); ?></div>
							<div class="kpi-cell-value">
								<?php echo wp_kses_post( wc_price( $metrics['net_sales'] ?? 0.0 ) ); ?>
							</div>
							<div class="kpi-cell-desc">(<?php esc_html_e( 'Gross sales minus refunds & discounts', 'opti-analytics' ); ?>)</div>
						</div>
						<div class="kpi-cell">
							<div class="kpi-cell-title"><?php esc_html_e( 'Average Order Value', 'opti-analytics' ); ?></div>
							<div class="kpi-cell-value">
								<?php echo wp_kses_post( wc_price( $metrics['aov'] ?? 0.0 ) ); ?>
							</div>
							<div class="kpi-cell-desc">(<?php esc_html_e( 'Net sales divided by number of orders', 'opti-analytics' ); ?>)</div>
						</div>
					</div>
					<!-- Second Row: Order Overview (4 columns) -->
					<div class="opti-metric-block-grid grid-4-col">
						<div class="kpi-cell">
							<div class="kpi-cell-title"><?php esc_html_e( 'Orders', 'opti-analytics' ); ?></div>
							<div class="kpi-cell-value">
								<?php echo esc_html( (string) ( $metrics['orders_count'] ?? 0 ) ); ?>
							</div>
							<div class="kpi-cell-desc">(<?php esc_html_e( 'The number of new orders placed', 'opti-analytics' ); ?>)</div>
						</div>
						<div class="kpi-cell">
							<div class="kpi-cell-title"><?php esc_html_e( 'Products Sold', 'opti-analytics' ); ?></div>
							<div class="kpi-cell-value">
								<?php echo esc_html( (string) ( $metrics['products_sold'] ?? 0 ) ); ?>
							</div>
							<div class="kpi-cell-desc">(<?php esc_html_e( 'Total quantity of all items purchased', 'opti-analytics' ); ?>)</div>
						</div>
						<div class="kpi-cell">
							<div class="kpi-cell-title"><?php esc_html_e( 'Average Item Per Order', 'opti-analytics' ); ?></div>
							<div class="kpi-cell-value">
								<?php echo esc_html( (string) round( $metrics['average_items_per_order'] ?? 0.0, 1 ) ); ?>
							</div>
							<div class="kpi-cell-desc">(<?php esc_html_e( 'Average number of items per order', 'opti-analytics' ); ?>)</div>
						</div>
						<div class="kpi-cell">
							<div class="kpi-cell-title"><?php esc_html_e( 'Total Discounts', 'opti-analytics' ); ?></div>
							<div class="kpi-cell-value">
								<?php echo wp_kses_post( wc_price( $metrics['off_total'] ?? 0.0 ) ); ?>
							</div>
							<div class="kpi-cell-desc">(<?php esc_html_e( 'Total coupon & sale discounts given', 'opti-analytics' ); ?>)</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Block 2: Fulfillment & Logistics -->
			<div class="opti-dashboard-sort-item" data-block-id="shipping">
				<div class="opti-metric-block opti-theme-blue">
					<div class="opti-metric-block-header">
						<h3>
							<span class="dashicons dashicons-menu opti-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'opti-analytics' ); ?>"></span>
							<?php esc_html_e( 'Shipping & Cost of Goods', 'opti-analytics' ); ?>
						</h3>
					</div>
					<div class="opti-metric-block-grid grid-3-col">
						<div class="kpi-cell">
							<div class="kpi-cell-title"><?php esc_html_e( 'Shipping Collected', 'opti-analytics' ); ?></div>
							<div class="kpi-cell-value">
								<?php echo wp_kses_post( wc_price( $metrics['shipping'] ?? 0.0 ) ); ?>
							</div>
							<div class="kpi-cell-desc">(<?php esc_html_e( 'Total shipping charges collected', 'opti-analytics' ); ?>)</div>
						</div>
						<div class="kpi-cell">
							<div class="kpi-cell-title"><?php esc_html_e( 'Actual Shipping Cost', 'opti-analytics' ); ?></div>
							<div class="kpi-cell-value">
								<?php echo wp_kses_post( wc_price( $metrics['actual_shipping_cost'] ?? 0.0 ) ); ?>
							</div>
							<div class="kpi-cell-desc">(<?php esc_html_e( 'Total actual shipping cost', 'opti-analytics' ); ?>)</div>
						</div>
						<div class="kpi-cell">
							<div class="kpi-cell-title"><?php esc_html_e( 'COGS (Cost of Goods Sold)', 'opti-analytics' ); ?></div>
							<div class="kpi-cell-value">
								<?php echo wp_kses_post( wc_price( $metrics['cogs'] ?? 0.0 ) ); ?>
							</div>
							<div class="kpi-cell-desc">(<?php esc_html_e( 'Total cost of goods sold', 'opti-analytics' ); ?>)</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Block 3: Granular Gateway & Operational Fees -->
			<?php
			$revenue_order_fields   = Settings::parse_csv_option( Settings::PNL_REVENUE_ORDER_FIELDS );
			$revenue_product_fields = Settings::parse_csv_option( Settings::PNL_REVENUE_PRODUCT_FIELDS );
			$cost_order_fields      = Settings::parse_csv_option( Settings::PNL_COST_ORDER_FIELDS );
			$cost_product_fields    = Settings::parse_csv_option( Settings::PNL_COST_PRODUCT_FIELDS );
			$vo_order_fields        = Settings::parse_csv_option( Settings::VIEWONLY_ORDER_FIELDS );
			$vo_product_fields      = Settings::parse_csv_option( Settings::VIEWONLY_PRODUCT_FIELDS );

			$custom_fields = array();

			// Revenue fields (order-level)
			foreach ( $revenue_order_fields as $f ) {
				$custom_fields[ $f ] = array(
					'label' => Data_Engine::get_custom_field_label( $f ),
					'desc'  => __( 'Custom revenue field (order-level)', 'opti-analytics' ),
					'value' => wp_kses_post( wc_price( $metrics[ $f ] ?? 0.0 ) ),
				);
			}
			// Revenue fields (line item)
			foreach ( $revenue_product_fields as $f ) {
				$custom_fields[ $f ] = array(
					'label' => Data_Engine::get_custom_field_label( $f ),
					'desc'  => __( 'Custom revenue field (line item)', 'opti-analytics' ),
					'value' => wp_kses_post( wc_price( $metrics[ $f ] ?? 0.0 ) ),
				);
			}
			// Cost fields (order-level)
			foreach ( $cost_order_fields as $f ) {
				$custom_fields[ $f ] = array(
					'label' => Data_Engine::get_custom_field_label( $f ),
					'desc'  => __( 'Custom cost field (order-level)', 'opti-analytics' ),
					'value' => wp_kses_post( wc_price( $metrics[ $f ] ?? 0.0 ) ),
				);
			}
			// Cost fields (line item)
			foreach ( $cost_product_fields as $f ) {
				$custom_fields[ $f ] = array(
					'label' => Data_Engine::get_custom_field_label( $f ),
					'desc'  => __( 'Custom cost field (line item)', 'opti-analytics' ),
					'value' => wp_kses_post( wc_price( $metrics[ $f ] ?? 0.0 ) ),
				);
			}
			// View only fields (order-level)
			foreach ( $vo_order_fields as $f ) {
				$custom_fields[ $f ] = array(
					'label' => Data_Engine::get_custom_field_label( $f ),
					'desc'  => __( 'View only field (order-level)', 'opti-analytics' ),
					'value' => wp_kses_post( wc_price( $metrics[ $f ] ?? 0.0 ) ),
				);
			}
			// View only fields (line item)
			foreach ( $vo_product_fields as $f ) {
				$custom_fields[ $f ] = array(
					'label' => Data_Engine::get_custom_field_label( $f ),
					'desc'  => __( 'View only field (line item)', 'opti-analytics' ),
					'value' => isset( $metrics[ $f ] ) ? ( strpos( $f, 'count' ) !== false ? esc_html( (string) $metrics[ $f ] ) : wp_kses_post( wc_price( $metrics[ $f ] ) ) ) : wp_kses_post( wc_price( 0.0 ) ),
				);
			}
			?>

			<?php if ( ! empty( $custom_fields ) ) : ?>
				<?php
				$cols_count = min( 4, count( $custom_fields ) );
				$grid_class = "grid-{$cols_count}-col";
				?>
				<div class="opti-dashboard-sort-item" data-block-id="fees">
					<div class="opti-metric-block opti-theme-purple">
						<div class="opti-metric-block-header">
							<h3>
								<span class="dashicons dashicons-menu opti-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'opti-analytics' ); ?>"></span>
								<?php esc_html_e( 'Transaction & Gateway Fees', 'opti-analytics' ); ?>
							</h3>
						</div>
						<div class="opti-metric-block-grid <?php echo esc_attr( $grid_class ); ?>">
							<?php foreach ( $custom_fields as $field_key => $field_data ) : ?>
								<div class="kpi-cell">
									<div class="kpi-cell-title"><?php echo esc_html( $field_data['label'] ); ?></div>
									<div class="kpi-cell-value">
										<?php echo $field_data['value']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized during array construction. ?>
									</div>
									<div class="kpi-cell-desc">(<?php echo esc_html( $field_data['desc'] ); ?>)</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<!-- Block 4: Inventory Alerts (High Priority) -->
			<?php
			$oos_ids = wc_get_products(
				array(
					'status'       => 'publish',
					'stock_status' => 'outofstock',
					'return'       => 'ids',
					'limit'        => -1,
				)
			);
			$oos_count = count( $oos_ids );
			$oos_class = $oos_count > 0 ? 'opti-inventory-alert' : '';
			?>
			<div class="opti-dashboard-sort-item" data-block-id="inventory">
				<div class="opti-metric-block opti-theme-slate <?php echo esc_attr( $oos_class ); ?>">
					<div class="opti-metric-block-header">
						<h3>
							<span class="dashicons dashicons-menu opti-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'opti-analytics' ); ?>"></span>
							<?php esc_html_e( 'Inventory Status', 'opti-analytics' ); ?>
						</h3>
					</div>
					<div class="opti-metric-block-grid grid-1-col">
						<div class="kpi-cell">
							<div class="kpi-cell-title" <?php echo $oos_count > 0 ? 'style="color: #dc2626;"' : ''; ?>>
								<?php esc_html_e( 'Out of Stock', 'opti-analytics' ); ?>
							</div>
							<div class="kpi-cell-value" <?php echo $oos_count > 0 ? 'style="color: #dc2626;"' : ''; ?>>
								<?php echo esc_html( (string) $oos_count ); ?>
							</div>
							<div class="kpi-cell-desc">(<?php esc_html_e( 'Number of products currently out of stock', 'opti-analytics' ); ?>)</div>
						</div>
					</div>
				</div>
			</div>

		</div>

		<!-- ═══════════ PROFIT & LOSS SECTION ═══════════ -->
		<div class="opti-dashboard-sort-item" data-block-id="pnl">
			<div class="opti-metric-block opti-theme-amber" style="margin-top: 30px;">
				<div class="opti-metric-block-header">
					<h3>
						<span class="dashicons dashicons-menu opti-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'opti-analytics' ); ?>"></span>
						<?php esc_html_e( 'Profit & Loss', 'opti-analytics' ); ?>
					</h3>
				</div>
				<div class="opti-metric-block-grid grid-5-col">
					<?php foreach ( $pnl_kpis as $key => $kpi ) : ?>
						<div class="kpi-cell" data-kpi="<?php echo esc_attr( $key ); ?>">
							<div class="kpi-cell-title" <?php echo ! empty( $kpi['color'] ) ? 'style="color: ' . esc_attr( $kpi['color'] ) . ';"' : ''; ?>>
								<?php echo esc_html( $kpi['label'] ); ?>
							</div>
							<div class="kpi-cell-value" <?php echo ! empty( $kpi['color'] ) ? 'style="color: ' . esc_attr( $kpi['color'] ) . ';"' : ''; ?>>
								<?php echo $kpi['value']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values are escaped during array construction. ?>
							</div>
							<div class="kpi-cell-desc">(<?php echo esc_html( $kpi['desc'] ); ?>)</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<?php
	}

	/**
	 * Renders the sales chart placeholder.
	 */
	private function render_sales_chart(): void {
		?>
		<div class="opti-dashboard-sort-item" data-block-id="chart">
			<div class="opti-metric-block opti-theme-slate" style="margin-top: 30px;">
				<div class="opti-metric-block-header">
					<h3>
						<span class="dashicons dashicons-menu opti-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'opti-analytics' ); ?>"></span>
						<?php esc_html_e( 'Sales Trend (Placeholder)', 'opti-analytics' ); ?>
					</h3>
				</div>
				<div class="opti-metric-block-grid grid-1-col" style="padding: 20px;">
					<div class="opti-chart-placeholder" style="height: 300px; background: #f9f9f9; display: flex; align-items: center; justify-content: center; color: #999;">
						<?php esc_html_e( 'Chart will be rendered here.', 'opti-analytics' ); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
