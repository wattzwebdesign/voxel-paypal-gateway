<?php

namespace VoxelPayPal\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wallet Widget
 * Elementor widget for wallet balance display, deposits, and transaction history
 */
class Wallet_Widget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'voxel-wallet';
	}

	public function get_title(): string {
		return __( 'Wallet', 'voxel-payment-gateways' );
	}

	public function get_icon(): string {
		return 'eicon-wallet';
	}

	public function get_categories(): array {
		return [ 'voxel' ];
	}

	public function get_keywords(): array {
		return [ 'wallet', 'balance', 'funds', 'payment', 'deposit', 'voxel' ];
	}

	protected function register_controls(): void {
		// Content Section
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
				'default' => __( 'My Wallet', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'description',
			[
				'label' => __( 'Description', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXTAREA,
				'default' => __( 'Add funds to your wallet and use them for future purchases.', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'button_text',
			[
				'label' => __( 'Add Funds Button Text', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Add Funds', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'show_history',
			[
				'label' => __( 'Show Transaction History', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __( 'Yes', 'voxel-payment-gateways' ),
				'label_off' => __( 'No', 'voxel-payment-gateways' ),
				'return_value' => 'yes',
				'default' => 'yes',
			]
		);

		$this->add_control(
			'history_limit',
			[
				'label' => __( 'Transactions to Show', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'default' => 10,
				'min' => 1,
				'max' => 50,
				'condition' => [
					'show_history' => 'yes',
				],
			]
		);

		$this->add_control(
			'show_preset_amounts',
			[
				'label' => __( 'Show Quick Amount Buttons', 'voxel-payment-gateways' ),
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
			'primary_color',
			[
				'label' => __( 'Primary Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#4caf50',
			]
		);

		$this->add_control(
			'text_color',
			[
				'label' => __( 'Text Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#333333',
			]
		);

		$this->end_controls_section();
	}

	protected function render(): void {
		$settings = $this->get_settings_for_display();
		$widget_id = 'wallet-widget-' . $this->get_id();

		// Check if wallet is enabled
		if ( ! \VoxelPayPal\Wallet_Client::is_enabled() ) {
			if ( current_user_can( 'manage_options' ) ) {
				echo '<div class="voxel-wallet-widget"><p>' . __( 'Wallet feature is disabled. Enable it in Voxel Payments settings.', 'voxel-payment-gateways' ) . '</p></div>';
			}
			return;
		}

		// Check if user is logged in
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			echo '<div class="voxel-wallet-widget"><p>' . __( 'Please log in to view your wallet.', 'voxel-payment-gateways' ) . '</p></div>';
			return;
		}

		$balance = \VoxelPayPal\Wallet_Client::get_balance( $user_id );
		$balance_formatted = \VoxelPayPal\Wallet_Client::get_balance_formatted( $user_id );
		$currency = \VoxelPayPal\Wallet_Client::get_site_currency();
		$min_deposit = \VoxelPayPal\Wallet_Client::get_min_deposit();
		$max_deposit = \VoxelPayPal\Wallet_Client::get_max_deposit();
		$preset_amounts = \VoxelPayPal\Wallet_Client::get_preset_amounts();

		$transactions = [];
		if ( $settings['show_history'] === 'yes' ) {
			$transactions = \VoxelPayPal\Wallet_Client::get_transactions( $user_id, [
				'limit' => absint( $settings['history_limit'] ) ?: 10,
			] );
		}

		$primary_color = $settings['primary_color'] ?: '#4caf50';
		$text_color = $settings['text_color'] ?: '#333333';

		// Check for deposit result message
		$deposit_status = isset( $_GET['wallet_deposit'] ) ? sanitize_text_field( $_GET['wallet_deposit'] ) : null;
		$deposit_message = isset( $_GET['wallet_message'] ) ? sanitize_text_field( urldecode( $_GET['wallet_message'] ) ) : null;

		?>
		<div class="voxel-wallet-widget" id="<?php echo esc_attr( $widget_id ); ?>">
			<style>
				#<?php echo esc_attr( $widget_id ); ?> {
					font-family: inherit;
					color: <?php echo esc_attr( $text_color ); ?>;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-header {
					margin-bottom: 24px;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-title {
					font-size: 24px;
					font-weight: 600;
					margin: 0 0 8px 0;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-description {
					margin: 0;
					opacity: 0.7;
					font-size: 14px;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-balance-card {
					background: linear-gradient(135deg, <?php echo esc_attr( $primary_color ); ?> 0%, <?php echo esc_attr( $this->adjust_brightness( $primary_color, -20 ) ); ?> 100%);
					color: #fff;
					padding: 24px;
					border-radius: 12px;
					margin-bottom: 24px;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-balance-label {
					font-size: 14px;
					opacity: 0.9;
					margin-bottom: 4px;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-balance-amount {
					font-size: 36px;
					font-weight: 700;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-add-funds {
					background: #fff;
					border: 1px solid #e0e0e0;
					border-radius: 12px;
					padding: 24px;
					margin-bottom: 24px;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-add-funds h3 {
					font-size: 18px;
					font-weight: 600;
					margin: 0 0 16px 0;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-preset-amounts {
					display: flex;
					flex-wrap: wrap;
					gap: 8px;
					margin-bottom: 16px;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-preset-btn {
					padding: 8px 16px;
					border: 2px solid <?php echo esc_attr( $primary_color ); ?>;
					background: transparent;
					color: <?php echo esc_attr( $primary_color ); ?>;
					border-radius: 6px;
					cursor: pointer;
					font-weight: 500;
					font-size: 14px;
					transition: all 0.2s ease;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-preset-btn:hover,
				#<?php echo esc_attr( $widget_id ); ?> .wallet-preset-btn.active {
					background: <?php echo esc_attr( $primary_color ); ?>;
					color: #fff;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-amount-input-group {
					display: flex;
					gap: 12px;
					align-items: stretch;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-amount-wrapper {
					flex: 1;
					position: relative;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-currency-prefix {
					position: absolute;
					left: 12px;
					top: 50%;
					transform: translateY(-50%);
					font-weight: 600;
					color: #666;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-amount-input {
					width: 100%;
					padding: 12px 12px 12px 48px;
					border: 1px solid #ddd;
					border-radius: 6px;
					font-size: 16px;
					box-sizing: border-box;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-amount-input:focus {
					outline: none;
					border-color: <?php echo esc_attr( $primary_color ); ?>;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-submit-btn {
					padding: 12px 24px;
					background: <?php echo esc_attr( $primary_color ); ?>;
					color: #fff;
					border: none;
					border-radius: 6px;
					cursor: pointer;
					font-weight: 600;
					font-size: 14px;
					transition: opacity 0.2s ease;
					white-space: nowrap;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-submit-btn:hover {
					opacity: 0.9;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-submit-btn:disabled {
					opacity: 0.5;
					cursor: not-allowed;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-message {
					padding: 12px;
					border-radius: 6px;
					margin-bottom: 16px;
					font-size: 14px;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-message.success {
					background: #e8f5e9;
					color: #2e7d32;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-message.error {
					background: #ffebee;
					color: #c62828;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-history {
					background: #fff;
					border: 1px solid #e0e0e0;
					border-radius: 12px;
					overflow: hidden;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-history h3 {
					font-size: 18px;
					font-weight: 600;
					margin: 0;
					padding: 16px 20px;
					border-bottom: 1px solid #e0e0e0;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-transactions {
					max-height: 400px;
					overflow-y: auto;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-transaction {
					display: flex;
					justify-content: space-between;
					align-items: center;
					padding: 14px 20px;
					border-bottom: 1px solid #f0f0f0;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-transaction:last-child {
					border-bottom: none;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-tx-info {
					display: flex;
					flex-direction: column;
					gap: 2px;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-tx-type {
					font-weight: 500;
					font-size: 14px;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-tx-date {
					font-size: 12px;
					opacity: 0.6;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-tx-amount {
					font-weight: 600;
					font-size: 14px;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-tx-amount.credit {
					color: #2e7d32;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-tx-amount.debit {
					color: #c62828;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-no-transactions {
					padding: 24px;
					text-align: center;
					opacity: 0.6;
				}
				#<?php echo esc_attr( $widget_id ); ?> .wallet-limits {
					font-size: 12px;
					color: #888;
					margin-top: 8px;
				}
			</style>

			<?php if ( $settings['title'] ) : ?>
				<div class="wallet-header">
					<h2 class="wallet-title"><?php echo esc_html( $settings['title'] ); ?></h2>
					<?php if ( $settings['description'] ) : ?>
						<p class="wallet-description"><?php echo esc_html( $settings['description'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $deposit_message ) : ?>
				<div class="wallet-message <?php echo $deposit_status === 'success' ? 'success' : 'error'; ?>">
					<?php echo esc_html( $deposit_message ); ?>
				</div>
			<?php endif; ?>

			<div class="wallet-balance-card">
				<div class="wallet-balance-label"><?php _e( 'Available Balance', 'voxel-payment-gateways' ); ?></div>
				<div class="wallet-balance-amount" id="<?php echo esc_attr( $widget_id ); ?>-balance">
					<?php echo esc_html( $balance_formatted ); ?>
				</div>
			</div>

			<div class="wallet-add-funds">
				<h3><?php _e( 'Add Funds', 'voxel-payment-gateways' ); ?></h3>

				<div id="<?php echo esc_attr( $widget_id ); ?>-message" class="wallet-message" style="display: none;"></div>

				<?php if ( $settings['show_preset_amounts'] === 'yes' && ! empty( $preset_amounts ) ) : ?>
					<div class="wallet-preset-amounts">
						<?php foreach ( $preset_amounts as $amount ) : ?>
							<button type="button" class="wallet-preset-btn" data-amount="<?php echo esc_attr( $amount ); ?>">
								<?php echo esc_html( \VoxelPayPal\Wallet_Client::format_amount( $amount, $currency ) ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<form id="<?php echo esc_attr( $widget_id ); ?>-form" class="wallet-deposit-form">
					<div class="wallet-amount-input-group">
						<div class="wallet-amount-wrapper">
							<span class="wallet-currency-prefix"><?php echo esc_html( $currency ); ?></span>
							<input
								type="number"
								id="<?php echo esc_attr( $widget_id ); ?>-amount"
								class="wallet-amount-input"
								name="amount"
								placeholder="0.00"
								min="<?php echo esc_attr( $min_deposit ); ?>"
								max="<?php echo esc_attr( $max_deposit ); ?>"
								step="0.01"
								required
							/>
						</div>
						<button type="submit" class="wallet-submit-btn" id="<?php echo esc_attr( $widget_id ); ?>-submit">
							<?php echo esc_html( $settings['button_text'] ?: __( 'Add Funds', 'voxel-payment-gateways' ) ); ?>
						</button>
					</div>
					<div class="wallet-limits">
						<?php printf(
							__( 'Min: %1$s | Max: %2$s', 'voxel-payment-gateways' ),
							\VoxelPayPal\Wallet_Client::format_amount( $min_deposit, $currency ),
							\VoxelPayPal\Wallet_Client::format_amount( $max_deposit, $currency )
						); ?>
					</div>
				</form>
			</div>

			<?php if ( $settings['show_history'] === 'yes' ) : ?>
				<div class="wallet-history">
					<h3><?php _e( 'Transaction History', 'voxel-payment-gateways' ); ?></h3>
					<div class="wallet-transactions" id="<?php echo esc_attr( $widget_id ); ?>-transactions">
						<?php if ( empty( $transactions ) ) : ?>
							<div class="wallet-no-transactions">
								<?php _e( 'No transactions yet', 'voxel-payment-gateways' ); ?>
							</div>
						<?php else : ?>
							<?php foreach ( $transactions as $tx ) : ?>
								<div class="wallet-transaction">
									<div class="wallet-tx-info">
										<span class="wallet-tx-type">
											<?php
											$type_labels = [
												'deposit' => __( 'Deposit', 'voxel-payment-gateways' ),
												'purchase' => __( 'Purchase', 'voxel-payment-gateways' ),
												'refund' => __( 'Refund', 'voxel-payment-gateways' ),
												'adjustment' => __( 'Adjustment', 'voxel-payment-gateways' ),
											];
											echo esc_html( $type_labels[ $tx['type'] ] ?? ucfirst( $tx['type'] ) );
											?>
										</span>
										<span class="wallet-tx-date">
											<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $tx['created_at'] ) ) ); ?>
										</span>
									</div>
									<span class="wallet-tx-amount <?php echo $tx['is_credit'] ? 'credit' : 'debit'; ?>">
										<?php echo $tx['is_credit'] ? '+' : '-'; ?><?php echo esc_html( $tx['amount_formatted'] ); ?>
									</span>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<script>
			(function() {
				const widgetId = '<?php echo esc_js( $widget_id ); ?>';
				const form = document.getElementById(widgetId + '-form');
				const amountInput = document.getElementById(widgetId + '-amount');
				const submitBtn = document.getElementById(widgetId + '-submit');
				const messageDiv = document.getElementById(widgetId + '-message');
				const presetBtns = document.querySelectorAll('#' + widgetId + ' .wallet-preset-btn');

				// Handle preset amount buttons
				presetBtns.forEach(function(btn) {
					btn.addEventListener('click', function() {
						const amount = this.getAttribute('data-amount');
						amountInput.value = amount;

						// Update active state
						presetBtns.forEach(b => b.classList.remove('active'));
						this.classList.add('active');
					});
				});

				// Clear active state when typing
				amountInput.addEventListener('input', function() {
					presetBtns.forEach(b => b.classList.remove('active'));
				});

				// Handle form submit
				form.addEventListener('submit', async function(e) {
					e.preventDefault();

					const amount = parseFloat(amountInput.value);

					if (isNaN(amount) || amount <= 0) {
						showMessage('<?php echo esc_js( __( 'Please enter a valid amount', 'voxel-payment-gateways' ) ); ?>', 'error');
						return;
					}

					submitBtn.disabled = true;
					submitBtn.textContent = '<?php echo esc_js( __( 'Processing...', 'voxel-payment-gateways' ) ); ?>';
					messageDiv.style.display = 'none';

					try {
						const response = await fetch('<?php echo home_url( '/?vx=1&action=wallet.deposit.initiate' ); ?>', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
							},
							body: JSON.stringify({ amount: amount, return_url: window.location.href })
						});

						const data = await response.json();

						if (data.success && data.redirect_url) {
							window.location.href = data.redirect_url;
						} else {
							showMessage(data.message || '<?php echo esc_js( __( 'Failed to initiate deposit', 'voxel-payment-gateways' ) ); ?>', 'error');
							submitBtn.disabled = false;
							submitBtn.textContent = '<?php echo esc_js( $settings['button_text'] ?: __( 'Add Funds', 'voxel-payment-gateways' ) ); ?>';
						}
					} catch (error) {
						showMessage('<?php echo esc_js( __( 'An error occurred', 'voxel-payment-gateways' ) ); ?>', 'error');
						submitBtn.disabled = false;
						submitBtn.textContent = '<?php echo esc_js( $settings['button_text'] ?: __( 'Add Funds', 'voxel-payment-gateways' ) ); ?>';
					}
				});

				function showMessage(text, type) {
					messageDiv.textContent = text;
					messageDiv.className = 'wallet-message ' + type;
					messageDiv.style.display = 'block';
				}
			})();
			</script>
		</div>
		<?php
	}

	/**
	 * Adjust color brightness
	 */
	private function adjust_brightness( string $hex, int $steps ): string {
		$hex = str_replace( '#', '', $hex );

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		$r = max( 0, min( 255, $r + $steps ) );
		$g = max( 0, min( 255, $g + $steps ) );
		$b = max( 0, min( 255, $b + $steps ) );

		return '#' . sprintf( '%02x%02x%02x', $r, $g, $b );
	}

	protected function content_template(): void {
		?>
		<#
		var primaryColor = settings.primary_color || '#4caf50';
		#>
		<div class="voxel-wallet-widget">
			<# if ( settings.title ) { #>
				<div class="wallet-header">
					<h2 class="wallet-title" style="font-size: 24px; font-weight: 600; margin: 0 0 8px 0;">{{{ settings.title }}}</h2>
					<# if ( settings.description ) { #>
						<p class="wallet-description" style="margin: 0; opacity: 0.7; font-size: 14px;">{{{ settings.description }}}</p>
					<# } #>
				</div>
			<# } #>

			<div class="wallet-balance-card" style="background: linear-gradient(135deg, {{ primaryColor }} 0%, {{ primaryColor }} 100%); color: #fff; padding: 24px; border-radius: 12px; margin: 24px 0;">
				<div style="font-size: 14px; opacity: 0.9; margin-bottom: 4px;"><?php _e( 'Available Balance', 'voxel-payment-gateways' ); ?></div>
				<div style="font-size: 36px; font-weight: 700;">$0.00</div>
			</div>

			<div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; padding: 24px; margin-bottom: 24px;">
				<h3 style="font-size: 18px; font-weight: 600; margin: 0 0 16px 0;"><?php _e( 'Add Funds', 'voxel-payment-gateways' ); ?></h3>

				<# if ( settings.show_preset_amounts === 'yes' ) { #>
					<div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 16px;">
						<button style="padding: 8px 16px; border: 2px solid {{ primaryColor }}; background: transparent; color: {{ primaryColor }}; border-radius: 6px;">$10</button>
						<button style="padding: 8px 16px; border: 2px solid {{ primaryColor }}; background: transparent; color: {{ primaryColor }}; border-radius: 6px;">$25</button>
						<button style="padding: 8px 16px; border: 2px solid {{ primaryColor }}; background: transparent; color: {{ primaryColor }}; border-radius: 6px;">$50</button>
						<button style="padding: 8px 16px; border: 2px solid {{ primaryColor }}; background: transparent; color: {{ primaryColor }}; border-radius: 6px;">$100</button>
					</div>
				<# } #>

				<div style="display: flex; gap: 12px;">
					<div style="flex: 1; position: relative;">
						<span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 600; color: #666;">USD</span>
						<input type="number" placeholder="0.00" style="width: 100%; padding: 12px 12px 12px 48px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px; box-sizing: border-box;" />
					</div>
					<button style="padding: 12px 24px; background: {{ primaryColor }}; color: #fff; border: none; border-radius: 6px; font-weight: 600;">
						{{{ settings.button_text || '<?php _e( 'Add Funds', 'voxel-payment-gateways' ); ?>' }}}
					</button>
				</div>
			</div>

			<# if ( settings.show_history === 'yes' ) { #>
				<div style="background: #fff; border: 1px solid #e0e0e0; border-radius: 12px; overflow: hidden;">
					<h3 style="font-size: 18px; font-weight: 600; margin: 0; padding: 16px 20px; border-bottom: 1px solid #e0e0e0;"><?php _e( 'Transaction History', 'voxel-payment-gateways' ); ?></h3>
					<div style="padding: 24px; text-align: center; opacity: 0.6;">
						<?php _e( 'No transactions yet', 'voxel-payment-gateways' ); ?>
					</div>
				</div>
			<# } #>
		</div>
		<?php
	}
}
