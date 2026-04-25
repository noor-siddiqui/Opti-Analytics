<?php
/**
 * The Settings Class for Opti Analytics Plugin.
 *
 * @package OptiAnalytics
 */

declare(strict_types=1);

namespace OptiAnalytics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the admin settings for Opti Analytics.
 */
class Settings {

	// ── Legacy options (kept for backward-compatible reads) ──────────
	public const OPTION_NAME           = 'opti_analytics_custom_fields';
	public const PRODUCT_FIELDS_OPTION = 'opti_analytics_product_fields';

	// ── Manual shipping toggle ──────────────────────────────────────
	public const MANUAL_SHIPPING_OPTION = 'opti_analytics_enable_manual_shipping';

	// ── P&L: built-in source selections (stored as arrays) ─────────
	public const PNL_REVENUE_BUILTINS = 'opti_analytics_pnl_revenue_builtins';
	public const PNL_COST_BUILTINS    = 'opti_analytics_pnl_cost_builtins';

	// ── P&L: custom meta keys (comma-separated strings) ────────────
	public const PNL_REVENUE_ORDER_FIELDS   = 'opti_analytics_pnl_revenue_order_fields';
	public const PNL_REVENUE_PRODUCT_FIELDS = 'opti_analytics_pnl_revenue_product_fields';
	public const PNL_COST_ORDER_FIELDS      = 'opti_analytics_pnl_cost_order_fields';
	public const PNL_COST_PRODUCT_FIELDS    = 'opti_analytics_pnl_cost_product_fields';

	// ── View Only: display on dashboard but no P&L impact ──────────
	public const VIEWONLY_ORDER_FIELDS   = 'opti_analytics_viewonly_order_fields';
	public const VIEWONLY_PRODUCT_FIELDS = 'opti_analytics_viewonly_product_fields';

	/**
	 * Built-in revenue sources the store owner can toggle.
	 */
	public const BUILTIN_REVENUE_SOURCES = array(
		'total_sales' => 'Total Sales (incl. tax & shipping)',
		'gross_sales' => 'Gross Sales (subtotal before discounts)',
		'net_sales'   => 'Net Sales (total − tax − shipping − refunds)',
		'shipping'    => 'Shipping Collected',
	);

	/**
	 * Built-in cost sources the store owner can toggle.
	 */
	public const BUILTIN_COST_SOURCES = array(
		'cogs'                 => 'COGS (Cost of Goods Sold)',
		'actual_shipping_cost' => 'Actual Shipping Cost',
		'discounted_total'     => 'Discounts Given',
	);

	/**
	 * Registers settings hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_settings_menu' ) );
		add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
	}

	/**
	 * Registers the settings menu under Opti Analytics.
	 */
	public function register_settings_menu(): void {
		add_submenu_page(
			'opti-analytics',
			__( 'Settings &lsaquo; Opti Analytics', 'opti-analytics' ),
			__( 'Settings', 'opti-analytics' ),
			'manage_woocommerce',
			'opti-analytics-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Registers all plugin options with the WordPress Settings API.
	 */
	public function register_plugin_settings(): void {
		$group = 'opti_analytics_settings_group';

		// Built-in source arrays.
		register_setting(
			$group,
			self::PNL_REVENUE_BUILTINS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_builtins_array' ),
				'default'           => array(),
			)
		);
		register_setting(
			$group,
			self::PNL_COST_BUILTINS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_builtins_array' ),
				'default'           => array(),
			)
		);

		// Custom meta key strings (comma-separated).
		$csv_options = array(
			self::PNL_REVENUE_ORDER_FIELDS,
			self::PNL_REVENUE_PRODUCT_FIELDS,
			self::PNL_COST_ORDER_FIELDS,
			self::PNL_COST_PRODUCT_FIELDS,
			self::VIEWONLY_ORDER_FIELDS,
			self::VIEWONLY_PRODUCT_FIELDS,
		);
		foreach ( $csv_options as $opt ) {
			register_setting(
				$group,
				$opt,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '',
				)
			);
		}

		// Manual shipping toggle.
		register_setting(
			$group,
			self::MANUAL_SHIPPING_OPTION,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
	}

	/**
	 * Sanitizes an array of built-in source keys.
	 *
	 * @param mixed $input The raw input value.
	 * @return array Sanitized array of strings, or empty array.
	 */
	public function sanitize_builtins_array( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}
		return array_map( 'sanitize_text_field', $input );
	}

	/**
	 * Gets available custom fields from recent orders across both HPOS and legacy storage.
	 *
	 * @return array<string, string> Associative array of meta_key => source label.
	 */
	private function get_available_order_meta_keys(): array {
		global $wpdb;

		$hpos_keys   = array();
		$legacy_keys = array();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$hpos_table = $wpdb->prefix . 'wc_orders_meta';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos_table ) ) === $hpos_table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$hpos_results = $wpdb->get_col(
				"SELECT DISTINCT meta_key FROM {$hpos_table} ORDER BY meta_key ASC" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
			if ( $hpos_results ) {
				$hpos_keys = array_flip( $hpos_results );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$legacy_results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s ORDER BY pm.meta_key ASC",
				'shop_order'
			)
		);
		if ( $legacy_results ) {
			$legacy_keys = array_flip( $legacy_results );
		}

		$all_keys = array();
		$combined = array_unique( array_merge( array_keys( $hpos_keys ), array_keys( $legacy_keys ) ) );
		sort( $combined );

		foreach ( $combined as $key ) {
			$in_hpos   = isset( $hpos_keys[ $key ] );
			$in_legacy = isset( $legacy_keys[ $key ] );
			if ( $in_hpos && $in_legacy ) {
				$all_keys[ $key ] = 'Both';
			} elseif ( $in_hpos ) {
				$all_keys[ $key ] = 'HPOS';
			} else {
				$all_keys[ $key ] = 'Legacy';
			}
		}

		return $all_keys;
	}

	/**
	 * Gets available product/variation meta keys.
	 *
	 * @return array<string> Array of unique meta keys.
	 */
	private function get_available_product_meta_keys(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type IN (%s, %s) ORDER BY pm.meta_key ASC",
				'product',
				'product_variation'
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Renders the settings page.
	 */
	public function render_settings_page(): void {
		$revenue_builtins       = get_option( self::PNL_REVENUE_BUILTINS, array() );
		$cost_builtins          = get_option( self::PNL_COST_BUILTINS, array() );
		$revenue_order_fields   = get_option( self::PNL_REVENUE_ORDER_FIELDS, '' );
		$revenue_product_fields = get_option( self::PNL_REVENUE_PRODUCT_FIELDS, '' );
		$cost_order_fields      = get_option( self::PNL_COST_ORDER_FIELDS, '' );
		$cost_product_fields    = get_option( self::PNL_COST_PRODUCT_FIELDS, '' );
		$vo_order_fields        = get_option( self::VIEWONLY_ORDER_FIELDS, '' );
		$vo_product_fields      = get_option( self::VIEWONLY_PRODUCT_FIELDS, '' );
		$order_keys             = $this->get_available_order_meta_keys();
		$product_keys           = $this->get_available_product_meta_keys();

		if ( ! is_array( $revenue_builtins ) ) {
			$revenue_builtins = array();
		}
		if ( ! is_array( $cost_builtins ) ) {
			$cost_builtins = array();
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Opti Analytics Settings', 'opti-analytics' ); ?></h1>
			<p style="color: #646970; margin-bottom: 20px;">
				<?php esc_html_e( 'Configure which data feeds into your Profit & Loss calculation and dashboard.', 'opti-analytics' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( 'opti_analytics_settings_group' ); ?>

				<?php // ── CARD 1: Revenue ──────────────────────────────── ?>
				<div class="opti-settings-card">
					<div class="opti-settings-card-header">
						<span class="dashicons dashicons-money-alt"></span>
						<h2><?php esc_html_e( 'Revenue Sources', 'opti-analytics' ); ?></h2>
						<p><?php esc_html_e( 'Money collected from customers → feeds into P&L as revenue', 'opti-analytics' ); ?></p>
					</div>
					<div class="opti-settings-card-body">
						<div class="opti-field-row">
							<label><?php esc_html_e( 'Built-in Metrics', 'opti-analytics' ); ?></label>
							<input type="hidden" name="<?php echo esc_attr( self::PNL_REVENUE_BUILTINS ); ?>" value="">
							<fieldset>
								<?php foreach ( self::BUILTIN_REVENUE_SOURCES as $key => $label ) : ?>
									<label style="display: block; margin-bottom: 6px; font-weight: normal;">
										<input type="checkbox" name="<?php echo esc_attr( self::PNL_REVENUE_BUILTINS ); ?>[]"
											value="<?php echo esc_attr( $key ); ?>"
											<?php checked( in_array( $key, $revenue_builtins, true ) ); ?> />
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</div>
						<?php
						$this->render_meta_field_row(
							'revenue_order_fields_input',
							self::PNL_REVENUE_ORDER_FIELDS,
							$revenue_order_fields,
							__( 'Custom Order Meta', 'opti-analytics' ),
							__( 'Order-level meta keys — summed once per order.', 'opti-analytics' ),
							'_tip_amount, _insurance_fee',
							$order_keys,
							'opti-rev-order',
							true
						);
						$this->render_meta_field_row(
							'revenue_product_fields_input',
							self::PNL_REVENUE_PRODUCT_FIELDS,
							$revenue_product_fields,
							__( 'Custom Line Item Meta', 'opti-analytics' ),
							__( 'Line-item meta keys — aggregated as value × quantity.', 'opti-analytics' ),
							'_custom_revenue_per_unit',
							$product_keys,
							'opti-rev-prod',
							false
						);
						?>
					</div>
				</div>

				<?php // ── CARD 2: Costs ───────────────────────────────── ?>
				<div class="opti-settings-card">
					<div class="opti-settings-card-header">
						<span class="dashicons dashicons-cart"></span>
						<h2><?php esc_html_e( 'Cost Sources', 'opti-analytics' ); ?></h2>
						<p><?php esc_html_e( 'Expenses to fulfill orders → feeds into P&L as costs', 'opti-analytics' ); ?></p>
					</div>
					<div class="opti-settings-card-body">
						<div class="opti-field-row">
							<label><?php esc_html_e( 'Built-in Metrics', 'opti-analytics' ); ?></label>
							<input type="hidden" name="<?php echo esc_attr( self::PNL_COST_BUILTINS ); ?>" value="">
							<fieldset>
								<?php foreach ( self::BUILTIN_COST_SOURCES as $key => $label ) : ?>
									<label style="display: block; margin-bottom: 6px; font-weight: normal;">
										<input type="checkbox" name="<?php echo esc_attr( self::PNL_COST_BUILTINS ); ?>[]"
											value="<?php echo esc_attr( $key ); ?>"
											<?php checked( in_array( $key, $cost_builtins, true ) ); ?> />
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</div>
						<?php
						$this->render_meta_field_row(
							'cost_order_fields_input',
							self::PNL_COST_ORDER_FIELDS,
							$cost_order_fields,
							__( 'Custom Order Meta', 'opti-analytics' ),
							__( 'Order-level meta keys — summed once per order.', 'opti-analytics' ),
							'_stripe_fee, _cod_charge',
							$order_keys,
							'opti-cost-order',
							true
						);
						$this->render_meta_field_row(
							'cost_product_fields_input',
							self::PNL_COST_PRODUCT_FIELDS,
							$cost_product_fields,
							__( 'Custom Line Item Meta', 'opti-analytics' ),
							__( 'Line-item meta keys — aggregated as value × quantity.', 'opti-analytics' ),
							'_packaging_cost',
							$product_keys,
							'opti-cost-prod',
							false
						);
						?>
					</div>
				</div>

				<?php // ── CARD 3: View Only ───────────────────────────── ?>
				<div class="opti-settings-card">
					<div class="opti-settings-card-header">
						<span class="dashicons dashicons-visibility"></span>
						<h2><?php esc_html_e( 'View Only Fields', 'opti-analytics' ); ?></h2>
						<p><?php esc_html_e( 'Shown on dashboard as KPI cards — no impact on P&L', 'opti-analytics' ); ?></p>
					</div>
					<div class="opti-settings-card-body">
						<?php
						$this->render_meta_field_row(
							'vo_order_fields_input',
							self::VIEWONLY_ORDER_FIELDS,
							$vo_order_fields,
							__( 'Order Meta (View Only)', 'opti-analytics' ),
							__( 'Order-level meta keys — displayed on dashboard but excluded from P&L.', 'opti-analytics' ),
							'_order_notes_count',
							$order_keys,
							'opti-vo-order',
							true
						);
						$this->render_meta_field_row(
							'vo_product_fields_input',
							self::VIEWONLY_PRODUCT_FIELDS,
							$vo_product_fields,
							__( 'Line Item Meta (View Only)', 'opti-analytics' ),
							__( 'Line-item meta keys — displayed on dashboard but excluded from P&L.', 'opti-analytics' ),
							'_custom_attribute',
							$product_keys,
							'opti-vo-prod',
							false
						);
						?>
					</div>
				</div>

				<?php // ── CARD 4: Other Settings ──────────────────────── ?>
				<div class="opti-settings-card">
					<div class="opti-settings-card-header">
						<span class="dashicons dashicons-admin-generic"></span>
						<h2><?php esc_html_e( 'Other Settings', 'opti-analytics' ); ?></h2>
					</div>
					<div class="opti-settings-card-body">
						<div class="opti-field-row">
							<label for="enable_manual_shipping" style="font-weight: normal;">
								<input type="checkbox" id="enable_manual_shipping"
									name="<?php echo esc_attr( self::MANUAL_SHIPPING_OPTION ); ?>"
									value="1" <?php checked( get_option( self::MANUAL_SHIPPING_OPTION, false ), 1 ); ?> />
								<?php esc_html_e( 'Enable Manual Shipping Cost', 'opti-analytics' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Adds an "Actual Shipping Cost" input field on the WooCommerce order edit screen.', 'opti-analytics' ); ?>
							</p>
						</div>
					</div>
				</div>

				<?php submit_button( __( 'Save Settings', 'opti-analytics' ) ); ?>
			</form>
		</div>

		<script>
			document.addEventListener('DOMContentLoaded', function() {
				/* ── Click-to-add meta tags ── */
				function setupAddButtons(inputId, btnClass) {
					var input = document.getElementById(inputId);
					if (!input) return;
					document.querySelectorAll('.' + btnClass).forEach(function(btn) {
						btn.addEventListener('click', function(e) {
							e.preventDefault();
							var key = this.getAttribute('data-key');
							var current = input.value.split(',').map(function(i){ return i.trim(); }).filter(Boolean);
							if (current.indexOf(key) === -1) {
								current.push(key);
								input.value = current.join(', ');
								this.classList.add('added');
							}
						});
					});
				}

				setupAddButtons('revenue_order_fields_input', 'opti-rev-order');
				setupAddButtons('revenue_product_fields_input', 'opti-rev-prod');
				setupAddButtons('cost_order_fields_input', 'opti-cost-order');
				setupAddButtons('cost_product_fields_input', 'opti-cost-prod');
				setupAddButtons('vo_order_fields_input', 'opti-vo-order');
				setupAddButtons('vo_product_fields_input', 'opti-vo-prod');

				/* ── Collapsible discovery panels ── */
				document.querySelectorAll('.opti-discovery-toggle').forEach(function(btn) {
					btn.addEventListener('click', function() {
						var panel = this.nextElementSibling;
						this.classList.toggle('open');
						panel.classList.toggle('open');
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * Renders a meta field row with input + collapsible discovery panel.
	 *
	 * @param string $input_id    HTML id for the input.
	 * @param string $option_name The WP option name.
	 * @param string $value       Current saved value.
	 * @param string $label       Field label.
	 * @param string $desc        Field description.
	 * @param string $placeholder Placeholder text.
	 * @param array  $keys        Discovered meta keys.
	 * @param string $btn_class   CSS class for click-to-add buttons.
	 * @param bool   $show_source Whether to show HPOS/Legacy source tag.
	 */
	private function render_meta_field_row( string $input_id, string $option_name, string $value, string $label, string $desc, string $placeholder, array $keys, string $btn_class, bool $show_source ): void {
		?>
		<div class="opti-field-row">
			<label for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $label ); ?></label>
			<input type="text" id="<?php echo esc_attr( $input_id ); ?>"
				name="<?php echo esc_attr( $option_name ); ?>"
				value="<?php echo esc_attr( $value ); ?>"
				class="regular-text"
				placeholder="<?php echo esc_attr( $placeholder ); ?>" />
			<p class="description"><?php echo esc_html( $desc ); ?></p>

			<?php if ( ! empty( $keys ) ) : ?>
				<button type="button" class="opti-discovery-toggle">
					<span class="dashicons dashicons-search"></span>
					<?php esc_html_e( 'Browse discovered fields', 'opti-analytics' ); ?>
					<span class="dashicons dashicons-arrow-down-alt2"></span>
				</button>
				<div class="opti-discovery-panel">
					<?php
					foreach ( $keys as $key => $source ) :
						$meta_key = $show_source ? $key : $source;
						?>
						<a href="#" class="opti-meta-tag <?php echo esc_attr( $btn_class ); ?>"
							data-key="<?php echo esc_attr( $meta_key ); ?>">
							+ <?php echo esc_html( $meta_key ); ?>
							<?php if ( $show_source ) : ?>
								<span class="opti-tag-source">(<?php echo esc_html( $source ); ?>)</span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Parses a comma-separated option string into a clean array.
	 *
	 * @param string $option_name The option key.
	 * @return array<string> Array of trimmed, non-empty strings.
	 */
	public static function parse_csv_option( string $option_name ): array {
		$raw = get_option( $option_name, '' );
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}
		return array_filter( array_map( 'trim', explode( ',', $raw ) ) );
	}
}
