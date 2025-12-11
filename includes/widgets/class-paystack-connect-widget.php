<?php

namespace VoxelPayPal\Widgets;

use VoxelPayPal\Paystack_Connect_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Paystack Connect Widget for Elementor
 * Allows vendors to connect their bank accounts for marketplace payouts
 */
class Paystack_Connect_Widget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'paystack-connect';
	}

	public function get_title(): string {
		return __( 'Paystack Connect', 'voxel-payment-gateways' );
	}

	public function get_icon(): string {
		return 'eicon-bank-transfer';
	}

	public function get_categories(): array {
		return [ 'voxel' ];
	}

	public function get_keywords(): array {
		return [ 'paystack', 'connect', 'payment', 'vendor', 'marketplace', 'bank' ];
	}

	protected function register_controls(): void {
		$this->start_controls_section(
			'content_section',
			[
				'label' => __( 'Content', 'voxel-payment-gateways' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'title',
			[
				'label' => __( 'Title', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Paystack Payout Account', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'description',
			[
				'label' => __( 'Description', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXTAREA,
				'default' => __( 'Connect your bank account to receive payments from your sales.', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'connect_button_text',
			[
				'label' => __( 'Connect Button Text', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Connect Bank Account', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'disconnect_button_text',
			[
				'label' => __( 'Disconnect Button Text', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Disconnect', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'default_country',
			[
				'label' => __( 'Default Country', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::SELECT,
				'default' => 'nigeria',
				'options' => [
					'nigeria' => __( 'Nigeria', 'voxel-payment-gateways' ),
					'ghana' => __( 'Ghana', 'voxel-payment-gateways' ),
					'south-africa' => __( 'South Africa', 'voxel-payment-gateways' ),
					'kenya' => __( 'Kenya', 'voxel-payment-gateways' ),
				],
			]
		);

		$this->add_control(
			'show_earnings',
			[
				'label' => __( 'Show Earnings Summary', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __( 'Yes', 'voxel-payment-gateways' ),
				'label_off' => __( 'No', 'voxel-payment-gateways' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->end_controls_section();

		// Style Section
		$this->start_controls_section(
			'style_section',
			[
				'label' => __( 'Style', 'voxel-payment-gateways' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'title_color',
			[
				'label' => __( 'Title Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#333333',
				'selectors' => [
					'{{WRAPPER}} .ps-connect-title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_background',
			[
				'label' => __( 'Button Background', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#58c0f2',
				'selectors' => [
					'{{WRAPPER}} .ps-connect-button' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_color',
			[
				'label' => __( 'Button Text Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .ps-connect-button' => 'color: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$user_id = get_current_user_id();

		// Check if user is logged in
		if ( ! $user_id ) {
			echo '<p>' . esc_html__( 'Please log in to manage your payout settings.', 'voxel-payment-gateways' ) . '</p>';
			return;
		}

		// Check if marketplace is enabled
		if ( ! Paystack_Connect_Client::is_marketplace_enabled() ) {
			return; // Silently return if marketplace is not enabled
		}

		// Get earnings if enabled
		$earnings_data = null;
		if ( $settings['show_earnings'] === 'yes' ) {
			$earnings_data = $this->get_vendor_earnings( $user_id );
		}

		$is_connected = Paystack_Connect_Client::is_vendor_connected( $user_id );
		$bank_info = null;

		if ( $is_connected ) {
			$bank_info = Paystack_Connect_Client::get_vendor_bank_info( $user_id );
		}

		$default_country = $settings['default_country'] ?? 'nigeria';
		$connect_nonce = wp_create_nonce( 'paystack_connect_' . $user_id );
		$disconnect_nonce = wp_create_nonce( 'paystack_disconnect_' . $user_id );

		$widget_id = 'ps-connect-' . $this->get_id();
		?>
		<div class="ps-connect-widget" id="<?php echo esc_attr( $widget_id ); ?>">
			<?php if ( ! empty( $settings['title'] ) ) : ?>
				<h3 class="ps-connect-title"><?php echo esc_html( $settings['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( $earnings_data && $is_connected ) : ?>
				<!-- Earnings Summary -->
				<div class="ps-earnings-summary">
					<div class="ps-earnings-grid">
						<div class="ps-earnings-item">
							<span class="ps-earnings-label"><?php _e( 'Total Earnings', 'voxel-payment-gateways' ); ?></span>
							<span class="ps-earnings-value"><?php echo esc_html( $earnings_data['currency_symbol'] . number_format( $earnings_data['total'], 2 ) ); ?></span>
						</div>
						<div class="ps-earnings-item">
							<span class="ps-earnings-label"><?php _e( 'Platform Fees', 'voxel-payment-gateways' ); ?></span>
							<span class="ps-earnings-value"><?php echo esc_html( $earnings_data['currency_symbol'] . number_format( $earnings_data['total_fees'], 2 ) ); ?></span>
						</div>
						<div class="ps-earnings-item">
							<span class="ps-earnings-label"><?php _e( 'Orders', 'voxel-payment-gateways' ); ?></span>
							<span class="ps-earnings-value"><?php echo intval( $earnings_data['order_count'] ); ?></span>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $is_connected && $bank_info ) : ?>
				<!-- Connected State -->
				<div class="ps-connect-status ps-connected">
					<div class="ps-status-icon">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
							<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
						</svg>
					</div>
					<div class="ps-status-info">
						<span class="ps-status-label"><?php _e( 'Connected', 'voxel-payment-gateways' ); ?></span>
						<?php if ( ! empty( $bank_info['account_name'] ) ) : ?>
							<span class="ps-account-name"><?php echo esc_html( $bank_info['account_name'] ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $bank_info['account_number'] ) ) : ?>
							<span class="ps-account-number">****<?php echo esc_html( substr( $bank_info['account_number'], -4 ) ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<button type="button" class="ps-connect-button ps-disconnect" onclick="paystackDisconnect_<?php echo esc_attr( $this->get_id() ); ?>()" data-confirm="<?php esc_attr_e( 'Are you sure you want to disconnect your bank account?', 'voxel-payment-gateways' ); ?>">
					<?php echo esc_html( $settings['disconnect_button_text'] ); ?>
				</button>

			<?php else : ?>
				<!-- Not Connected State -->
				<?php if ( ! empty( $settings['description'] ) ) : ?>
					<p class="ps-connect-description"><?php echo esc_html( $settings['description'] ); ?></p>
				<?php endif; ?>

				<form class="ps-connect-form" id="ps-form-<?php echo esc_attr( $this->get_id() ); ?>">
					<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $connect_nonce ); ?>">

					<div class="ps-form-group">
						<label for="ps-country-<?php echo esc_attr( $this->get_id() ); ?>"><?php _e( 'Country', 'voxel-payment-gateways' ); ?></label>
						<select id="ps-country-<?php echo esc_attr( $this->get_id() ); ?>" name="country" required>
							<option value="nigeria" <?php selected( $default_country, 'nigeria' ); ?>><?php _e( 'Nigeria', 'voxel-payment-gateways' ); ?></option>
							<option value="ghana" <?php selected( $default_country, 'ghana' ); ?>><?php _e( 'Ghana', 'voxel-payment-gateways' ); ?></option>
							<option value="south-africa" <?php selected( $default_country, 'south-africa' ); ?>><?php _e( 'South Africa', 'voxel-payment-gateways' ); ?></option>
							<option value="kenya" <?php selected( $default_country, 'kenya' ); ?>><?php _e( 'Kenya', 'voxel-payment-gateways' ); ?></option>
						</select>
					</div>

					<div class="ps-form-group">
						<label for="ps-bank-<?php echo esc_attr( $this->get_id() ); ?>"><?php _e( 'Bank', 'voxel-payment-gateways' ); ?></label>
						<select id="ps-bank-<?php echo esc_attr( $this->get_id() ); ?>" name="bank_code" required disabled>
							<option value=""><?php _e( 'Loading banks...', 'voxel-payment-gateways' ); ?></option>
						</select>
					</div>

					<div class="ps-form-group">
						<label for="ps-account-<?php echo esc_attr( $this->get_id() ); ?>"><?php _e( 'Account Number', 'voxel-payment-gateways' ); ?></label>
						<input type="text" id="ps-account-<?php echo esc_attr( $this->get_id() ); ?>" name="account_number" required pattern="[0-9]{10,}" placeholder="<?php esc_attr_e( 'Enter your account number', 'voxel-payment-gateways' ); ?>">
					</div>

					<div class="ps-form-group ps-account-preview" id="ps-preview-<?php echo esc_attr( $this->get_id() ); ?>" style="display: none;">
						<label><?php _e( 'Account Name', 'voxel-payment-gateways' ); ?></label>
						<div class="ps-account-name-display"></div>
					</div>

					<div class="ps-form-group">
						<label for="ps-business-<?php echo esc_attr( $this->get_id() ); ?>"><?php _e( 'Business Name (Optional)', 'voxel-payment-gateways' ); ?></label>
						<input type="text" id="ps-business-<?php echo esc_attr( $this->get_id() ); ?>" name="business_name" placeholder="<?php esc_attr_e( 'Your business or display name', 'voxel-payment-gateways' ); ?>">
					</div>

					<div class="ps-message" id="ps-message-<?php echo esc_attr( $this->get_id() ); ?>" style="display: none;"></div>

					<button type="submit" class="ps-connect-button" id="ps-submit-<?php echo esc_attr( $this->get_id() ); ?>">
						<?php echo esc_html( $settings['connect_button_text'] ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>

		<style>
			.ps-connect-widget {
				padding: 20px;
				border: 1px solid #e0e0e0;
				border-radius: 8px;
				background: #fff;
			}
			.ps-connect-title {
				margin: 0 0 10px 0;
				font-size: 18px;
				font-weight: 600;
			}
			.ps-connect-description {
				margin: 0 0 20px 0;
				color: #666;
			}
			.ps-earnings-summary {
				margin-bottom: 20px;
				padding: 16px;
				background: #f0f9ff;
				border-radius: 8px;
				border: 1px solid #bae6fd;
			}
			.ps-earnings-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
				gap: 16px;
			}
			.ps-earnings-item {
				display: flex;
				flex-direction: column;
				gap: 4px;
			}
			.ps-earnings-label {
				font-size: 12px;
				color: #64748b;
				font-weight: 500;
				text-transform: uppercase;
			}
			.ps-earnings-value {
				font-size: 20px;
				font-weight: 700;
				color: #0369a1;
			}
			.ps-connect-status {
				display: flex;
				align-items: center;
				padding: 15px;
				margin-bottom: 15px;
				border-radius: 6px;
				background: #e8f5e9;
			}
			.ps-connect-status.ps-connected .ps-status-icon {
				color: #4caf50;
				margin-right: 12px;
				flex-shrink: 0;
			}
			.ps-status-info {
				display: flex;
				flex-direction: column;
			}
			.ps-status-label {
				font-weight: 600;
				color: #2e7d32;
			}
			.ps-account-name, .ps-account-number {
				font-size: 13px;
				color: #666;
				margin-top: 2px;
			}
			.ps-connect-form {
				display: flex;
				flex-direction: column;
				gap: 15px;
			}
			.ps-form-group {
				display: flex;
				flex-direction: column;
				gap: 5px;
			}
			.ps-form-group label {
				font-weight: 500;
				font-size: 14px;
				color: #333;
			}
			.ps-form-group input,
			.ps-form-group select {
				padding: 10px 12px;
				border: 1px solid #ddd;
				border-radius: 6px;
				font-size: 14px;
				transition: border-color 0.2s;
			}
			.ps-form-group input:focus,
			.ps-form-group select:focus {
				outline: none;
				border-color: #58c0f2;
			}
			.ps-form-group input:disabled,
			.ps-form-group select:disabled {
				background: #f5f5f5;
				cursor: not-allowed;
			}
			.ps-account-preview {
				background: #e3f2fd;
				padding: 12px;
				border-radius: 6px;
			}
			.ps-account-name-display {
				font-weight: 600;
				color: #1976d2;
			}
			.ps-message {
				padding: 12px;
				border-radius: 6px;
				font-size: 14px;
			}
			.ps-message.ps-error {
				background: #ffebee;
				color: #c62828;
			}
			.ps-message.ps-success {
				background: #e8f5e9;
				color: #2e7d32;
			}
			.ps-message.ps-loading {
				background: #e3f2fd;
				color: #1976d2;
			}
			.ps-connect-button {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				padding: 12px 24px;
				border: none;
				border-radius: 6px;
				font-size: 14px;
				font-weight: 600;
				text-decoration: none;
				cursor: pointer;
				transition: opacity 0.2s;
			}
			.ps-connect-button:hover {
				opacity: 0.9;
			}
			.ps-connect-button:disabled {
				opacity: 0.6;
				cursor: not-allowed;
			}
			.ps-connect-button.ps-disconnect {
				background: #f5f5f5 !important;
				color: #666 !important;
			}
			.ps-connect-button.ps-disconnect:hover {
				background: #e0e0e0 !important;
			}
		</style>

		<script>
		(function() {
			const widgetId = '<?php echo esc_js( $this->get_id() ); ?>';
			const ajaxUrl = '<?php echo esc_js( home_url( '/?vx=1' ) ); ?>';
			const isConnected = <?php echo $is_connected ? 'true' : 'false'; ?>;

			<?php if ( ! $is_connected ) : ?>
			// Elements
			const form = document.getElementById('ps-form-' + widgetId);
			const countrySelect = document.getElementById('ps-country-' + widgetId);
			const bankSelect = document.getElementById('ps-bank-' + widgetId);
			const accountInput = document.getElementById('ps-account-' + widgetId);
			const previewDiv = document.getElementById('ps-preview-' + widgetId);
			const messageDiv = document.getElementById('ps-message-' + widgetId);
			const submitBtn = document.getElementById('ps-submit-' + widgetId);

			let resolveTimeout = null;
			let resolvedAccountName = null;

			// Load banks on country change
			async function loadBanks(country) {
				bankSelect.disabled = true;
				bankSelect.innerHTML = '<option value=""><?php echo esc_js( __( 'Loading banks...', 'voxel-payment-gateways' ) ); ?></option>';

				try {
					const response = await fetch(ajaxUrl + '&action=paystack.connect.banks&country=' + encodeURIComponent(country));
					const data = await response.json();

					if (data.success && data.banks) {
						bankSelect.innerHTML = '<option value=""><?php echo esc_js( __( 'Select your bank', 'voxel-payment-gateways' ) ); ?></option>';
						data.banks.forEach(bank => {
							const option = document.createElement('option');
							option.value = bank.code;
							option.textContent = bank.name;
							bankSelect.appendChild(option);
						});
						bankSelect.disabled = false;
					} else {
						showMessage('<?php echo esc_js( __( 'Failed to load banks. Please try again.', 'voxel-payment-gateways' ) ); ?>', 'error');
					}
				} catch (error) {
					showMessage('<?php echo esc_js( __( 'Error loading banks. Please try again.', 'voxel-payment-gateways' ) ); ?>', 'error');
				}
			}

			// Resolve account number
			async function resolveAccount() {
				const accountNumber = accountInput.value.trim();
				const bankCode = bankSelect.value;

				if (accountNumber.length < 10 || !bankCode) {
					previewDiv.style.display = 'none';
					resolvedAccountName = null;
					return;
				}

				previewDiv.style.display = 'block';
				previewDiv.querySelector('.ps-account-name-display').textContent = '<?php echo esc_js( __( 'Verifying...', 'voxel-payment-gateways' ) ); ?>';

				try {
					const formData = new FormData();
					formData.append('account_number', accountNumber);
					formData.append('bank_code', bankCode);

					const response = await fetch(ajaxUrl + '&action=paystack.connect.resolve', {
						method: 'POST',
						body: formData
					});
					const data = await response.json();

					if (data.success && data.account_name) {
						previewDiv.querySelector('.ps-account-name-display').textContent = data.account_name;
						resolvedAccountName = data.account_name;
					} else {
						previewDiv.querySelector('.ps-account-name-display').textContent = '<?php echo esc_js( __( 'Could not verify account', 'voxel-payment-gateways' ) ); ?>';
						resolvedAccountName = null;
					}
				} catch (error) {
					previewDiv.querySelector('.ps-account-name-display').textContent = '<?php echo esc_js( __( 'Verification failed', 'voxel-payment-gateways' ) ); ?>';
					resolvedAccountName = null;
				}
			}

			// Show message
			function showMessage(text, type) {
				messageDiv.textContent = text;
				messageDiv.className = 'ps-message ps-' + type;
				messageDiv.style.display = 'block';
			}

			// Hide message
			function hideMessage() {
				messageDiv.style.display = 'none';
			}

			// Event listeners
			countrySelect.addEventListener('change', function() {
				loadBanks(this.value);
				previewDiv.style.display = 'none';
				resolvedAccountName = null;
			});

			bankSelect.addEventListener('change', function() {
				if (accountInput.value.length >= 10) {
					resolveAccount();
				}
			});

			accountInput.addEventListener('input', function() {
				clearTimeout(resolveTimeout);
				if (this.value.length >= 10 && bankSelect.value) {
					resolveTimeout = setTimeout(resolveAccount, 500);
				} else {
					previewDiv.style.display = 'none';
					resolvedAccountName = null;
				}
			});

			// Form submit
			form.addEventListener('submit', async function(e) {
				e.preventDefault();
				hideMessage();

				if (!resolvedAccountName) {
					showMessage('<?php echo esc_js( __( 'Please wait for account verification to complete.', 'voxel-payment-gateways' ) ); ?>', 'error');
					return;
				}

				submitBtn.disabled = true;
				submitBtn.textContent = '<?php echo esc_js( __( 'Connecting...', 'voxel-payment-gateways' ) ); ?>';

				try {
					const formData = new FormData(form);

					const response = await fetch(ajaxUrl + '&action=paystack.connect.submit', {
						method: 'POST',
						body: formData
					});
					const data = await response.json();

					if (data.success) {
						showMessage(data.message || '<?php echo esc_js( __( 'Bank account connected successfully!', 'voxel-payment-gateways' ) ); ?>', 'success');
						setTimeout(() => location.reload(), 1500);
					} else {
						showMessage(data.message || '<?php echo esc_js( __( 'Failed to connect bank account.', 'voxel-payment-gateways' ) ); ?>', 'error');
						submitBtn.disabled = false;
						submitBtn.textContent = '<?php echo esc_js( $settings['connect_button_text'] ); ?>';
					}
				} catch (error) {
					showMessage('<?php echo esc_js( __( 'An error occurred. Please try again.', 'voxel-payment-gateways' ) ); ?>', 'error');
					submitBtn.disabled = false;
					submitBtn.textContent = '<?php echo esc_js( $settings['connect_button_text'] ); ?>';
				}
			});

			// Initial load
			loadBanks(countrySelect.value);
			<?php endif; ?>

			// Disconnect function
			window['paystackDisconnect_' + widgetId] = async function() {
				if (!confirm(document.querySelector('#<?php echo esc_attr( $widget_id ); ?> .ps-disconnect').dataset.confirm)) {
					return;
				}

				try {
					const formData = new FormData();
					formData.append('_wpnonce', '<?php echo esc_js( $disconnect_nonce ); ?>');

					const response = await fetch(ajaxUrl + '&action=paystack.connect.disconnect', {
						method: 'POST',
						body: formData
					});
					const data = await response.json();

					if (data.success) {
						location.reload();
					} else {
						alert(data.message || '<?php echo esc_js( __( 'Failed to disconnect.', 'voxel-payment-gateways' ) ); ?>');
					}
				} catch (error) {
					alert('<?php echo esc_js( __( 'An error occurred.', 'voxel-payment-gateways' ) ); ?>');
				}
			};
		})();
		</script>
		<?php
	}

	/**
	 * Get vendor earnings summary for Paystack orders
	 */
	private function get_vendor_earnings( int $user_id ): ?array {
		// Get currency from Paystack settings
		$currency = strtoupper( \Voxel\get( 'payments.paystack.currency', 'NGN' ) );
		$currency_symbols = [
			'NGN' => '₦',
			'GHS' => 'GH₵',
			'ZAR' => 'R',
			'KES' => 'KSh',
			'USD' => '$',
		];
		$currency_symbol = $currency_symbols[ $currency ] ?? $currency . ' ';

		// Query orders
		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1000,
		] );

		$total_earnings = 0;
		$total_fees = 0;
		$order_count = 0;

		foreach ( $orders as $order ) {
			// Only count Paystack orders
			$payment_method = $order->get_payment_method();
			if ( ! $payment_method ) {
				continue;
			}

			$payment_type = $payment_method->get_type();
			if ( ! in_array( $payment_type, [ 'paystack_payment', 'paystack_subscription' ], true ) ) {
				continue;
			}

			// Check if this order belongs to the vendor
			$items = $order->get_items();
			if ( empty( $items ) ) {
				continue;
			}

			$first_item = reset( $items );
			$post_id = null;

			if ( method_exists( $first_item, 'get_post_id' ) ) {
				$post_id = $first_item->get_post_id();
			}

			if ( ! $post_id ) {
				$post_id = $first_item->get_details( 'post_id' );
			}

			if ( ! $post_id ) {
				continue;
			}

			$post_author = get_post_field( 'post_author', $post_id );

			if ( intval( $post_author ) !== $user_id ) {
				continue;
			}

			// Get earnings from this order
			$vendor_earnings = $order->get_details( 'marketplace.vendor_earnings' );
			$platform_fee = $order->get_details( 'marketplace.platform_fee' );

			if ( $vendor_earnings ) {
				$total_earnings += floatval( $vendor_earnings );
				$total_fees += floatval( $platform_fee ?? 0 );
				$order_count++;
			}
		}

		return [
			'total' => round( $total_earnings, 2 ),
			'total_fees' => round( $total_fees, 2 ),
			'order_count' => $order_count,
			'currency' => $currency,
			'currency_symbol' => $currency_symbol,
		];
	}
}
