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

	/**
	 * The option key saved in the database for custom fields.
	 */
	public const OPTION_NAME = 'opti_analytics_custom_fields';

	/**
	 * The option key for line-item-level custom fields (e.g. snapshotted COGS).
	 */
	public const PRODUCT_FIELDS_OPTION = 'opti_analytics_product_fields';

	/**
	 * The option key for enabling manual shipping cost.
	 */
	public const MANUAL_SHIPPING_OPTION = 'opti_analytics_enable_manual_shipping';

	/**
	 * Registers settings hooks.
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_settings_menu' ) );
		add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
	}

	/**
	 * Registers the settings menu under WooCommerce.
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
	 * Registers the setting with WordPress.
	 */
	public function register_plugin_settings(): void {
		register_setting(
			'opti_analytics_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'opti_analytics_settings_group',
			self::PRODUCT_FIELDS_OPTION,
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'opti_analytics_settings_group',
			self::MANUAL_SHIPPING_OPTION,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
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
		// Retrieve existing settings from the database.
		$current_fields         = get_option( self::OPTION_NAME, '' );
		$current_product_fields = get_option( self::PRODUCT_FIELDS_OPTION, '' );
		$available_keys         = $this->get_available_order_meta_keys();
		$available_product_keys = $this->get_available_product_meta_keys();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Opti Analytics Settings', 'opti-analytics' ); ?></h1>
			
			<form method="post" action="options.php">
				<?php
				// This outputs the hidden security fields required by the Settings API.
				settings_fields( 'opti_analytics_settings_group' );
				?>
				
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="custom_fields_input">
									<?php esc_html_e( 'Order-Level Custom Fields', 'opti-analytics' ); ?>
								</label>
							</th>
							<td>
								<input type="text" 
										id="custom_fields_input" 
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>" 
										value="<?php echo esc_attr( $current_fields ); ?>" 
										class="regular-text" 
										placeholder="_stripe_fee, _cod_charge" />
								<p class="description">
									<?php esc_html_e( 'Meta keys stored on each order. Values are summed across all orders in the date range.', 'opti-analytics' ); ?>
								</p>

								<?php if ( ! empty( $available_keys ) ) : ?>
									<div style="margin-top: 15px; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
										<p style="margin: 0 0 10px 0; font-weight: 600;">
											<?php esc_html_e( 'Fields found in your orders (Click to add):', 'opti-analytics' ); ?>
										</p>
										<?php foreach ( $available_keys as $key => $source ) : ?>
											<a href="#" class="opti-add-key-btn" data-key="<?php echo esc_attr( $key ); ?>" style="display: inline-block; padding: 4px 8px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px; text-decoration: none; color: #50575e; margin: 0 5px 5px 0; font-size: 12px; transition: background 0.2s;">
												+ <?php echo esc_html( $key ); ?>
												<span style="font-size: 10px; color: #999; margin-left: 2px;">(<?php echo esc_html( $source ); ?>)</span>
											</a>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="product_fields_input">
									<?php esc_html_e( 'Line Item Custom Fields', 'opti-analytics' ); ?>
								</label>
							</th>
							<td>
								<input type="text" 
										id="product_fields_input" 
										name="<?php echo esc_attr( self::PRODUCT_FIELDS_OPTION ); ?>" 
										value="<?php echo esc_attr( $current_product_fields ); ?>" 
										class="regular-text" 
										placeholder="_historical_cogs, _cost_price" />
								<p class="description">
									<?php esc_html_e( 'Meta keys stored on order line items (e.g. snapshotted COGS). Aggregated as value × quantity for each item sold.', 'opti-analytics' ); ?>
								</p>

								<?php if ( ! empty( $available_product_keys ) ) : ?>
									<div style="margin-top: 15px; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
										<p style="margin: 0 0 10px 0; font-weight: 600;">
											<?php esc_html_e( 'Fields found on your products (Click to add):', 'opti-analytics' ); ?>
										</p>
										<?php foreach ( $available_product_keys as $key ) : ?>
											<a href="#" class="opti-add-product-key-btn" data-key="<?php echo esc_attr( $key ); ?>" style="display: inline-block; padding: 4px 8px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px; text-decoration: none; color: #50575e; margin: 0 5px 5px 0; font-size: 12px; transition: background 0.2s;">
												+ <?php echo esc_html( $key ); ?>
											</a>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</td>
						</tr>
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
									<?php esc_html_e( 'Enable the manual shipping cost on the order edit screen. By enabling it you can add the manual shipping cost on the order edit screen.', 'opti-analytics' ); ?>
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

				setupAddButtons('custom_fields_input', 'opti-add-key-btn');
				setupAddButtons('product_fields_input', 'opti-add-product-key-btn');
			});
		</script>
		<?php
	}
}
