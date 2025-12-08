<?php

namespace VoxelPayPal\Widgets;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * PayPal Connect Elementor Widget
 * Allows vendors to manage their PayPal email for marketplace payouts
 */
class PayPal_Connect_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'paypal-connect';
	}

	public function get_title() {
		return __( 'PayPal Connect', 'voxel-payment-gateways' );
	}

	public function get_icon() {
		return 'eicon-paypal-button';
	}

	public function get_categories() {
		return [ 'voxel', 'general' ];
	}

	public function get_keywords() {
		return [ 'paypal', 'vendor', 'payout', 'marketplace', 'connect' ];
	}

	protected function register_controls() {
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
				'default' => __( 'PayPal Payout Settings', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'description',
			[
				'label' => __( 'Description', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXTAREA,
				'default' => __( 'Set your PayPal email to receive payments from your sales.', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'button_text',
			[
				'label' => __( 'Button Text', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Save PayPal Email', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'show_earnings',
			[
				'label' => __( 'Show Earnings Summary', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'label_on' => __( 'Show', 'voxel-payment-gateways' ),
				'label_off' => __( 'Hide', 'voxel-payment-gateways' ),
				'return_value' => 'yes',
				'default' => 'no',
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
				'selectors' => [
					'{{WRAPPER}} .paypal-connect-title' => 'color: {{VALUE}}',
				],
			]
		);

		$this->add_control(
			'button_color',
			[
				'label' => __( 'Button Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .paypal-connect-submit' => 'background-color: {{VALUE}}',
				],
			]
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();
		$user_id = get_current_user_id();

		// Check if user is logged in
		if ( ! $user_id ) {
			echo '<div class="paypal-connect-widget">';
			echo '<p>' . __( 'Please log in to manage your PayPal settings.', 'voxel-payment-gateways' ) . '</p>';
			echo '</div>';
			return;
		}

		// Check if marketplace is enabled
		$marketplace_enabled = (bool) \Voxel\get( 'payments.paypal.marketplace.enabled', 0 );

		if ( ! $marketplace_enabled ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="paypal-connect-widget">';
				echo '<p style="color: #f00;">' . __( 'Marketplace mode is not enabled in PayPal settings.', 'voxel-payment-gateways' ) . '</p>';
				echo '</div>';
			}
			return;
		}

		$current_email = \VoxelPayPal\PayPal_Connect_Client::get_vendor_paypal_email( $user_id );
		$user = wp_get_current_user();

		// Get earnings if enabled
		$earnings_data = null;
		if ( $settings['show_earnings'] === 'yes' ) {
			$earnings_data = $this->get_vendor_earnings( $user_id );
		}

		?>
		<div class="paypal-connect-widget">
			<?php if ( ! empty( $settings['title'] ) ): ?>
				<h3 class="paypal-connect-title"><?php echo esc_html( $settings['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( ! empty( $settings['description'] ) ): ?>
				<p class="paypal-connect-description"><?php echo esc_html( $settings['description'] ); ?></p>
			<?php endif; ?>

			<?php if ( $earnings_data ): ?>
				<div class="paypal-connect-earnings">
					<div class="earnings-grid">
						<div class="earnings-item">
							<span class="earnings-label"><?php _e( 'Total Earnings', 'voxel-payment-gateways' ); ?></span>
							<span class="earnings-value">$<?php echo number_format( $earnings_data['total'], 2 ); ?></span>
						</div>
						<div class="earnings-item">
							<span class="earnings-label"><?php _e( 'Platform Fees', 'voxel-payment-gateways' ); ?></span>
							<span class="earnings-value">$<?php echo number_format( $earnings_data['total_fees'], 2 ); ?></span>
						</div>
						<div class="earnings-item">
							<span class="earnings-label"><?php _e( 'Orders', 'voxel-payment-gateways' ); ?></span>
							<span class="earnings-value"><?php echo intval( $earnings_data['order_count'] ); ?></span>
						</div>
						<?php if ( $earnings_data['pending_payouts'] > 0 ): ?>
							<div class="earnings-item">
								<span class="earnings-label"><?php _e( 'Pending Payouts', 'voxel-payment-gateways' ); ?></span>
								<span class="earnings-value"><?php echo intval( $earnings_data['pending_payouts'] ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<form class="paypal-connect-form" data-widget-id="<?php echo esc_attr( $this->get_id() ); ?>">
				<div class="form-group">
					<label for="paypal_email_<?php echo esc_attr( $this->get_id() ); ?>">
						<?php _e( 'PayPal Email', 'voxel-payment-gateways' ); ?>
					</label>
					<input
						type="email"
						id="paypal_email_<?php echo esc_attr( $this->get_id() ); ?>"
						name="paypal_email"
						value="<?php echo esc_attr( $current_email ?? '' ); ?>"
						placeholder="<?php echo esc_attr( $user->user_email ); ?>"
						class="paypal-connect-input"
						required
					/>
					<small class="paypal-connect-help">
						<?php _e( 'If not set, payments will be sent to your account email.', 'voxel-payment-gateways' ); ?>
					</small>
				</div>

				<button type="submit" class="paypal-connect-submit">
					<?php echo esc_html( $settings['button_text'] ); ?>
				</button>

				<div class="paypal-connect-message" style="display:none;"></div>
			</form>
		</div>

		<style>
		.paypal-connect-widget {
			max-width: 600px;
			padding: 20px;
		}
		.paypal-connect-title {
			margin-bottom: 10px;
			font-size: 24px;
			font-weight: 600;
		}
		.paypal-connect-description {
			margin-bottom: 20px;
			color: #666;
		}
		.paypal-connect-earnings {
			margin-bottom: 30px;
			padding: 20px;
			background: #f8f9fa;
			border-radius: 8px;
		}
		.earnings-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
			gap: 20px;
		}
		.earnings-item {
			display: flex;
			flex-direction: column;
		}
		.earnings-label {
			font-size: 12px;
			color: #666;
			margin-bottom: 5px;
		}
		.earnings-value {
			font-size: 24px;
			font-weight: 600;
			color: #333;
		}
		.paypal-connect-form .form-group {
			margin-bottom: 20px;
		}
		.paypal-connect-form label {
			display: block;
			margin-bottom: 8px;
			font-weight: 600;
			color: #333;
		}
		.paypal-connect-input {
			width: 100%;
			padding: 12px;
			border: 1px solid #ddd;
			border-radius: 4px;
			font-size: 14px;
		}
		.paypal-connect-input:focus {
			outline: none;
			border-color: #0077cc;
		}
		.paypal-connect-help {
			display: block;
			margin-top: 5px;
			font-size: 12px;
			color: #666;
		}
		.paypal-connect-submit {
			background-color: #0077cc;
			color: white;
			border: none;
			padding: 12px 24px;
			border-radius: 4px;
			font-size: 14px;
			font-weight: 600;
			cursor: pointer;
			transition: background-color 0.2s;
		}
		.paypal-connect-submit:hover {
			background-color: #005fa3;
		}
		.paypal-connect-message {
			margin-top: 15px;
			padding: 12px;
			border-radius: 4px;
			font-size: 14px;
		}
		.paypal-connect-message.success {
			background-color: #d4edda;
			color: #155724;
			border: 1px solid #c3e6cb;
		}
		.paypal-connect-message.error {
			background-color: #f8d7da;
			color: #721c24;
			border: 1px solid #f5c6cb;
		}
		</style>

		<script>
		(function() {
			const form = document.querySelector('.paypal-connect-form[data-widget-id="<?php echo esc_js( $this->get_id() ); ?>"]');
			const messageDiv = form ? form.querySelector('.paypal-connect-message') : null;

			if (form && messageDiv) {
				form.addEventListener('submit', async function(e) {
					e.preventDefault();

					const emailInput = form.querySelector('input[name="paypal_email"]');
					const email = emailInput ? emailInput.value : '';

					try {
						const url = '<?php echo home_url( '/?vx=1&action=paypal.vendor.save_paypal_email' ); ?>';
						console.log('PayPal: Sending request to:', url);
						console.log('PayPal: Email:', email);

						const response = await fetch(url, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
							},
							body: JSON.stringify({ email: email })
						});

						console.log('PayPal: Response status:', response.status);
						const responseText = await response.text();
						console.log('PayPal: Response text:', responseText);

						let data;
						try {
							data = JSON.parse(responseText);
						} catch (e) {
							console.error('PayPal: Failed to parse JSON response:', e);
							throw new Error('Invalid response from server: ' + responseText.substring(0, 100));
						}

						console.log('PayPal: Response data:', data);

						messageDiv.style.display = 'block';

						if (data.success) {
							messageDiv.className = 'paypal-connect-message success';
							messageDiv.textContent = '<?php _e( 'PayPal email saved successfully!', 'voxel-payment-gateways' ); ?>';
						} else {
							messageDiv.className = 'paypal-connect-message error';
							messageDiv.textContent = data.error || '<?php _e( 'Failed to save email.', 'voxel-payment-gateways' ); ?>';
						}

						// Hide message after 5 seconds
						setTimeout(function() {
							messageDiv.style.display = 'none';
						}, 5000);

					} catch (error) {
						console.error('PayPal: Error saving email:', error);
						messageDiv.style.display = 'block';
						messageDiv.className = 'paypal-connect-message error';
						messageDiv.textContent = error.message || '<?php _e( 'An error occurred. Please try again.', 'voxel-payment-gateways' ); ?>';
					}
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Get vendor earnings summary
	 */
	private function get_vendor_earnings( int $user_id ): ?array {
		// Query orders where vendor is the product author
		$orders = \Voxel\Product_Types\Orders\Order::query( [
			'limit' => 1000,
		] );

		$total_earnings = 0;
		$total_fees = 0;
		$order_count = 0;

		foreach ( $orders as $order ) {
			// Check if this order belongs to the vendor
			$items = $order->get_items();
			if ( empty( $items ) ) {
				continue;
			}

			$first_item = reset( $items );
			$post_id = $first_item->get_details( 'post_id' );

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

		// Query pending payouts
		$args = [
			'post_type' => 'voxel_vendor_order',
			'author' => $user_id,
			'posts_per_page' => -1,
			'meta_query' => [
				[
					'key' => 'payout_status',
					'value' => [ 'pending', 'processing' ],
					'compare' => 'IN',
				],
			],
		];

		$query = new \WP_Query( $args );

		return [
			'total' => round( $total_earnings, 2 ),
			'total_fees' => round( $total_fees, 2 ),
			'order_count' => $order_count,
			'pending_payouts' => $query->found_posts,
		];
	}

	protected function content_template() {
		?>
		<#
		var widgetId = 'paypal-connect-' + Math.random().toString(36).substr(2, 9);
		#>
		<div class="paypal-connect-widget">
			<# if ( settings.title ) { #>
				<h3 class="paypal-connect-title">{{{ settings.title }}}</h3>
			<# } #>

			<# if ( settings.description ) { #>
				<p class="paypal-connect-description">{{{ settings.description }}}</p>
			<# } #>

			<# if ( settings.show_earnings === 'yes' ) { #>
				<div class="paypal-connect-earnings">
					<div class="earnings-grid">
						<div class="earnings-item">
							<span class="earnings-label"><?php _e( 'Total Earnings', 'voxel-payment-gateways' ); ?></span>
							<span class="earnings-value">$0.00</span>
						</div>
						<div class="earnings-item">
							<span class="earnings-label"><?php _e( 'Platform Fees', 'voxel-payment-gateways' ); ?></span>
							<span class="earnings-value">$0.00</span>
						</div>
						<div class="earnings-item">
							<span class="earnings-label"><?php _e( 'Orders', 'voxel-payment-gateways' ); ?></span>
							<span class="earnings-value">0</span>
						</div>
					</div>
				</div>
			<# } #>

			<form class="paypal-connect-form">
				<div class="form-group">
					<label for="{{ widgetId }}">
						<?php _e( 'PayPal Email', 'voxel-payment-gateways' ); ?>
					</label>
					<input
						type="email"
						id="{{ widgetId }}"
						name="paypal_email"
						placeholder="vendor@example.com"
						class="paypal-connect-input"
						required
					/>
					<small class="paypal-connect-help">
						<?php _e( 'If not set, payments will be sent to your account email.', 'voxel-payment-gateways' ); ?>
					</small>
				</div>

				<button type="submit" class="paypal-connect-submit">
					{{{ settings.button_text }}}
				</button>
			</form>
		</div>
		<?php
	}
}
