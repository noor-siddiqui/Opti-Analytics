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
	 * Queries both `wp_wc_orders_meta` (HPOS) and `wp_postmeta` (legacy) tables
	 * directly to discover all unique meta keys, even from plugins that don't use HPOS.
	 *
	 * @return array<string, string> Associative array of meta_key => source label ('HPOS', 'Legacy', or 'Both').
	 */
	private function get_available_order_meta_keys(): array {
		global $wpdb;

		$hpos_keys   = array();
		$legacy_keys = array();

		// --- HPOS table: wp_wc_orders_meta ---
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

		// --- Legacy table: wp_postmeta (for shop_order post types) ---
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$legacy_results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_key
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s
				ORDER BY pm.meta_key ASC",
				'shop_order'
			)
		);
		if ( $legacy_results ) {
			$legacy_keys = array_flip( $legacy_results );
		}

		// Merge and tag the source.
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
	 * Gets available product/variation meta keys from the database for discovery purposes.
	 *
	 * Queries wp_postmeta for product and product_variation post types.
	 *
	 * @return array<string> Array of unique meta keys.
	 */
	private function get_available_product_meta_keys(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_key
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type IN (%s, %s)
				ORDER BY pm.meta_key ASC",
				'product',
				'product_variation'
			)
		);

		return $results ? $results : array();
	}

	/**
	 * Renders the HTML for the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		// Retrieve existing settings.
		$revenue_builtins       = get_option( self::PNL_REVENUE_BUILTINS, array() );
		$cost_builtins          = get_option( self::PNL_COST_BUILTINS, array() );
		$revenue_order_fields   = get_option( self::PNL_REVENUE_ORDER_FIELDS, '' );
		$revenue_product_fields = get_option( self::PNL_REVENUE_PRODUCT_FIELDS, '' );
		$cost_order_fields      = get_option( self::PNL_COST_ORDER_FIELDS, '' );
		$cost_product_fields    = get_option( self::PNL_COST_PRODUCT_FIELDS, '' );
		$available_keys         = $this->get_available_order_meta_keys();
		$available_product_keys = $this->get_available_product_meta_keys();

		if ( ! is_array( $revenue_builtins ) ) {
			$revenue_builtins = array();
		}
		if ( ! is_array( $cost_builtins ) ) {
			$cost_builtins = array();
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Opti Analytics Settings', 'opti-analytics' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'opti_analytics_settings_group' ); ?>

				<!-- ═══════════════════════════════════════════════════
					REVENUE SOURCES
					═══════════════════════════════════════════════════ -->
				<h2 class="title"><?php esc_html_e( '💰 Revenue Sources', 'opti-analytics' ); ?></h2>
				<p><?php esc_html_e( 'Select the built-in metrics and custom fields that count as revenue for your P&L calculation.', 'opti-analytics' ); ?></p>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Built-in Revenue', 'opti-analytics' ); ?></th>
							<td>
								<!-- Hidden field ensures an empty array is submitted when nothing is checked. -->
								<input type="hidden" name="<?php echo esc_attr( self::PNL_REVENUE_BUILTINS ); ?>" value="">
								<fieldset>
									<?php foreach ( self::BUILTIN_REVENUE_SOURCES as $key => $label ) : ?>
										<label style="display: block; margin-bottom: 6px;">
											<input type="checkbox"
												name="<?php echo esc_attr( self::PNL_REVENUE_BUILTINS ); ?>[]"
												value="<?php echo esc_attr( $key ); ?>"
												<?php checked( in_array( $key, $revenue_builtins, true ) ); ?> />
											<?php echo esc_html( $label ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
								<p class="description">
									<?php esc_html_e( 'Choose which built-in metrics represent money collected from customers.', 'opti-analytics' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="revenue_order_fields_input">
									<?php esc_html_e( 'Custom Revenue (Order Meta)', 'opti-analytics' ); ?>
								</label>
							</th>
							<td>
								<input type="text"
									id="revenue_order_fields_input"
									name="<?php echo esc_attr( self::PNL_REVENUE_ORDER_FIELDS ); ?>"
									value="<?php echo esc_attr( $revenue_order_fields ); ?>"
									class="regular-text"
									placeholder="_tip_amount, _insurance_fee" />
								<p class="description">
									<?php esc_html_e( 'Order-level meta keys to include as revenue. Values are summed per order.', 'opti-analytics' ); ?>
								</p>
								<?php $this->render_click_to_add_buttons( $available_keys, 'opti-add-rev-order-btn', true ); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="revenue_product_fields_input">
									<?php esc_html_e( 'Custom Revenue (Line Item Meta)', 'opti-analytics' ); ?>
								</label>
							</th>
							<td>
								<input type="text"
									id="revenue_product_fields_input"
									name="<?php echo esc_attr( self::PNL_REVENUE_PRODUCT_FIELDS ); ?>"
									value="<?php echo esc_attr( $revenue_product_fields ); ?>"
									class="regular-text"
									placeholder="_custom_revenue_per_unit" />
								<p class="description">
									<?php esc_html_e( 'Line-item meta keys to include as revenue. Aggregated as value × quantity.', 'opti-analytics' ); ?>
								</p>
								<?php $this->render_click_to_add_buttons( $available_product_keys, 'opti-add-rev-product-btn', false ); ?>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- ═══════════════════════════════════════════════════
					COST SOURCES
					═══════════════════════════════════════════════════ -->
				<h2 class="title"><?php esc_html_e( '📦 Cost Sources', 'opti-analytics' ); ?></h2>
				<p><?php esc_html_e( 'Select the built-in metrics and custom fields that count as costs for your P&L calculation.', 'opti-analytics' ); ?></p>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Built-in Costs', 'opti-analytics' ); ?></th>
							<td>
								<input type="hidden" name="<?php echo esc_attr( self::PNL_COST_BUILTINS ); ?>" value="">
								<fieldset>
									<?php foreach ( self::BUILTIN_COST_SOURCES as $key => $label ) : ?>
										<label style="display: block; margin-bottom: 6px;">
											<input type="checkbox"
												name="<?php echo esc_attr( self::PNL_COST_BUILTINS ); ?>[]"
												value="<?php echo esc_attr( $key ); ?>"
												<?php checked( in_array( $key, $cost_builtins, true ) ); ?> />
											<?php echo esc_html( $label ); ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
								<p class="description">
									<?php esc_html_e( 'Choose which built-in metrics represent costs of fulfilling orders.', 'opti-analytics' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cost_order_fields_input">
									<?php esc_html_e( 'Custom Costs (Order Meta)', 'opti-analytics' ); ?>
								</label>
							</th>
							<td>
								<input type="text"
									id="cost_order_fields_input"
									name="<?php echo esc_attr( self::PNL_COST_ORDER_FIELDS ); ?>"
									value="<?php echo esc_attr( $cost_order_fields ); ?>"
									class="regular-text"
									placeholder="_stripe_fee, _cod_charge" />
								<p class="description">
									<?php esc_html_e( 'Order-level meta keys to include as costs. Values are summed per order.', 'opti-analytics' ); ?>
								</p>
								<?php $this->render_click_to_add_buttons( $available_keys, 'opti-add-cost-order-btn', true ); ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="cost_product_fields_input">
									<?php esc_html_e( 'Custom Costs (Line Item Meta)', 'opti-analytics' ); ?>
								</label>
							</th>
							<td>
								<input type="text"
									id="cost_product_fields_input"
									name="<?php echo esc_attr( self::PNL_COST_PRODUCT_FIELDS ); ?>"
									value="<?php echo esc_attr( $cost_product_fields ); ?>"
									class="regular-text"
									placeholder="_packaging_cost, _handling_fee" />
								<p class="description">
									<?php esc_html_e( 'Line-item meta keys to include as costs. Aggregated as value × quantity.', 'opti-analytics' ); ?>
								</p>
								<?php $this->render_click_to_add_buttons( $available_product_keys, 'opti-add-cost-product-btn', false ); ?>
							</td>
						</tr>
					</tbody>
				</table>

				<!-- ═══════════════════════════════════════════════════
					OTHER SETTINGS
					═══════════════════════════════════════════════════ -->
				<h2 class="title"><?php esc_html_e( '⚙️ Other Settings', 'opti-analytics' ); ?></h2>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="enable_manual_shipping">
									<?php esc_html_e( 'Enable Manual Shipping Cost', 'opti-analytics' ); ?>
								</label>
							</th>
							<td>
								<input type="checkbox"
									id="enable_manual_shipping"
									name="<?php echo esc_attr( self::MANUAL_SHIPPING_OPTION ); ?>"
									value="1" <?php checked( get_option( self::MANUAL_SHIPPING_OPTION, false ), 1 ); ?> />
								<p class="description">
									<?php esc_html_e( 'Enable the manual shipping cost on the order edit screen. By enabling it you can add the actual shipping cost on the order edit screen.', 'opti-analytics' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Settings', 'opti-analytics' ) ); ?>
			</form>
		</div>

		<script>
			document.addEventListener('DOMContentLoaded', function() {
				function setupAddButtons(inputId, btnClass) {
					var input = document.getElementById(inputId);
					if (!input) return;
					var buttons = document.querySelectorAll('.' + btnClass);

					buttons.forEach(function(btn) {
						btn.addEventListener('click', function(e) {
							e.preventDefault();
							var key = this.getAttribute('data-key');

							var current = input.value.split(',').map(function(item) {
								return item.trim();
							}).filter(function(item) {
								return item !== '';
							});

							if (current.indexOf(key) === -1) {
								current.push(key);
								input.value = current.join(', ');
								this.style.background = '#d1e5db';
								this.style.borderColor = '#9abda9';
							}
						});
					});
				}

				setupAddButtons('revenue_order_fields_input', 'opti-add-rev-order-btn');
				setupAddButtons('revenue_product_fields_input', 'opti-add-rev-product-btn');
				setupAddButtons('cost_order_fields_input', 'opti-add-cost-order-btn');
				setupAddButtons('cost_product_fields_input', 'opti-add-cost-product-btn');
			});
		</script>
		<?php
	}

	/**
	 * Renders the "click to add" discovery buttons for a list of meta keys.
	 *
	 * @param array<string, string>|array<string> $keys      The available meta keys.
	 * @param string                              $btn_class CSS class for the buttons.
	 * @param bool                                $show_source Whether to show the source label (HPOS/Legacy).
	 */
	private function render_click_to_add_buttons( array $keys, string $btn_class, bool $show_source ): void {
		if ( empty( $keys ) ) {
			return;
		}
		?>
		<div style="margin-top: 15px; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
			<p style="margin: 0 0 10px 0; font-weight: 600;">
				<?php esc_html_e( 'Discovered fields (click to add):', 'opti-analytics' ); ?>
			</p>
			<?php
			foreach ( $keys as $key => $source ) :
				// For product meta, $key is numeric and $source is the meta key.
				$meta_key = $show_source ? $key : $source;
				?>
				<a href="#" class="<?php echo esc_attr( $btn_class ); ?>"
					data-key="<?php echo esc_attr( $meta_key ); ?>"
					style="display: inline-block; padding: 4px 8px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px; text-decoration: none; color: #50575e; margin: 0 5px 5px 0; font-size: 12px; transition: background 0.2s;">
					+ <?php echo esc_html( $meta_key ); ?>
					<?php if ( $show_source ) : ?>
						<span style="font-size: 10px; color: #999; margin-left: 2px;">(<?php echo esc_html( $source ); ?>)</span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
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
