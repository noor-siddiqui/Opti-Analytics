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
				<button type="button" class="page-title-action button button-primary opti-rearrange-btn" id="opti_rearrange_btn">
					<span class="dashicons dashicons-move" style="font-size: 16px; vertical-align: middle; color: white;"></span><?php esc_html_e( 'Rearrange Layout', 'opti-analytics' ); ?>
				</button>
				<button type="button" class="button button-link opti-layout-cancel-btn" id="opti_layout_cancel_btn" style="display: none; margin-left: 10px; padding: 0 8px; vertical-align: middle;">
					<?php esc_html_e( 'Cancel', 'opti-analytics' ); ?>
				</button>
				<button type="button" class="button button-link opti-layout-reset-btn" id="opti_layout_reset_btn" style="display: none; margin-left: 10px; padding: 0 8px; vertical-align: middle; color: #dc2626;">
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

				var defaultOrder = ['sales', 'shipping', 'fees', 'view_only', 'inventory', 'pnl', 'customer_insiders'];
				var originalOrderHtml = [];

				// 1. Instant Pre-sorting on DOM load to prevent layout flashing
				function applySavedLayout() {
					var savedOrder = localStorage.getItem('opti_dashboard_layout_order');
					if (savedOrder) {
						var orderArray = JSON.parse(savedOrder);
						var allItems = Array.from(container.querySelectorAll('.opti-dashboard-sort-item'));
						var allIds = allItems.map(function(item) { return item.getAttribute('data-block-id'); });

						// Append all items from orderArray first
						orderArray.forEach(function(id) {
							var item = container.querySelector('.opti-dashboard-sort-item[data-block-id="' + id + '"]');
							if (item) {
								container.appendChild(item);
							}
						});

						// Append any items that were not in orderArray to make sure new blocks are visible
						allIds.forEach(function(id) {
							if (orderArray.indexOf(id) === -1) {
								var item = container.querySelector('.opti-dashboard-sort-item[data-block-id="' + id + '"]');
								if (item) {
									container.appendChild(item);
								}
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
							rearrangeBtn.innerHTML = '<span class="dashicons dashicons-yes" style="font-size: 16px; vertical-align: middle; color: white;"></span><?php esc_html_e( 'Save Layout', 'opti-analytics' ); ?>';
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

				// 5. Out of Stock Products Modal Popup Handlers
				var oosModal = document.getElementById('opti_oos_modal');
				var showAllLink = document.getElementById('opti_show_all_oos');
				if (oosModal && showAllLink) {
					showAllLink.addEventListener('click', function(e) {
						e.preventDefault();
						oosModal.style.display = 'flex';
						document.body.style.overflow = 'hidden';
					});

					var closeElements = oosModal.querySelectorAll('.opti-modal-close, .opti-modal-close-btn');
					closeElements.forEach(function(el) {
						el.addEventListener('click', function() {
							oosModal.style.display = 'none';
							document.body.style.overflow = '';
						});
					});

					oosModal.addEventListener('click', function(e) {
						if (e.target === oosModal) {
							oosModal.style.display = 'none';
							document.body.style.overflow = '';
						}
					});
				}
			});
		</script>
		<?php
	}

	/**
	 * Calculates the start and end dates based on the filter.
	 *
	 * Validates custom date inputs to ensure Y-m-d format. Invalid dates
	 * are silently rejected to prevent malformed SQL queries.
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
				// Validate Y-m-d format to prevent malformed date strings reaching SQL.
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
					$from_parts = explode( '-', $date_from );
					$to_parts   = explode( '-', $date_to );
					if ( checkdate( (int) $from_parts[1], (int) $from_parts[2], (int) $from_parts[0] )
						&& checkdate( (int) $to_parts[1], (int) $to_parts[2], (int) $to_parts[0] ) ) {
						$start_date = $date_from;
						$end_date   = $date_to;
					}
				}
				break;
		}

		return array(
			'start' => $start_date,
			'end'   => $end_date,
		);
	}

	/**
	 * Renders the Dashboard tab content.
	 *
	 * Centralizes all data fetching: single wc_get_orders() call, single Data_Engine
	 * instance, and single Settings parse — then passes shared data to sub-render methods
	 * to avoid redundant queries.
	 */
	private function render_dashboard_tab(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Simple GET filter form without state mutation.
		$date_filter = isset( $_GET['date_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['date_filter'] ) ) : 'this_week';
		$date_from   = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to     = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$dates = $this->get_date_range( $date_filter, $date_from, $date_to );
		$this->render_filters( $dates, $date_filter, $date_from, $date_to );

		// ── CENTRALIZED DATA FETCHING ──────────────────────────────
		// Single Data_Engine instance shared by all rendering methods.
		$engine = new Data_Engine();

		// Single wc_get_orders() call — reused for metrics, velocity, and customer insiders.
		$order_ids = array();
		if ( ! empty( $dates['start'] ) && ! empty( $dates['end'] ) ) {
			$order_ids = wc_get_orders(
				array(
					'date_created' => $dates['start'] . ' 00:00:00...' . $dates['end'] . ' 23:59:59',
					'limit'        => -1,
					'return'       => 'ids',
					'status'       => array( 'wc-completed', 'wc-processing' ),
				)
			);
		}

		// Single Settings parse — reused by Data_Engine and rendering methods.
		$custom_fields = array(
			'revenue_builtins'       => get_option( Settings::PNL_REVENUE_BUILTINS, array() ),
			'cost_builtins'          => get_option( Settings::PNL_COST_BUILTINS, array() ),
			'revenue_order_fields'   => Settings::parse_csv_option( Settings::PNL_REVENUE_ORDER_FIELDS ),
			'revenue_product_fields' => Settings::parse_csv_option( Settings::PNL_REVENUE_PRODUCT_FIELDS ),
			'cost_order_fields'      => Settings::parse_csv_option( Settings::PNL_COST_ORDER_FIELDS ),
			'cost_product_fields'    => Settings::parse_csv_option( Settings::PNL_COST_PRODUCT_FIELDS ),
			'vo_order_fields'        => Settings::parse_csv_option( Settings::VIEWONLY_ORDER_FIELDS ),
			'vo_product_fields'      => Settings::parse_csv_option( Settings::VIEWONLY_PRODUCT_FIELDS ),
		);

		// Get metrics once, pass to rendering.
		$metrics = $engine->get_dashboard_metrics( $order_ids, $custom_fields );

		?>
		<div class="opti-sortable-dashboard" id="opti_sortable_dashboard">
			<?php
			$this->render_performance_kpis( $metrics, $order_ids, $engine, $custom_fields, $dates );
			$this->render_customer_insiders( $order_ids, $engine, $dates );
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
	 * @param array<string, float|int>     $metrics       Pre-computed dashboard metrics.
	 * @param array<int>                   $order_ids     Pre-fetched order IDs.
	 * @param Data_Engine                  $engine        Shared Data_Engine instance.
	 * @param array<string, array<string>> $custom_fields Pre-parsed custom field configuration.
	 * @param array<string, string>        $dates         Computed start and end dates.
	 */
	private function render_performance_kpis( array $metrics, array $order_ids, Data_Engine $engine, array $custom_fields, array $dates ): void {

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

		// Add custom revenue & cost fields as KPI cards (using pre-parsed custom_fields).
		$pnl_custom_groups = array(
			array(
				'fields' => $custom_fields['revenue_order_fields'],
				'desc'   => __( 'Revenue — order-level (summed per order)', 'opti-analytics' ),
			),
			array(
				'fields' => $custom_fields['revenue_product_fields'],
				'desc'   => __( 'Revenue — line item (value × qty)', 'opti-analytics' ),
			),
			array(
				'fields' => $custom_fields['cost_order_fields'],
				'desc'   => __( 'Cost — order-level (summed per order)', 'opti-analytics' ),
			),
			array(
				'fields' => $custom_fields['cost_product_fields'],
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

		// Add View Only fields (dashboard display, no P&L impact) — using pre-parsed custom_fields.
		$vo_groups = array(
			array(
				'fields' => $custom_fields['vo_order_fields'],
				'desc'   => __( 'View only — order-level (summed per order)', 'opti-analytics' ),
			),
			array(
				'fields' => $custom_fields['vo_product_fields'],
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
			// Use pre-parsed custom_fields for rendering (no redundant Settings::parse_csv_option calls).
			$revenue_order_fields   = $custom_fields['revenue_order_fields'];
			$revenue_product_fields = $custom_fields['revenue_product_fields'];
			$cost_order_fields      = $custom_fields['cost_order_fields'];
			$cost_product_fields    = $custom_fields['cost_product_fields'];
			$vo_order_fields        = $custom_fields['vo_order_fields'];
			$vo_product_fields      = $custom_fields['vo_product_fields'];

			// ── 1. TRANSACTION & GATEWAY FEES (P&L Impacting Custom Fields) ──
			$fees_fields = array(
				'total_tax' => array(
					'label' => __( 'Total Tax', 'opti-analytics' ),
					'desc'  => __( 'Total tax collected on orders', 'opti-analytics' ),
					'value' => wp_kses_post( wc_price( $metrics['tax_total'] ?? 0.0 ) ),
				),
			);

			// Revenue fields (order-level).
			foreach ( $revenue_order_fields as $f ) {
				$fees_fields[ $f ] = array(
					'label' => Data_Engine::get_custom_field_label( $f ),
					'desc'  => __( 'Custom revenue field (order-level)', 'opti-analytics' ),
					'value' => wp_kses_post( wc_price( $metrics[ $f ] ?? 0.0 ) ),
				);
			}
			// Revenue fields (line item).
			foreach ( $revenue_product_fields as $f ) {
				$fees_fields[ $f ] = array(
					'label' => Data_Engine::get_custom_field_label( $f ),
					'desc'  => __( 'Custom revenue field (line item)', 'opti-analytics' ),
					'value' => wp_kses_post( wc_price( $metrics[ $f ] ?? 0.0 ) ),
				);
			}
			// Cost fields (order-level).
			foreach ( $cost_order_fields as $f ) {
				$fees_fields[ $f ] = array(
					'label' => Data_Engine::get_custom_field_label( $f ),
					'desc'  => __( 'Custom cost field (order-level)', 'opti-analytics' ),
					'value' => wp_kses_post( wc_price( $metrics[ $f ] ?? 0.0 ) ),
				);
			}
			// Cost fields (line item).
			foreach ( $cost_product_fields as $f ) {
				$fees_fields[ $f ] = array(
					'label' => Data_Engine::get_custom_field_label( $f ),
					'desc'  => __( 'Custom cost field (line item)', 'opti-analytics' ),
					'value' => wp_kses_post( wc_price( $metrics[ $f ] ?? 0.0 ) ),
				);
			}

			// ── 2. VIEW ONLY FIELDS (Non-P&L Custom Fields) ──
			$view_only_fields = array();

			// View only fields (order-level).
			foreach ( $vo_order_fields as $f ) {
				$view_only_fields[ $f ] = array(
					'label' => Data_Engine::get_custom_field_label( $f ),
					'desc'  => __( 'View only field (order-level)', 'opti-analytics' ),
					'value' => wp_kses_post( wc_price( $metrics[ $f ] ?? 0.0 ) ),
				);
			}
			// View only fields (line item).
			foreach ( $vo_product_fields as $f ) {
				$view_only_fields[ $f ] = array(
					'label' => Data_Engine::get_custom_field_label( $f ),
					'desc'  => __( 'View only field (line item)', 'opti-analytics' ),
					'value' => isset( $metrics[ $f ] ) ? ( strpos( $f, 'count' ) !== false ? esc_html( (string) $metrics[ $f ] ) : wp_kses_post( wc_price( $metrics[ $f ] ) ) ) : wp_kses_post( wc_price( 0.0 ) ),
				);
			}
			?>

			<?php if ( ! empty( $fees_fields ) ) : ?>
				<?php
				$cols_count = min( 4, count( $fees_fields ) );
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
							<?php foreach ( $fees_fields as $field_key => $field_data ) : ?>
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

			<?php if ( ! empty( $view_only_fields ) ) : ?>
				<?php
				$cols_count = min( 4, count( $view_only_fields ) );
				$grid_class = "grid-{$cols_count}-col";
				?>
				<div class="opti-dashboard-sort-item" data-block-id="view_only">
					<div class="opti-metric-block opti-theme-slate">
						<div class="opti-metric-block-header">
							<h3>
								<span class="dashicons dashicons-menu opti-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'opti-analytics' ); ?>"></span>
								<?php esc_html_e( 'View Only Fields', 'opti-analytics' ); ?>
							</h3>
						</div>
						<div class="opti-metric-block-grid <?php echo esc_attr( $grid_class ); ?>">
							<?php foreach ( $view_only_fields as $field_key => $field_data ) : ?>
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

			<!-- Block 4: Inventory Status & Stock Velocity -->
			<?php
			// Single out-of-stock query — get full product objects to avoid N individual wc_get_product() calls.
			$oos_products_query = wc_get_products(
				array(
					'status'       => 'publish',
					'stock_status' => 'outofstock',
					'limit'        => 50,
					'paginate'     => true,
				)
			);
			$oos_count          = $oos_products_query->total;
			$oos_class          = $oos_count > 0 ? 'opti-inventory-alert' : '';
			$oos_products       = array();
			foreach ( $oos_products_query->products as $product ) {
				$oos_products[] = array(
					'name' => $product->get_name(),
					'id'   => $product->get_id(),
				);
			}

			// Fetch product velocity data (reusing pre-fetched order IDs).
			$velocity = $engine->get_product_velocity( $order_ids, $dates );
			?>
			<div class="opti-dashboard-sort-item" data-block-id="inventory">
				<div class="opti-metric-block opti-theme-slate <?php echo esc_attr( $oos_class ); ?>">
					<div class="opti-metric-block-header">
						<h3>
							<span class="dashicons dashicons-menu opti-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'opti-analytics' ); ?>"></span>
							<?php esc_html_e( 'Inventory Status & Stock Velocity', 'opti-analytics' ); ?>
						</h3>
					</div>
					<div class="opti-metric-block-grid grid-4-col">
						<!-- Card 1: Out of Stock -->
						<div class="kpi-cell">
							<div class="kpi-cell-title" <?php echo $oos_count > 0 ? 'style="color: #dc2626; font-weight: 500;"' : 'style="font-weight: 500;"'; ?>>
								<?php
								/* translators: %d: number of out of stock products */
								printf( esc_html__( 'Out of Stock Products %d', 'opti-analytics' ), (int) $oos_count );
								?>
							</div>

							<!-- Inline Out of Stock List (Max 5) -->
							<div class="opti-velocity-list" style="margin-top: 10px; margin-bottom: 8px;">
								<?php if ( ! empty( $oos_products ) ) : ?>
									<ul style="margin: 0; padding-left: 15px; font-size: 13px; line-height: 1.6; color: #991b1b; list-style-type: disc;">
										<?php
										$oos_inline = array_slice( $oos_products, 0, 5 );
										foreach ( $oos_inline as $item ) :
											?>
											<li style="margin-bottom: 2px;">
												<span style="font-weight: 500; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom;" title="<?php echo esc_attr( $item['name'] ); ?>">
													<?php echo esc_html( $item['name'] ); ?>
												</span>
											</li>
										<?php endforeach; ?>
									</ul>
									<?php if ( $oos_count > 5 ) : ?>
										<a href="#" class="opti-show-all-oos" id="opti_show_all_oos" style="font-size: 11px; float: right; margin-top: 4px; color: #2271b1; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 2px;">
											<?php esc_html_e( 'Show All', 'opti-analytics' ); ?> &rarr;
										</a>
									<?php endif; ?>
								<?php else : ?>
									<p style="font-size: 12px; color: #166534; margin: 0; font-style: italic; font-weight: 500;">
										<span class="dashicons dashicons-yes" style="width:14px; height:14px; vertical-align: middle;"></span>
										<?php esc_html_e( 'All products are in stock.', 'opti-analytics' ); ?>
									</p>
								<?php endif; ?>
							</div>

							<div class="kpi-cell-desc" style="clear: both; margin-top: 8px;">(<?php esc_html_e( 'Products currently out of stock', 'opti-analytics' ); ?>)</div>
						</div>

						<!-- Card 2: Top Moving Products -->
						<div class="kpi-cell">
							<div class="kpi-cell-title" style="color: #166534; font-weight: 500;">
								<?php esc_html_e( '🦅 Top 5 Moving Products', 'opti-analytics' ); ?>
							</div>
							<div class="opti-velocity-list" style="margin-top: 10px;">
								<?php if ( ! empty( $velocity['top_moving'] ) ) : ?>
									<ul style="margin: 0; padding-left: 0; list-style-type: none; font-size: 13px; line-height: 1.6; color: #1f2937;">
										<?php foreach ( $velocity['top_moving'] as $item ) : ?>
											<li style="margin-bottom: 6px; border-bottom: 1px dashed #e5e7eb; padding-bottom: 6px; display: flex; justify-content: space-between; align-items: center; gap: 8px;">
												<span style="font-weight: 400; max-width: 55%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-grow: 1; min-width: 0;" title="<?php echo esc_attr( $item['name'] ); ?>">
													<?php echo esc_html( $item['name'] ); ?>
												</span>
												<span style="flex-shrink: 0; max-width: 45%; color: #4b5563; font-size: 11px; display: inline-flex; align-items: center; gap: 6px;">
													<span style="color: black; font-weight: 500;">
													<?php
													/* translators: 1: units sold, 2: velocity, 3: stock level */
													printf( esc_html__( 'Stk: %3$s | %1$s sold (%2$s/d)', 'opti-analytics' ), esc_html( (string) $item['qty'] ), esc_html( number_format( $item['velocity'], 1 ) ), esc_html( (string) $item['stock'] ) );
													?>
													</span>
													<?php
													if ( null !== $item['runway'] ) :
														$runway_days = (int) round( $item['runway'] );
														$color       = $runway_days <= 2 ? '#dc2626' : ( $runway_days < 7 ? '#d97706' : '#16a34a' );
														$bg          = $runway_days <= 2 ? '#fee2e2' : ( $runway_days < 7 ? '#fef3c7' : '#f0fdf4' );
														?>
														<span style="color: <?php echo esc_attr( $color ); ?>; background: <?php echo esc_attr( $bg ); ?>; font-weight: bold; padding: 1px 4px; border-radius: 3px; font-size: 10px;">
															<?php echo esc_html( (string) $runway_days ) . 'd'; ?>
														</span>
													<?php endif; ?>
												</span>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>
									<p style="font-size: 12px; color: #646970; margin: 0; font-style: italic;">
										<?php esc_html_e( 'No sales data in this range.', 'opti-analytics' ); ?>
									</p>
								<?php endif; ?>
							</div>
							<div class="kpi-cell-desc" style="margin-top: 8px;">(<?php esc_html_e( 'Highest daily velocity and runway', 'opti-analytics' ); ?>)</div>
						</div>

						<!-- Card 3: Slow Moving Products -->
						<div class="kpi-cell">
							<div class="kpi-cell-title" style="color: #b45309; font-weight: 500;">
								<?php esc_html_e( '🐌 Top 5 Slow Moving Products', 'opti-analytics' ); ?>
							</div>
							<div class="opti-velocity-list" style="margin-top: 10px;">
								<?php if ( ! empty( $velocity['slow_moving'] ) ) : ?>
									<ul style="margin: 0; padding-left: 0; list-style-type: none; font-size: 13px; line-height: 1.6; color: #1f2937;">
										<?php foreach ( $velocity['slow_moving'] as $item ) : ?>
											<li style="margin-bottom: 6px; border-bottom: 1px dashed #e5e7eb; padding-bottom: 6px; display: flex; justify-content: space-between; align-items: center; gap: 8px;">
												<span style="max-width: 55%; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-grow: 1; min-width: 0;" title="<?php echo esc_attr( $item['name'] ); ?>">
													<?php echo esc_html( $item['name'] ); ?>
												</span>
												<span style="max-width: 45%; flex-shrink: 0; color: #4b5563; font-size: 11px; display: inline-flex; align-items: center; gap: 6px;">
													<span style="color: black; font-weight: 500;">
													<?php
													/* translators: 1: stock level, 2: units sold */
													printf( esc_html__( 'Stk: %1$s | Sold: %2$s', 'opti-analytics' ), esc_html( (string) $item['stock'] ), esc_html( (string) $item['qty'] ) );
													?>
													</span>
													<?php if ( null !== $item['days_idle'] ) : ?>
														<span style="color: #b45309; background: #fffbeb; font-weight: 600; padding: 1px 4px; border-radius: 3px; font-size: 10px;">
															<?php echo esc_html( (string) round( $item['days_idle'] ) ) . 'd'; ?>
														</span>
													<?php endif; ?>
												</span>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>
									<p style="font-size: 12px; color: #646970; margin: 0; font-style: italic;">
										<?php esc_html_e( 'No slow moving products.', 'opti-analytics' ); ?>
									</p>
								<?php endif; ?>
							</div>
							<div class="kpi-cell-desc" style="margin-top: 8px;">(<?php esc_html_e( 'In-stock items with lowest velocity', 'opti-analytics' ); ?>)</div>
						</div>

						<!-- Card 4: Dead Stock -->
						<div class="kpi-cell">
							<div class="kpi-cell-title" style="color: #6b7280; font-weight: 500;">
								<?php esc_html_e( '🐘 5 Dead Stock', 'opti-analytics' ); ?>
							</div>
							<div class="opti-velocity-list" style="margin-top: 10px;">
								<?php if ( ! empty( $velocity['dead_stock'] ) ) : ?>
									<ul style="margin: 0; padding-left: 0; list-style-type: none; font-size: 13px; line-height: 1.6; color: #1f2937;">
										<?php foreach ( $velocity['dead_stock'] as $item ) : ?>
											<li style="margin-bottom: 6px; border-bottom: 1px dashed #e5e7eb; padding-bottom: 6px; display: flex; justify-content: space-between; align-items: center; gap: 8px;">
												<span style="max-width: 55%; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-grow: 1; min-width: 0;" title="<?php echo esc_attr( $item['name'] ); ?>">
													<?php echo esc_html( $item['name'] ); ?>
												</span>
												<span style="max-width: 45%; flex-shrink: 0; color: #4b5563; font-size: 11px; display: inline-flex; align-items: center; gap: 6px;">
													<span style="color: black; font-weight: 500;">
													<?php
													/* translators: %s: stock level */
													printf( esc_html__( 'Stk: %s', 'opti-analytics' ), esc_html( (string) $item['stock'] ) );
													?>
													</span>
													<span style="font-weight: 600; color: #f70707; background: #dbf0ff; padding: 1px 4px; border-radius: 3px; font-size: 10px;">
														<?php echo wp_kses_post( wc_price( $item['total_amount'] ) ); ?>
													</span>
												</span>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php else : ?>
									<p style="font-size: 12px; color: #646970; margin: 0; font-style: italic;">
										<?php esc_html_e( 'No dead stock found.', 'opti-analytics' ); ?>
									</p>
								<?php endif; ?>
							</div>
							<div class="kpi-cell-desc" style="margin-top: 8px;">(<?php esc_html_e( 'Stock > 0 with zero sales in period', 'opti-analytics' ); ?>)</div>
						</div>
					</div>
				</div>

				<!-- Out of Stock Modal Popup Overlay -->
				<div class="opti-modal-overlay" id="opti_oos_modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); z-index: 99999; backdrop-filter: blur(4px); align-items: center; justify-content: center;">
					<div class="opti-modal-content" style="background: #ffffff; width: 90%; max-width: 500px; border-radius: 8px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); overflow: hidden; border: 1px solid #e5e7eb; animation: optiModalFadeIn 0.2s ease-out;">
						<!-- Header -->
						<div style="padding: 16px 20px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; background: #fdf2f2;">
							<h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #991b1b; display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-warning" style="color: #dc2626; vertical-align: middle;"></span>
								<?php esc_html_e( 'All Out of Stock Products', 'opti-analytics' ); ?>
								<span style="font-size: 12px; background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 9999px; font-weight: 600; margin-left: 4px;">
									<?php echo esc_html( (string) $oos_count ); ?>
								</span>
							</h3>
							<button type="button" class="opti-modal-close" style="background: none; border: none; cursor: pointer; color: #9ca3af; padding: 4px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.15s, color 0.15s;" onmouseover="this.style.background='#f3f4f6'; this.style.color='#4b5563';" onmouseout="this.style.background='none'; this.style.color='#9ca3af';">
								<span class="dashicons dashicons-no-alt" style="font-size: 20px; width: 20px; height: 20px;"></span>
							</button>
						</div>
						<!-- Body -->
						<div style="padding: 20px; max-height: 350px; overflow-y: auto;">
							<ul style="margin: 0; padding: 0; list-style: none;">
								<?php foreach ( $oos_products as $idx => $p ) : ?>
									<li style="padding: 10px 12px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; <?php echo ( count( $oos_products ) - 1 ) === $idx ? 'border-bottom: none;' : ''; ?>">
										<span style="font-size: 13px; font-weight: 500; color: #1f2937; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px;" title="<?php echo esc_attr( $p['name'] ); ?>">
											<?php echo esc_html( $p['name'] ); ?>
										</span>
										<span style="font-size: 11px; background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 4px; font-weight: 600;">
											<?php esc_html_e( 'Out of stock', 'opti-analytics' ); ?>
										</span>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
						<!-- Footer -->
						<div style="padding: 14px 20px; border-top: 1px solid #f3f4f6; display: flex; justify-content: flex-end; background: #f9fafb;">
							<button type="button" class="button opti-modal-close-btn" style="font-size: 12px;"><?php esc_html_e( 'Close', 'opti-analytics' ); ?></button>
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
	 * Renders the Customer Insiders block.
	 *
	 * @param array<int>            $order_ids Pre-fetched order IDs.
	 * @param Data_Engine           $engine    Shared Data_Engine instance.
	 * @param array<string, string> $dates     Computed start and end dates.
	 */
	private function render_customer_insiders( array $order_ids, Data_Engine $engine, array $dates ): void {
		$insiders = $engine->get_customer_insiders( $order_ids, $dates['start'] ?? '' );
		?>
		<div class="opti-dashboard-sort-item" data-block-id="customer_insiders">
			<div class="opti-metric-block opti-theme-purple" style="margin-top: 30px;">
				<div class="opti-metric-block-header">
					<h3>
						<span class="dashicons dashicons-menu opti-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'opti-analytics' ); ?>"></span>
						<?php esc_html_e( 'Customer Insiders', 'opti-analytics' ); ?>
					</h3>
				</div>
				<div class="opti-metric-block-grid grid-3-col">
					<!-- Column 1: VIP Customers (Top 3 Spenders) -->
					<div class="kpi-cell">
						<div class="kpi-cell-title" style="color: #6b21a8; font-weight: 600;">
							<span class="dashicons dashicons-awards" style="vertical-align: middle; margin-right: 4px; font-size: 16px;"></span>
							<?php esc_html_e( 'Top 3 VIP Customers', 'opti-analytics' ); ?>
						</div>
						<div class="opti-velocity-list" style="margin-top: 10px;">
							<?php if ( ! empty( $insiders['vip'] ) ) : ?>
								<ol style="margin: 0; padding-left: 15px; font-size: 13px; line-height: 1.6; color: #1f2937;">
									<?php foreach ( $insiders['vip'] as $customer ) : ?>
										<li style="margin-bottom: 4px;">
											<span style="font-weight: 500; display: inline-block; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; vertical-align: bottom;" title="<?php echo esc_attr( $customer['name'] ); ?>">
												<?php echo esc_html( $customer['name'] ); ?>
											</span>
											<span style="float: right; font-weight: 600; color: #6b21a8; background: #faf5ff; padding: 1px 6px; border-radius: 4px; font-size: 11px;">
												<?php echo wp_kses_post( wc_price( $customer['spend'] ) ); ?>
											</span>
										</li>
									<?php endforeach; ?>
								</ol>
							<?php else : ?>
								<p style="font-size: 12px; color: #646970; margin: 0; font-style: italic;">
									<?php esc_html_e( 'No customer activity in this range.', 'opti-analytics' ); ?>
								</p>
							<?php endif; ?>
						</div>
						<div class="kpi-cell-desc" style="margin-top: 8px;">(<?php esc_html_e( 'Highest spending customers in selected range', 'opti-analytics' ); ?>)</div>
					</div>

					<!-- Column 2: Buyer Mix (New vs Returning) -->
					<div class="kpi-cell">
						<div class="kpi-cell-title" style="color: #1e40af; font-weight: 600;">
							<span class="dashicons dashicons-groups" style="vertical-align: middle; margin-right: 4px; font-size: 16px;"></span>
							<?php esc_html_e( 'Buyer Mix', 'opti-analytics' ); ?>
						</div>
						<div style="margin-top: 12px;">
							<?php
							$total_buyers = $insiders['new_count'] + $insiders['returning_count'];
							$new_percent  = $total_buyers > 0 ? round( ( $insiders['new_count'] / $total_buyers ) * 100 ) : 0;
							$ret_percent  = $total_buyers > 0 ? round( ( $insiders['returning_count'] / $total_buyers ) * 100 ) : 0;
							?>
							<div style="display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px; color: #1f2937;">
								<span style="font-weight: 500; display: inline-flex; align-items: center; gap: 4px;">
									<span style="width: 8px; height: 8px; border-radius: 50%; background: #22c55e; display: inline-block;"></span>
									<?php esc_html_e( 'New Buyers', 'opti-analytics' ); ?>
								</span>
								<span style="font-weight: 600;"><?php echo esc_html( "{$insiders['new_count']} ({$new_percent}%)" ); ?></span>
							</div>
							<div style="display: flex; justify-content: space-between; font-size: 13px; color: #1f2937;">
								<span style="font-weight: 500; display: inline-flex; align-items: center; gap: 4px;">
									<span style="width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; display: inline-block;"></span>
									<?php esc_html_e( 'Returning Buyers', 'opti-analytics' ); ?>
								</span>
								<span style="font-weight: 600;"><?php echo esc_html( "{$insiders['returning_count']} ({$ret_percent}%)" ); ?></span>
							</div>

							<!-- Micro Progress Bar -->
							<div style="display: flex; height: 6px; border-radius: 9999px; overflow: hidden; background: #e5e7eb; margin-top: 14px;">
								<div style="width: <?php echo esc_attr( (string) $new_percent ); ?>%; background: #22c55e;"></div>
								<div style="width: <?php echo esc_attr( (string) $ret_percent ); ?>%; background: #3b82f6;"></div>
							</div>
						</div>
						<div class="kpi-cell-desc" style="margin-top: 16px;">(<?php esc_html_e( 'First-time vs repeat buyers in selected range', 'opti-analytics' ); ?>)</div>
					</div>

					<!-- Column 3: Customer Behavior & Value -->
					<div class="kpi-cell">
						<div class="kpi-cell-title" style="color: #334155; font-weight: 600;">
							<span class="dashicons dashicons-admin-users" style="vertical-align: middle; margin-right: 4px; font-size: 16px;"></span>
							<?php esc_html_e( 'Value & Engagement', 'opti-analytics' ); ?>
						</div>
						<div style="margin-top: 10px; font-size: 13px; line-height: 1.6; color: #1f2937;">
							<?php
							$repeat_rate = $insiders['unique_count'] > 0 ? round( ( $insiders['repeat_count'] / $insiders['unique_count'] ) * 100, 1 ) : 0.0;
							?>
							<div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
								<span style="font-weight: 500;"><?php esc_html_e( 'Avg. Spend / Customer', 'opti-analytics' ); ?></span>
								<span style="font-weight: 600; color: #0f172a;"><?php echo wp_kses_post( wc_price( $insiders['avg_spend'] ) ); ?></span>
							</div>
							<div style="display: flex; justify-content: space-between;">
								<span style="font-weight: 500;"><?php esc_html_e( 'Repeat Purchase Rate', 'opti-analytics' ); ?></span>
								<span style="font-weight: 600; color: #0f172a;"><?php echo esc_html( "{$repeat_rate}%" ); ?></span>
							</div>
							<div style="font-size: 11px; color: #646970; margin-top: 6px; font-style: italic;">
								<?php
								/* translators: 1: repeat customers count, 2: total unique customers */
								printf( esc_html__( '%1$d of %2$d buyers placed 2+ orders', 'opti-analytics' ), (int) $insiders['repeat_count'], (int) $insiders['unique_count'] );
								?>
							</div>
						</div>
						<div class="kpi-cell-desc" style="margin-top: 8px;">(<?php esc_html_e( 'Average spend and repeat customer percentages', 'opti-analytics' ); ?>)</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
