<?php

namespace VoxelPayPal\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wallet Controller
 * Main controller for wallet functionality - initializes sub-controllers and handles settings
 */
class Wallet_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks(): void {
		// Initialize sub-controllers
		$this->on( 'init', '@init_sub_controllers', 5 );

		// Register wallet settings in Voxel admin
		$this->on( 'admin_init', '@register_wallet_settings' );

		// Add wallet settings to Voxel payments page
		$this->on( 'admin_footer', '@inject_wallet_settings_ui' );

		// Handle wallet settings save
		$this->on( 'voxel_ajax_wallet.settings.save', '@handle_save_settings' );

		// Ensure database table exists
		$this->on( 'admin_init', '@maybe_create_table' );

		// Inject wallet checkout toggle on frontend
		$this->on( 'wp_footer', '@inject_checkout_wallet_toggle' );
	}

	/**
	 * Initialize sub-controllers
	 */
	protected function init_sub_controllers(): void {
		new Wallet_Ajax_Controller();
		new Wallet_Deposit_Controller();
	}

	/**
	 * Register wallet settings
	 */
	protected function register_wallet_settings(): void {
		// Settings are handled via Voxel's settings system
		// We just need to make sure defaults are set
		if ( \Voxel\get( 'payments.wallet.enabled' ) === null ) {
			// Initialize default settings if they don't exist
			// Note: We don't actually save here, just check
		}
	}

	/**
	 * Inject wallet settings UI into Voxel payments page
	 */
	protected function inject_wallet_settings_ui(): void {
		// Only run on Voxel payments page
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'voxel-payments' ) {
			return;
		}

		$enabled = \VoxelPayPal\Wallet_Client::is_enabled();
		$min_deposit = \VoxelPayPal\Wallet_Client::get_min_deposit();
		$max_deposit = \VoxelPayPal\Wallet_Client::get_max_deposit();
		$preset_amounts = \VoxelPayPal\Wallet_Client::get_preset_amounts();
		$currency = \VoxelPayPal\Wallet_Client::get_site_currency();

		?>
		<script>
		(function() {
			// Wait for Vue to be ready
			function injectWalletSettings() {
				// Check if wallet panel already exists
				if (document.querySelector('.vx-panel.provider-wallet')) {
					return true;
				}

				// Find an existing provider panel to get its parent container
				const existingPanel = document.querySelector('.vx-panel[class*="provider-"]');
				if (!existingPanel) {
					return false;
				}

				const providersContainer = existingPanel.parentElement;
				if (!providersContainer) {
					return false;
				}

				// Create wallet panel
				const walletPanel = document.createElement('div');
				walletPanel.className = 'vx-panel provider-wallet <?php echo $enabled ? 'active' : ''; ?>';
				walletPanel.innerHTML = `
					<div class="panel-image wallet-panel">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
							<path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
						</svg>
					</div>
					<div class="panel-info">
						<div class="panel-title"><?php _e( 'Wallet', 'voxel-payment-gateways' ); ?></div>
						<div class="panel-description"><?php _e( 'Allow customers to add funds and pay with wallet balance', 'voxel-payment-gateways' ); ?></div>
					</div>
					<div class="panel-toggle">
						<label class="vx-switch">
							<input type="checkbox" id="wallet-enabled-toggle" <?php echo $enabled ? 'checked' : ''; ?>>
							<span class="vx-switch-slider"></span>
						</label>
					</div>
				`;

				// Add toggle handler
				const toggle = walletPanel.querySelector('#wallet-enabled-toggle');
				toggle.addEventListener('change', function() {
					walletPanel.classList.toggle('active', this.checked);
					saveWalletSettings();
				});

				// Add click handler for the panel (not the toggle)
				walletPanel.addEventListener('click', function(e) {
					// Don't trigger if clicking on the toggle
					if (e.target.closest('.panel-toggle')) {
						return;
					}
					// Toggle the settings panel
					const settingsPanel = document.getElementById('wallet-settings-panel');
					if (settingsPanel) {
						settingsPanel.style.display = settingsPanel.style.display === 'none' ? 'block' : 'none';
					}
				});

				// Add after other panels
				providersContainer.appendChild(walletPanel);

				// Create settings panel
				const settingsPanel = document.createElement('div');
				settingsPanel.id = 'wallet-settings-panel';
				settingsPanel.style.display = 'none';
				settingsPanel.innerHTML = `
					<div class="wallet-settings-container">
						<h3><?php _e( 'Wallet Settings', 'voxel-payment-gateways' ); ?></h3>

						<div class="wallet-setting-group">
							<label><?php _e( 'Minimum Deposit Amount', 'voxel-payment-gateways' ); ?></label>
							<div class="wallet-input-wrapper">
								<span class="wallet-currency"><?php echo esc_html( $currency ); ?></span>
								<input type="number" id="wallet-min-deposit" value="<?php echo esc_attr( $min_deposit ); ?>" min="0.01" step="0.01">
							</div>
						</div>

						<div class="wallet-setting-group">
							<label><?php _e( 'Maximum Deposit Amount', 'voxel-payment-gateways' ); ?></label>
							<div class="wallet-input-wrapper">
								<span class="wallet-currency"><?php echo esc_html( $currency ); ?></span>
								<input type="number" id="wallet-max-deposit" value="<?php echo esc_attr( $max_deposit ); ?>" min="1" step="1">
							</div>
						</div>

						<div class="wallet-setting-group">
							<label><?php _e( 'Quick Deposit Amounts (comma-separated)', 'voxel-payment-gateways' ); ?></label>
							<input type="text" id="wallet-preset-amounts" value="<?php echo esc_attr( implode( ', ', $preset_amounts ) ); ?>" placeholder="10, 25, 50, 100">
							<small><?php _e( 'These amounts will appear as quick-select buttons in the wallet widget.', 'voxel-payment-gateways' ); ?></small>
						</div>

						<div class="wallet-settings-note">
							<p><strong><?php _e( 'Note:', 'voxel-payment-gateways' ); ?></strong> <?php _e( 'Wallet uses the same currency as your configured payment gateway. Customers can add funds using the active payment gateway (PayPal, Stripe, etc.) and then use their wallet balance for future purchases.', 'voxel-payment-gateways' ); ?></p>
						</div>

						<div class="wallet-settings-actions">
							<button type="button" id="wallet-save-settings" class="button button-primary">
								<?php _e( 'Save Wallet Settings', 'voxel-payment-gateways' ); ?>
							</button>
							<span id="wallet-save-status"></span>
						</div>
					</div>
				`;

				// Insert after wallet panel
				walletPanel.after(settingsPanel);

				// Add save handler
				document.getElementById('wallet-save-settings').addEventListener('click', saveWalletSettings);

				return true;
			}

			async function saveWalletSettings() {
				const statusEl = document.getElementById('wallet-save-status');
				const saveBtn = document.getElementById('wallet-save-settings');

				if (saveBtn) {
					saveBtn.disabled = true;
				}
				if (statusEl) {
					statusEl.textContent = '<?php echo esc_js( __( 'Saving...', 'voxel-payment-gateways' ) ); ?>';
				}

				const enabled = document.getElementById('wallet-enabled-toggle')?.checked ?? false;
				const minDeposit = document.getElementById('wallet-min-deposit')?.value ?? 1;
				const maxDeposit = document.getElementById('wallet-max-deposit')?.value ?? 10000;
				const presetAmounts = document.getElementById('wallet-preset-amounts')?.value ?? '10, 25, 50, 100';

				try {
					const response = await fetch('<?php echo home_url( '/?vx=1&action=wallet.settings.save' ); ?>', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
						},
						body: JSON.stringify({
							enabled: enabled,
							min_deposit: parseFloat(minDeposit),
							max_deposit: parseFloat(maxDeposit),
							preset_amounts: presetAmounts
						})
					});

					const data = await response.json();

					if (data.success) {
						if (statusEl) {
							statusEl.textContent = '<?php echo esc_js( __( 'Saved!', 'voxel-payment-gateways' ) ); ?>';
							statusEl.style.color = '#4caf50';
							setTimeout(() => { statusEl.textContent = ''; }, 2000);
						}
					} else {
						if (statusEl) {
							statusEl.textContent = data.message || '<?php echo esc_js( __( 'Error saving settings', 'voxel-payment-gateways' ) ); ?>';
							statusEl.style.color = '#f44336';
						}
					}
				} catch (error) {
					if (statusEl) {
						statusEl.textContent = '<?php echo esc_js( __( 'Error saving settings', 'voxel-payment-gateways' ) ); ?>';
						statusEl.style.color = '#f44336';
					}
				}

				if (saveBtn) {
					saveBtn.disabled = false;
				}
			}

			// Poll for Vue render
			var attempts = 0;
			var maxAttempts = 100;
			var pollInterval = setInterval(function() {
				attempts++;
				if (injectWalletSettings() || attempts >= maxAttempts) {
					clearInterval(pollInterval);
				}
			}, 100);

			// Also watch for DOM changes (Vue/SPA navigation)
			var observer = new MutationObserver(function(mutations) {
				injectWalletSettings();
			});

			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
		})();
		</script>
		<style>
			/* Wallet Panel Styles */
			.wallet-panel {
				background: #4caf50 !important;
				width: 50px;
				height: 50px;
				border-radius: 10px;
				display: flex;
				align-items: center;
				justify-content: center;
				flex-shrink: 0;
			}
			.wallet-panel svg {
				width: 28px !important;
				height: 28px !important;
				fill: #fff !important;
			}
			.vx-panel:not(.active).provider-wallet .wallet-panel {
				filter: grayscale(1) !important;
				opacity: 0.6 !important;
			}
			.vx-panel.provider-wallet.active {
				background: linear-gradient(45deg, rgba(76, 175, 80, .25) -20%, transparent 70%) !important;
				border-color: rgba(76, 175, 80, .54) !important;
			}
			.vx-panel.provider-wallet {
				cursor: pointer;
			}
			.vx-panel.provider-wallet .panel-title {
				color: #fff;
			}
			.vx-panel.provider-wallet .panel-description {
				color: rgba(255, 255, 255, 0.7);
			}

			/* Wallet Settings Panel */
			#wallet-settings-panel {
				margin: 20px 0;
				padding: 20px;
				background: #fff;
				border: 1px solid #e0e0e0;
				border-radius: 8px;
			}
			.wallet-settings-container h3 {
				margin: 0 0 20px 0;
				font-size: 16px;
				font-weight: 600;
			}
			.wallet-setting-group {
				margin-bottom: 20px;
			}
			.wallet-setting-group label {
				display: block;
				margin-bottom: 8px;
				font-weight: 500;
				font-size: 14px;
			}
			.wallet-setting-group input[type="number"],
			.wallet-setting-group input[type="text"] {
				width: 100%;
				max-width: 300px;
				padding: 8px 12px;
				border: 1px solid #ddd;
				border-radius: 4px;
				font-size: 14px;
			}
			.wallet-input-wrapper {
				display: flex;
				align-items: center;
				gap: 8px;
			}
			.wallet-currency {
				font-weight: 600;
				color: #666;
			}
			.wallet-setting-group small {
				display: block;
				margin-top: 4px;
				color: #888;
				font-size: 12px;
			}
			.wallet-settings-note {
				background: #f5f5f5;
				padding: 12px;
				border-radius: 4px;
				margin-bottom: 20px;
			}
			.wallet-settings-note p {
				margin: 0;
				font-size: 13px;
				color: #666;
			}
			.wallet-settings-actions {
				display: flex;
				align-items: center;
				gap: 12px;
			}
			#wallet-save-status {
				font-size: 13px;
			}
		</style>
		<?php
	}

	/**
	 * Handle saving wallet settings
	 */
	protected function handle_save_settings(): void {
		// Check admin permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Permission denied', 'voxel-payment-gateways' ),
			] );
			return;
		}

		// Get JSON input
		$input = json_decode( file_get_contents( 'php://input' ), true );

		if ( ! is_array( $input ) ) {
			wp_send_json( [
				'success' => false,
				'message' => __( 'Invalid input', 'voxel-payment-gateways' ),
			] );
			return;
		}

		// Build wallet settings
		$wallet_settings = [
			'enabled' => ! empty( $input['enabled'] ),
			'min_deposit' => max( 0.01, floatval( $input['min_deposit'] ?? 1 ) ),
			'max_deposit' => max( 1, floatval( $input['max_deposit'] ?? 10000 ) ),
			'preset_amounts' => $this->parse_preset_amounts( $input['preset_amounts'] ?? '10, 25, 50, 100' ),
		];

		// Save settings via WordPress option (same pattern as PayPal marketplace settings)
		update_option( 'voxel_wallet_settings', $wallet_settings );

		wp_send_json( [
			'success' => true,
			'message' => __( 'Settings saved', 'voxel-payment-gateways' ),
		] );
	}

	/**
	 * Parse preset amounts from string input
	 */
	private function parse_preset_amounts( string $input ): array {
		$amounts = array_map( 'trim', explode( ',', $input ) );
		$amounts = array_filter( $amounts, function( $val ) {
			return is_numeric( $val ) && floatval( $val ) > 0;
		} );
		$amounts = array_map( 'floatval', $amounts );
		$amounts = array_values( $amounts );

		if ( empty( $amounts ) ) {
			return [ 10, 25, 50, 100 ];
		}

		return $amounts;
	}

	/**
	 * Ensure database table exists on admin init
	 */
	protected function maybe_create_table(): void {
		// Only run once per day
		$last_check = get_transient( 'voxel_wallet_table_check' );
		if ( $last_check ) {
			return;
		}

		if ( ! \VoxelPayPal\Wallet_Client::table_exists() ) {
			\VoxelPayPal\Wallet_Client::create_tables();
		}

		set_transient( 'voxel_wallet_table_check', true, DAY_IN_SECONDS );
	}

	/**
	 * Inject wallet checkout toggle on frontend
	 */
	protected function inject_checkout_wallet_toggle(): void {
		// Only on frontend, not admin
		if ( is_admin() ) {
			return;
		}

		// Check if wallet is enabled
		if ( ! \VoxelPayPal\Wallet_Client::is_enabled() ) {
			return;
		}

		// Only for logged in users
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		$balance = \VoxelPayPal\Wallet_Client::get_balance( $user_id );
		$balance_formatted = \VoxelPayPal\Wallet_Client::get_balance_formatted( $user_id );
		$currency = \VoxelPayPal\Wallet_Client::get_site_currency();

		?>
		<script>
		(function() {
			const WALLET_BALANCE = <?php echo floatval( $balance ); ?>;
			const WALLET_BALANCE_FORMATTED = '<?php echo esc_js( $balance_formatted ); ?>';
			const CHECKOUT_URL = '<?php echo esc_url_raw( home_url( '/?vx=1&action=products.checkout' ) ); ?>';

			let walletToggleInjected = false;
			let isProcessing = false;

			console.log('Wallet: Script loaded, balance:', WALLET_BALANCE);

			function resetWalletUI() {
				const submitBtn = document.querySelector('.ts-checkout a.ts-btn.ts-btn-2.form-btn, .ts-checkout .form-btn, a.ts-btn-2.form-btn');
				if (submitBtn && submitBtn.dataset.originalText) {
					submitBtn.textContent = submitBtn.dataset.originalText;
					submitBtn.style.pointerEvents = '';
					submitBtn.style.opacity = '';
				}
				const checkbox = document.getElementById('use-wallet-balance');
				if (checkbox) {
					checkbox.checked = false;
					const toggle = checkbox.closest('.wallet-checkout-toggle');
					if (toggle) toggle.classList.remove('active');
				}
				isProcessing = false;
			}

			// Store captured item data from fetch responses
			let capturedItemData = null;

			// Intercept fetch to capture item data when checkout loads
			const originalFetch = window.fetch;
			window.fetch = async function(url, options) {
				const response = await originalFetch.apply(this, arguments);
				const urlStr = typeof url === 'string' ? url : url.toString();

				// Capture direct cart data
				if (urlStr.includes('products.get_direct_cart') || urlStr.includes('get_direct_cart')) {
					try {
						const cloned = response.clone();
						const data = await cloned.json();
						console.log('Wallet: Captured direct cart response:', data);
						if (data.success && data.item) {
							capturedItemData = data.item;
							console.log('Wallet: Stored item data:', capturedItemData);
						}
					} catch (e) {
						console.log('Wallet: Could not capture cart data:', e);
					}
				}

				return response;
			};
			console.log('Wallet: Fetch interceptor installed for cart capture');

			// Get item config - use captured data or try to extract from page
			function getItemConfig() {
				// First check if we captured data from fetch
				if (capturedItemData) {
					console.log('Wallet: Using captured item data');
					if (capturedItemData._config) {
						return [capturedItemData._config];
					}
					if (capturedItemData.post_id || capturedItemData.product_type) {
						return [capturedItemData];
					}
				}

				// Look for ALL script tags and dump their content to find item data
				const allScripts = document.querySelectorAll('script');
				console.log('Wallet: Checking', allScripts.length, 'script tags');

				for (const script of allScripts) {
					const content = script.textContent || '';
					// Look for scripts that contain item/cart data
					if (content.includes('post_id') && content.includes('product_type')) {
						console.log('Wallet: Found script with item data, length:', content.length);
						console.log('Wallet: Script type:', script.type);
						console.log('Wallet: Script class:', script.className);
						console.log('Wallet: Script content preview:', content.substring(0, 500));

						try {
							// Try to parse as JSON
							const data = JSON.parse(content);
							console.log('Wallet: Parsed script JSON:', data);
							if (data.item) return [data.item._config || data.item];
							if (data.items) return data.items.map(i => i._config || i);
						} catch (e) {
							console.log('Wallet: Script is not pure JSON, trying to extract...');
							// Try to extract JSON from script content
							const jsonMatch = content.match(/\{[\s\S]*"post_id"[\s\S]*\}/);
							if (jsonMatch) {
								console.log('Wallet: Found JSON match, length:', jsonMatch[0].length);
								try {
									const data = JSON.parse(jsonMatch[0]);
									console.log('Wallet: Extracted JSON from script:', data);
									return [data];
								} catch (e2) {
									console.log('Wallet: Could not parse extracted JSON:', e2.message);
								}
							}
						}
					}
				}

				// Try to find window variables that might contain cart data
				const windowKeys = ['Voxel', 'voxel', 'checkout', 'cart', 'VoxelCart', 'VoxelCheckout'];
				for (const key of windowKeys) {
					if (window[key]) {
						console.log('Wallet: Found window.' + key + ':', window[key]);
						const obj = window[key];
						if (obj.item) return [obj.item._config || obj.item];
						if (obj.items) return obj.items.map(i => i._config || i);
						if (obj.cart && obj.cart.item) return [obj.cart.item._config || obj.cart.item];
					}
				}

				// Debug: log all window properties that look cart-related
				const cartRelated = Object.keys(window).filter(k =>
					k.toLowerCase().includes('cart') ||
					k.toLowerCase().includes('checkout') ||
					k.toLowerCase().includes('voxel') ||
					k.toLowerCase().includes('item')
				);
				console.log('Wallet: Cart-related window keys:', cartRelated);

				// Check VX_Cart_Summary - this is the Vue component for the cart
				if (window.VX_Cart_Summary) {
					console.log('Wallet: VX_Cart_Summary:', window.VX_Cart_Summary);
					console.log('Wallet: VX_Cart_Summary keys:', Object.keys(window.VX_Cart_Summary));

					// Try to access the internal state via the underscore property
					const cs = window.VX_Cart_Summary;
					if (cs._) {
						console.log('Wallet: VX_Cart_Summary._ keys:', Object.keys(cs._));
					}

					// Try to call getSummary or access items directly
					if (cs.items) {
						console.log('Wallet: Found items in VX_Cart_Summary:', cs.items);

						// cs.items is an object with keys like "kq8td132", each containing item data
						// We need to find or reconstruct the config needed for checkout
						const itemConfigs = [];
						for (const itemKey in cs.items) {
							const item = cs.items[itemKey];
							console.log('Wallet: Item', itemKey, 'full keys:', Object.keys(item));

							// Log ALL properties to find the config
							for (const prop of Object.keys(item)) {
								const val = item[prop];
								if (typeof val === 'object' && val !== null) {
									console.log('Wallet: Item.' + prop + ' (object):', val);
								} else {
									console.log('Wallet: Item.' + prop + ':', val);
								}
							}

							// Check for _config or config
							if (item._config) {
								console.log('Wallet: Found _config:', item._config);
								itemConfigs.push(item._config);
							} else if (item.config) {
								console.log('Wallet: Found config:', item.config);
								itemConfigs.push(item.config);
							} else if (item.value && item.value.product) {
								// The item.value contains the config format Voxel needs!
								// It has: product.post_id, product.field_key, booking dates, addons, etc.
								console.log('Wallet: Found value (config) in item:', item.value);
								itemConfigs.push(item.value);
							} else {
								console.log('Wallet: No config found in item');
							}
						}

						if (itemConfigs.length > 0) {
							console.log('Wallet: Extracted item configs:', itemConfigs);
							return itemConfigs;
						}

						// If no configs found, return null so we try other methods
						console.log('Wallet: Could not extract configs from items');
					}

					if (cs.item) {
						console.log('Wallet: Found single item in VX_Cart_Summary:', cs.item);
						console.log('Wallet: Item keys:', Object.keys(cs.item));
						if (cs.item._config) {
							return [cs.item._config];
						}
						return [cs.item.config || cs.item];
					}

					// Try to get from the proxy's internal data
					try {
						// Vue 3 proxies have a target we can access
						const allKeys = [];
						for (const key in cs) {
							allKeys.push(key);
							if (key !== '_' && !key.startsWith('$') && typeof cs[key] !== 'function') {
								console.log('Wallet: VX_Cart_Summary.' + key + ':', cs[key]);
							}
						}
						console.log('Wallet: All VX_Cart_Summary enumerable keys:', allKeys);
					} catch (e) {
						console.log('Wallet: Error enumerating VX_Cart_Summary:', e);
					}
				}

				// Check render_voxel_cart
				if (window.render_voxel_cart) {
					console.log('Wallet: render_voxel_cart:', typeof window.render_voxel_cart);
				}

				// Check render_voxel_checkout
				if (window.render_voxel_checkout) {
					console.log('Wallet: render_voxel_checkout:', typeof window.render_voxel_checkout);
				}

				// Check Voxel_Config
				if (window.Voxel_Config) {
					console.log('Wallet: Voxel_Config:', window.Voxel_Config);
					console.log('Wallet: Voxel_Config keys:', Object.keys(window.Voxel_Config));
				}

				// Try vxconfig script tag
				const configScript = document.querySelector('script.vxconfig');
				if (configScript) {
					try {
						const config = JSON.parse(configScript.textContent);
						console.log('Wallet: vxconfig contents:', Object.keys(config));
						if (config.item) {
							return [config.item._config || config.item];
						}
					} catch (e) {}
				}

				return null;
			}

			// Process wallet payment by calling Voxel's checkout with wallet flag
			async function processWalletPayment() {
				// Get item config from Vue/page
				let items = getItemConfig();
				console.log('Wallet: Items from page:', items);

				if (!items || items.length === 0) {
					// As a fallback, look for script tags with item config
					const scripts = document.querySelectorAll('script[type="application/json"]');
					for (const script of scripts) {
						try {
							const data = JSON.parse(script.textContent);
							if (data.item || data.items) {
								items = data.items || [data.item];
								console.log('Wallet: Found items in script tag:', items);
								break;
							}
						} catch (e) {}
					}
				}

				if (!items || items.length === 0) {
					throw new Error('<?php echo esc_js( __( 'Could not get cart items. Please try again.', 'voxel-payment-gateways' ) ); ?>');
				}

				// Build the same payload Voxel uses
				const payload = {
					source: 'direct_cart',
					items: JSON.stringify(items),
					use_wallet: true
				};

				console.log('Wallet: Sending checkout request with payload:', payload);

				const response = await fetch(CHECKOUT_URL, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
					},
					credentials: 'same-origin',
					body: JSON.stringify(payload)
				});

				const data = await response.json();
				console.log('Wallet: Checkout response:', data);

				if (data.success && data.redirect_url) {
					window.location.href = data.redirect_url;
				} else {
					throw new Error(data.message || '<?php echo esc_js( __( 'Payment failed', 'voxel-payment-gateways' ) ); ?>');
				}
			}

			function injectWalletToggle() {
				// Don't inject multiple times
				if (walletToggleInjected && document.querySelector('.wallet-checkout-toggle')) {
					return true;
				}

				// Find the checkout submit button - Voxel uses a.ts-btn.ts-btn-2.form-btn
				const submitBtn = document.querySelector('.ts-checkout a.ts-btn.ts-btn-2.form-btn, .ts-checkout .form-btn, a.ts-btn-2.form-btn');
				if (!submitBtn) {
					return false;
				}

				// Find the checkout container
				const form = submitBtn.closest('.ts-checkout') || submitBtn.closest('.checkout-section');
				if (!form) {
					return false;
				}

				// Check if toggle already exists
				if (form.querySelector('.wallet-checkout-toggle')) {
					return true;
				}

				// Create wallet toggle container
				const walletToggle = document.createElement('div');
				walletToggle.className = 'wallet-checkout-toggle';
				walletToggle.innerHTML = `
					<div class="wallet-checkout-option">
						<label class="wallet-checkout-label">
							<input type="checkbox" id="use-wallet-balance" ${WALLET_BALANCE <= 0 ? 'disabled' : ''}>
							<span class="wallet-checkbox-custom"></span>
							<span class="wallet-label-text">
								<?php _e( 'Pay with Wallet', 'voxel-payment-gateways' ); ?>
								<span class="wallet-balance">(${WALLET_BALANCE_FORMATTED})</span>
							</span>
						</label>
						${WALLET_BALANCE <= 0 ? '<span class="wallet-insufficient"><?php _e( 'No balance', 'voxel-payment-gateways' ); ?></span>' : ''}
					</div>
				`;

				// Insert before the submit button
				submitBtn.parentNode.insertBefore(walletToggle, submitBtn);
				walletToggleInjected = true;

				// Add change handler
				const checkbox = walletToggle.querySelector('#use-wallet-balance');
				checkbox.addEventListener('change', function() {
					walletToggle.classList.toggle('active', this.checked);

					// Update button text
					if (this.checked) {
						submitBtn.dataset.originalText = submitBtn.textContent;
						submitBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;margin-right:6px;vertical-align:middle;"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg> <?php _e( 'Pay with Wallet', 'voxel-payment-gateways' ); ?>';
					} else if (submitBtn.dataset.originalText) {
						submitBtn.textContent = submitBtn.dataset.originalText;
					}
				});

				// Handle click - if wallet is checked, process wallet payment directly
				submitBtn.addEventListener('click', async function(e) {
					const useWallet = document.getElementById('use-wallet-balance')?.checked;
					console.log('Wallet: Button clicked, useWallet:', useWallet, 'isProcessing:', isProcessing);

					if (!useWallet || isProcessing) {
						console.log('Wallet: Not using wallet or already processing, letting Voxel handle');
						return; // Let normal checkout proceed
					}

					// Prevent Voxel's checkout
					e.preventDefault();
					e.stopPropagation();
					e.stopImmediatePropagation();

					const orderTotal = getOrderTotal();
					console.log('Wallet: Order total:', orderTotal, 'Balance:', WALLET_BALANCE);

					if (orderTotal > WALLET_BALANCE) {
						alert('<?php echo esc_js( __( 'Insufficient wallet balance for this order', 'voxel-payment-gateways' ) ); ?>');
						return false;
					}

					// Show processing state
					isProcessing = true;
					submitBtn.dataset.originalText = submitBtn.dataset.originalText || submitBtn.textContent;
					submitBtn.innerHTML = '<?php _e( 'Processing...', 'voxel-payment-gateways' ); ?>';
					submitBtn.style.pointerEvents = 'none';
					submitBtn.style.opacity = '0.7';

					try {
						await processWalletPayment();
					} catch (error) {
						console.error('Wallet: Payment error:', error);
						alert(error.message || '<?php echo esc_js( __( 'Payment failed', 'voxel-payment-gateways' ) ); ?>');
						resetWalletUI();
					}

					return false;
				}, true);

				return true;
			}

			function getOrderTotal() {
				// Try to find order total from Voxel checkout elements
				// The structure is: .ts-total .ts-item-price p containing "$20"
				const totalEl = document.querySelector('.ts-total .ts-item-price p, .ts-total .ts-item-price, .ts-cost-calculator .ts-total .ts-item-price p');
				if (totalEl) {
					const text = totalEl.textContent.replace(/[^0-9.,]/g, '').replace(',', '.');
					return parseFloat(text) || 0;
				}

				// Try subtotal as fallback
				const subtotalEl = document.querySelector('.ts-cost-calculator li:last-child .ts-item-price p, .checkout-total, .order-total');
				if (subtotalEl) {
					const text = subtotalEl.textContent.replace(/[^0-9.,]/g, '').replace(',', '.');
					return parseFloat(text) || 0;
				}

				// Last resort - look for any price-like element in the checkout
				const priceEl = document.querySelector('.ts-checkout .ts-item-price p');
				if (priceEl) {
					const text = priceEl.textContent.replace(/[^0-9.,]/g, '').replace(',', '.');
					return parseFloat(text) || 0;
				}

				return 0;
			}

			// Poll for checkout form
			let attempts = 0;
			const maxAttempts = 200;
			const pollInterval = setInterval(function() {
				attempts++;
				if (injectWalletToggle() || attempts >= maxAttempts) {
					clearInterval(pollInterval);
				}
			}, 250);

			// Watch for SPA navigation
			const observer = new MutationObserver(function(mutations) {
				if (!document.querySelector('.wallet-checkout-toggle')) {
					walletToggleInjected = false;
				}
				injectWalletToggle();
			});

			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
		})();
		</script>
		<style>
			.wallet-checkout-toggle {
				margin: 16px 0;
				padding: 16px;
				background: linear-gradient(135deg, rgba(76, 175, 80, 0.1) 0%, rgba(76, 175, 80, 0.05) 100%);
				border: 2px solid rgba(76, 175, 80, 0.3);
				border-radius: 12px;
				transition: all 0.2s ease;
			}
			.wallet-checkout-toggle.active {
				background: linear-gradient(135deg, rgba(76, 175, 80, 0.2) 0%, rgba(76, 175, 80, 0.1) 100%);
				border-color: #4caf50;
			}
			.wallet-checkout-option {
				display: flex;
				align-items: center;
				justify-content: space-between;
				gap: 12px;
			}
			.wallet-checkout-label {
				display: flex;
				align-items: center;
				gap: 12px;
				cursor: pointer;
				font-weight: 500;
				flex: 1;
			}
			.wallet-checkout-label input[type="checkbox"] {
				display: none;
			}
			.wallet-checkbox-custom {
				width: 22px;
				height: 22px;
				border: 2px solid #4caf50;
				border-radius: 6px;
				display: flex;
				align-items: center;
				justify-content: center;
				transition: all 0.2s ease;
				flex-shrink: 0;
			}
			.wallet-checkout-label input:checked + .wallet-checkbox-custom {
				background: #4caf50;
			}
			.wallet-checkout-label input:checked + .wallet-checkbox-custom::after {
				content: '';
				width: 6px;
				height: 10px;
				border: solid #fff;
				border-width: 0 2px 2px 0;
				transform: rotate(45deg);
				margin-bottom: 2px;
			}
			.wallet-checkout-label input:disabled + .wallet-checkbox-custom {
				border-color: #ccc;
				background: #f5f5f5;
			}
			.wallet-label-text {
				font-size: 15px;
				color: #333;
			}
			.wallet-balance {
				color: #4caf50;
				font-weight: 600;
			}
			.wallet-insufficient {
				font-size: 12px;
				color: #f44336;
				background: rgba(244, 67, 54, 0.1);
				padding: 4px 8px;
				border-radius: 4px;
			}
		</style>
		<?php
	}
}
