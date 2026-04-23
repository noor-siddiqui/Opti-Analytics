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
			self::MANUAL_SHIPPING_OPTION,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
	}

	/**
	 * Gets available custom fields from recent orders (safely without crashing DB).
	 *
	 * @return array<string> Array of meta keys.
	 */
	private function get_available_order_meta_keys(): array {
		$engine         = new Data_Engine();
		$orders         = $engine->get_orders( 1, 50 );
		$available_keys = array();

		foreach ( $orders as $order ) {
			$meta_data = $order->get_meta_data();

			foreach ( $meta_data as $meta ) {
				$key                    = $meta->key;
				$available_keys[ $key ] = $key;
			}
		}

		ksort( $available_keys );

		return $available_keys;
	}

	/**
	 * Renders the HTML for the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		// Retrieve existing settings from the database.
		$current_fields = get_option( self::OPTION_NAME, '' );
		$available_keys = $this->get_available_order_meta_keys();
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
									<?php esc_html_e( 'Custom Fields to Export', 'opti-analytics' ); ?>
								</label>
							</th>
							<td>
								<input type="text" 
										id="custom_fields_input" 
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>" 
										value="<?php echo esc_attr( $current_fields ); ?>" 
										class="regular-text" 
										placeholder="_billing_company, delivery_date" />
								<p class="description">
									<?php esc_html_e( 'Enter the meta keys of the custom fields you want to include in your Excel export, separated by commas.', 'opti-analytics' ); ?>
								</p>

								<?php if ( ! empty( $available_keys ) ) : ?>
									<div style="margin-top: 15px; background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px;">
										<p style="margin: 0 0 10px 0; font-weight: 600;">
											<?php esc_html_e( 'Fields found in your recent orders (Click to add):', 'opti-analytics' ); ?>
										</p>
										<?php foreach ( $available_keys as $key ) : ?>
											<a href="#" class="opti-add-key-btn" data-key="<?php echo esc_attr( $key ); ?>" style="display: inline-block; padding: 4px 8px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 3px; text-decoration: none; color: #50575e; margin: 0 5px 5px 0; font-size: 12px; transition: background 0.2s;">
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
									<?php esc_html_e( 'Injects a "Manual Shipping Cost" field directly beneath the shipping address on the single order admin view.', 'opti-analytics' ); ?>
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
				var input = document.getElementById('custom_fields_input');
				var buttons = document.querySelectorAll('.opti-add-key-btn');
				
				buttons.forEach(function(btn) {
					btn.addEventListener('click', function(e) {
						e.preventDefault();
						var key = this.getAttribute('data-key');
						
						// Parse current values
						var current = input.value.split(',').map(function(item) { 
							return item.trim(); 
						}).filter(function(item) { 
							return item !== ''; 
						});
						
						// Add key if it doesn't exist
						if (current.indexOf(key) === -1) {
							current.push(key);
							input.value = current.join(', ');
							
							// Visual feedback
							this.style.background = '#d1e5db';
							this.style.borderColor = '#9abda9';
						}
					});
				});
			});
		</script>
		<?php
	}
}
