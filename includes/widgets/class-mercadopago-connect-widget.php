<?php

namespace VoxelPayPal\Widgets;

use VoxelPayPal\MercadoPago_Connect_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Mercado Pago Connect Widget for Elementor
 * Allows vendors to connect their Mercado Pago accounts for marketplace payouts
 */
class MercadoPago_Connect_Widget extends \Elementor\Widget_Base {

	public function get_name(): string {
		return 'mercadopago-connect';
	}

	public function get_title(): string {
		return __( 'Mercado Pago Connect', 'voxel-payment-gateways' );
	}

	public function get_icon(): string {
		return 'eicon-theme-builder';
	}

	public function get_categories(): array {
		return [ 'voxel' ];
	}

	public function get_keywords(): array {
		return [ 'mercado', 'pago', 'connect', 'payment', 'vendor', 'marketplace' ];
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
				'default' => __( 'Mercado Pago Account', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'description',
			[
				'label' => __( 'Description', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXTAREA,
				'default' => __( 'Connect your Mercado Pago account to receive payments from your sales.', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'connect_button_text',
			[
				'label' => __( 'Connect Button Text', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Connect Mercado Pago', 'voxel-payment-gateways' ),
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
					'{{WRAPPER}} .mp-connect-title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'button_background',
			[
				'label' => __( 'Button Background', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#009ee3',
				'selectors' => [
					'{{WRAPPER}} .mp-connect-button' => 'background-color: {{VALUE}};',
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
					'{{WRAPPER}} .mp-connect-button' => 'color: {{VALUE}};',
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
			echo '<p>' . esc_html__( 'Please log in to manage your Mercado Pago settings.', 'voxel-payment-gateways' ) . '</p>';
			return;
		}

		// Check if marketplace is enabled
		if ( ! MercadoPago_Connect_Client::is_marketplace_enabled() ) {
			return; // Silently return if marketplace is not enabled
		}

		$is_connected = MercadoPago_Connect_Client::is_vendor_connected( $user_id );
		$account_info = null;

		if ( $is_connected ) {
			$account_info = MercadoPago_Connect_Client::get_vendor_account_info( $user_id );
		}

		$connect_url = add_query_arg( [
			'vx' => 1,
			'action' => 'mercadopago.oauth.connect',
		], home_url( '/' ) );

		$disconnect_url = add_query_arg( [
			'vx' => 1,
			'action' => 'mercadopago.oauth.disconnect',
			'_wpnonce' => wp_create_nonce( 'mercadopago_disconnect_' . $user_id ),
		], home_url( '/' ) );

		?>
		<div class="mp-connect-widget">
			<?php if ( ! empty( $settings['title'] ) ) : ?>
				<h3 class="mp-connect-title"><?php echo esc_html( $settings['title'] ); ?></h3>
			<?php endif; ?>

			<?php if ( ! empty( $settings['description'] ) && ! $is_connected ) : ?>
				<p class="mp-connect-description"><?php echo esc_html( $settings['description'] ); ?></p>
			<?php endif; ?>

			<?php if ( $is_connected ) : ?>
				<div class="mp-connect-status mp-connected">
					<div class="mp-status-icon">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="24" height="24">
							<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
						</svg>
					</div>
					<div class="mp-status-info">
						<span class="mp-status-label"><?php _e( 'Connected', 'voxel-payment-gateways' ); ?></span>
						<?php if ( $account_info && ! empty( $account_info['email'] ) ) : ?>
							<span class="mp-account-email"><?php echo esc_html( $account_info['email'] ); ?></span>
						<?php endif; ?>
					</div>
				</div>

				<a href="<?php echo esc_url( $disconnect_url ); ?>" class="mp-connect-button mp-disconnect" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to disconnect your Mercado Pago account?', 'voxel-payment-gateways' ); ?>');">
					<?php echo esc_html( $settings['disconnect_button_text'] ); ?>
				</a>
			<?php else : ?>
				<a href="<?php echo esc_url( $connect_url ); ?>" class="mp-connect-button">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="20" height="20" style="margin-right: 8px;">
						<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
					</svg>
					<?php echo esc_html( $settings['connect_button_text'] ); ?>
				</a>
			<?php endif; ?>
		</div>

		<style>
			.mp-connect-widget {
				padding: 20px;
				border: 1px solid #e0e0e0;
				border-radius: 8px;
				background: #fff;
			}
			.mp-connect-title {
				margin: 0 0 10px 0;
				font-size: 18px;
				font-weight: 600;
			}
			.mp-connect-description {
				margin: 0 0 20px 0;
				color: #666;
			}
			.mp-connect-status {
				display: flex;
				align-items: center;
				padding: 15px;
				margin-bottom: 15px;
				border-radius: 6px;
				background: #e8f5e9;
			}
			.mp-connect-status.mp-connected .mp-status-icon {
				color: #4caf50;
				margin-right: 12px;
			}
			.mp-status-info {
				display: flex;
				flex-direction: column;
			}
			.mp-status-label {
				font-weight: 600;
				color: #2e7d32;
			}
			.mp-account-email {
				font-size: 13px;
				color: #666;
				margin-top: 2px;
			}
			.mp-connect-button {
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
			.mp-connect-button:hover {
				opacity: 0.9;
			}
			.mp-connect-button.mp-disconnect {
				background: #f5f5f5 !important;
				color: #666 !important;
			}
			.mp-connect-button.mp-disconnect:hover {
				background: #e0e0e0 !important;
			}
		</style>
		<?php
	}
}
