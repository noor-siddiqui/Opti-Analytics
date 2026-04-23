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
			<h1><?php esc_html_e( 'Opti Analytics', 'opti-analytics' ); ?></h1>
			<div class="opti-analytics-tab-content">
				<?php $this->render_dashboard_tab(); ?>
			</div>
		</div>
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

		$this->render_performance_kpis( $dates );

		$this->render_sales_chart();
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
			'total_sales'       => 0.0,
			'net_sales'         => 0.0,
			'gross_sales'       => 0.0,
			'orders_count'      => 0,
			'products_sold'     => 0,
			'shipping'          => 0.0,
			'out_of_stock'      => 0,
			'aov'               => 0.0,
			'discounted_orders' => 0,
		);
		if ( ! empty( $dates['start'] ) && ! empty( $dates['end'] ) ) {
			$metrics = $engine->get_dashboard_metrics( $dates['start'], $dates['end'] );
		}

		$kpis = array(
			'total_sales'       => array(
				'label'   => __( 'Total sales', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['total_sales'] ) ),
				'desc'    => __( 'What customer paid', 'opti-analytics' ),
				'default' => true,
			),
			'gross_sales'       => array(
				'label'   => __( 'Gross sales', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['gross_sales'] ) ),
				'desc'    => __( 'Total product sales before discounts and taxes', 'opti-analytics' ),
				'default' => true,
			),
			'net_sales'         => array(
				'label'   => __( 'Net sales', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['net_sales'] ) ),
				'desc'    => __( 'Gross sales minus refunds and discounts', 'opti-analytics' ),
				'default' => true,
			),
			'aov'               => array(
				'label'   => __( 'Average order value', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['aov'] ) ),
				'desc'    => __( 'Net sales divided by number of orders', 'opti-analytics' ),
				'default' => true,
			),
			'orders_count'      => array(
				'label'   => __( 'Orders', 'opti-analytics' ),
				'value'   => esc_html( (string) $metrics['orders_count'] ),
				'desc'    => __( 'The number of new orders placed', 'opti-analytics' ),
				'default' => true,
			),
			'products_sold'     => array(
				'label'   => __( 'Products sold', 'opti-analytics' ),
				'value'   => esc_html( (string) $metrics['products_sold'] ),
				'desc'    => __( 'Total quantity of all items purchased', 'opti-analytics' ),
				'default' => true,
			),
			'discounted_orders' => array(
				'label'   => __( 'Discounted orders', 'opti-analytics' ),
				'value'   => esc_html( (string) $metrics['discounted_orders'] ),
				'desc'    => __( 'Number of orders containing a discount', 'opti-analytics' ),
				'default' => true,
			),
			'out_of_stock'      => array(
				'label'   => __( 'Out of stock', 'opti-analytics' ),
				'value'   => esc_html( (string) $metrics['out_of_stock'] ),
				'desc'    => __( 'Number of products currently out of stock', 'opti-analytics' ),
				'default' => true,
				'color'   => 'red',
			),
			'shipping'          => array(
				'label'   => __( 'Shipping', 'opti-analytics' ),
				'value'   => wp_kses_post( wc_price( $metrics['shipping'] ) ),
				'desc'    => __( 'Total shipping charges collected', 'opti-analytics' ),
				'default' => true,
			),
		);

		// Add custom fields.
		$custom_fields_string = get_option( Settings::OPTION_NAME, '' );
		$custom_fields        = array_filter( array_map( 'trim', explode( ',', $custom_fields_string ) ) );
		foreach ( $custom_fields as $field ) {
			if ( isset( $metrics[ $field ] ) ) {
				$kpis[ $field ] = array(
					'label'   => Data_Engine::get_custom_field_label( $field ),
					'value'   => esc_html( (string) $metrics[ $field ] ),
					'desc'    => __( 'Custom field value', 'opti-analytics' ),
					'default' => false,
				);
			}
		}
		?>
		<div class="opti-stats-header">
			<h2 class="opti-section-title"><?php esc_html_e( 'Performance', 'opti-analytics' ); ?></h2>
			<div class="opti-dropdown-wrap">
				<button type="button" class="opti-dropdown-toggle" title="<?php esc_attr_e( 'Display stats', 'opti-analytics' ); ?>">
					&#8942;
				</button>
				<div class="opti-dropdown-menu">
					<div class="opti-dropdown-menu-title"><?php esc_html_e( 'Display stats:', 'opti-analytics' ); ?></div>
					<?php foreach ( $kpis as $key => $kpi ) : ?>
						<div class="opti-toggle-item">
							<label class="opti-switch">
								<input type="checkbox" class="opti-kpi-toggle" data-key="<?php echo esc_attr( $key ); ?>" <?php checked( $kpi['default'] ); ?>>
								<span class="opti-slider"></span>
							</label>
							<span class="opti-toggle-label" onclick="this.previousElementSibling.querySelector('input').click();">
								<?php echo esc_html( $kpi['label'] ); ?>
							</span>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		
		<div class="opti-dashboard-grid">
			<?php foreach ( $kpis as $key => $kpi ) : ?>
				<div class="kpi-cell" data-kpi="<?php echo esc_attr( $key ); ?>" style="<?php echo $kpi['default'] ? '' : 'display: none;'; ?>">
					<div class="kpi-cell-title" <?php echo ! empty( $kpi['color'] ) ? 'style="color: ' . esc_attr( $kpi['color'] ) . ';"' : ''; ?>>
						<?php echo esc_html( $kpi['label'] ); ?>
					</div>
					<div class="kpi-cell-value"><?php echo $kpi['value']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Values are escaped during array construction. ?></div>
					<div class="kpi-cell-desc">(<?php echo esc_html( $kpi['desc'] ); ?>)</div>
				</div>
			<?php endforeach; ?>
		</div>

		<script>
			document.addEventListener('DOMContentLoaded', function() {
				var toggleBtn = document.querySelector('.opti-dropdown-toggle');
				var dropdown = document.querySelector('.opti-dropdown-menu');
				
				if (toggleBtn && dropdown) {
					toggleBtn.addEventListener('click', function(e) {
						e.stopPropagation();
						dropdown.classList.toggle('active');
					});
					
					document.addEventListener('click', function(e) {
						if (!dropdown.contains(e.target) && e.target !== toggleBtn) {
							dropdown.classList.remove('active');
						}
					});
				}

				var toggles = document.querySelectorAll('.opti-kpi-toggle');
				var savedState = localStorage.getItem('opti_dashboard_kpis');
				var kpiState = savedState ? JSON.parse(savedState) : {};

				function applyState() {
					toggles.forEach(function(toggle) {
						var key = toggle.getAttribute('data-key');
						var isVisible = kpiState.hasOwnProperty(key) ? kpiState[key] : toggle.defaultChecked;
						toggle.checked = isVisible;
						
						var cell = document.querySelector('.kpi-cell[data-kpi="' + key + '"]');
						if (cell) {
							cell.style.display = isVisible ? 'block' : 'none';
						}
					});
				}

				applyState();

				toggles.forEach(function(toggle) {
					toggle.addEventListener('change', function() {
						var key = this.getAttribute('data-key');
						kpiState[key] = this.checked;
						localStorage.setItem('opti_dashboard_kpis', JSON.stringify(kpiState));
						applyState();
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * Renders the sales chart placeholder.
	 */
	private function render_sales_chart(): void {
		?>
		<div class="opti-chart-container">
			<h3><?php esc_html_e( 'Sales Trend (Placeholder)', 'opti-analytics' ); ?></h3>
			<div class="opti-chart-placeholder">
				<?php esc_html_e( 'Chart will be rendered here.', 'opti-analytics' ); ?>
			</div>
		</div>
		<?php
	}
}
