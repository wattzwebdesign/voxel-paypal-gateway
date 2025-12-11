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
		return 'eicon-price-table';
	}

	public function get_categories(): array {
		return [ 'voxel' ];
	}

	public function get_keywords(): array {
		return [ 'wallet', 'balance', 'funds', 'payment', 'deposit', 'voxel' ];
	}

	protected function register_controls(): void {
		// ========================================
		// CONTENT TAB
		// ========================================

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

		// ========================================
		// CONTENT TAB - Labels Section
		// ========================================

		$this->start_controls_section(
			'labels_section',
			[
				'label' => __( 'Labels', 'voxel-payment-gateways' ),
				'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'label_available_balance',
			[
				'label' => __( 'Available Balance Label', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Available Balance', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'label_add_funds_title',
			[
				'label' => __( 'Add Funds Section Title', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Add Funds', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'label_history_title',
			[
				'label' => __( 'Transaction History Title', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Transaction History', 'voxel-payment-gateways' ),
				'condition' => [
					'show_history' => 'yes',
				],
			]
		);

		$this->add_control(
			'label_no_transactions',
			[
				'label' => __( 'No Transactions Text', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'No transactions yet', 'voxel-payment-gateways' ),
				'condition' => [
					'show_history' => 'yes',
				],
			]
		);

		$this->add_control(
			'label_limits_heading',
			[
				'label' => __( 'Deposit Limits', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'label_min',
			[
				'label' => __( 'Minimum Label', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Min', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'label_max',
			[
				'label' => __( 'Maximum Label', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Max', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'label_tx_types_heading',
			[
				'label' => __( 'Transaction Type Labels', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'label_deposit',
			[
				'label' => __( 'Deposit', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Deposit', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'label_purchase',
			[
				'label' => __( 'Purchase', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Purchase', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'label_refund',
			[
				'label' => __( 'Refund', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Refund', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'label_adjustment',
			[
				'label' => __( 'Adjustment', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Adjustment', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'label_messages_heading',
			[
				'label' => __( 'Messages', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'label_processing',
			[
				'label' => __( 'Processing Text', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Processing...', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'label_invalid_amount',
			[
				'label' => __( 'Invalid Amount Error', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Please enter a valid amount', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'label_deposit_failed',
			[
				'label' => __( 'Deposit Failed Error', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Failed to initiate deposit', 'voxel-payment-gateways' ),
			]
		);

		$this->add_control(
			'label_error_occurred',
			[
				'label' => __( 'General Error', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'An error occurred', 'voxel-payment-gateways' ),
			]
		);

		$this->end_controls_section();

		// ========================================
		// STYLE TAB - Header Section
		// ========================================

		$this->start_controls_section(
			'header_style_section',
			[
				'label' => __( 'Header', 'voxel-payment-gateways' ),
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
					'{{WRAPPER}} .voxel-wallet-widget .wallet-title' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'title_typography',
				'label' => __( 'Title Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-title',
			]
		);

		$this->add_responsive_control(
			'title_margin',
			[
				'label' => __( 'Title Margin', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-title' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'description_color',
			[
				'label' => __( 'Description Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#666666',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-description' => 'color: {{VALUE}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'description_typography',
				'label' => __( 'Description Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-description',
			]
		);

		$this->add_responsive_control(
			'header_margin',
			[
				'label' => __( 'Header Margin', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-header' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);

		$this->end_controls_section();

		// ========================================
		// STYLE TAB - Balance Card Section
		// ========================================

		$this->start_controls_section(
			'balance_style_section',
			[
				'label' => __( 'Balance Card', 'voxel-payment-gateways' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'balance_bg_color',
			[
				'label' => __( 'Background Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#4caf50',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-balance-card' => 'background: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'balance_bg_gradient',
			[
				'label' => __( 'Use Gradient', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'balance_bg_gradient_end',
			[
				'label' => __( 'Gradient End Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#388e3c',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-balance-card' => 'background: linear-gradient(135deg, {{balance_bg_color.VALUE}} 0%, {{VALUE}} 100%);',
				],
				'condition' => [
					'balance_bg_gradient' => 'yes',
				],
			]
		);

		$this->add_control(
			'balance_text_color',
			[
				'label' => __( 'Text Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-balance-card' => 'color: {{VALUE}};',
					'{{WRAPPER}} .voxel-wallet-widget .wallet-balance-card *' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'balance_label_heading',
			[
				'label' => __( 'Balance Label', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'balance_label_typography',
				'label' => __( 'Label Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-balance-label',
			]
		);

		$this->add_control(
			'balance_amount_heading',
			[
				'label' => __( 'Balance Amount', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'balance_amount_typography',
				'label' => __( 'Amount Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-balance-amount',
			]
		);

		$this->add_responsive_control(
			'balance_card_padding',
			[
				'label' => __( 'Padding', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'default' => [
					'top' => '24',
					'right' => '24',
					'bottom' => '24',
					'left' => '24',
					'unit' => 'px',
				],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-balance-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'balance_card_margin',
			[
				'label' => __( 'Margin', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-balance-card' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'balance_card_border_radius',
			[
				'label' => __( 'Border Radius', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default' => [
					'top' => '12',
					'right' => '12',
					'bottom' => '12',
					'left' => '12',
					'unit' => 'px',
				],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-balance-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'balance_card_border',
				'label' => __( 'Border', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-balance-card',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'balance_card_shadow',
				'label' => __( 'Box Shadow', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-balance-card',
			]
		);

		$this->end_controls_section();

		// ========================================
		// STYLE TAB - Add Funds Card Section
		// ========================================

		$this->start_controls_section(
			'add_funds_style_section',
			[
				'label' => __( 'Add Funds Card', 'voxel-payment-gateways' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'add_funds_bg_color',
			[
				'label' => __( 'Background Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-add-funds' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'add_funds_title_color',
			[
				'label' => __( 'Title Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#333333',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-add-funds h3' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'add_funds_title_typography',
				'label' => __( 'Title Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-add-funds h3',
			]
		);

		$this->add_responsive_control(
			'add_funds_padding',
			[
				'label' => __( 'Padding', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'default' => [
					'top' => '24',
					'right' => '24',
					'bottom' => '24',
					'left' => '24',
					'unit' => 'px',
				],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-add-funds' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'add_funds_margin',
			[
				'label' => __( 'Margin', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-add-funds' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'add_funds_border_radius',
			[
				'label' => __( 'Border Radius', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default' => [
					'top' => '12',
					'right' => '12',
					'bottom' => '12',
					'left' => '12',
					'unit' => 'px',
				],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-add-funds' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'add_funds_border',
				'label' => __( 'Border', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-add-funds',
				'fields_options' => [
					'border' => [
						'default' => 'solid',
					],
					'width' => [
						'default' => [
							'top' => '1',
							'right' => '1',
							'bottom' => '1',
							'left' => '1',
							'unit' => 'px',
						],
					],
					'color' => [
						'default' => '#e0e0e0',
					],
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'add_funds_shadow',
				'label' => __( 'Box Shadow', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-add-funds',
			]
		);

		$this->end_controls_section();

		// ========================================
		// STYLE TAB - Preset Amount Buttons
		// ========================================

		$this->start_controls_section(
			'preset_buttons_style_section',
			[
				'label' => __( 'Quick Amount Buttons', 'voxel-payment-gateways' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_preset_amounts' => 'yes',
				],
			]
		);

		$this->add_control(
			'preset_btn_color',
			[
				'label' => __( 'Primary Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#4caf50',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-preset-btn' => 'border-color: {{VALUE}}; color: {{VALUE}};',
					'{{WRAPPER}} .voxel-wallet-widget .wallet-preset-btn:hover, {{WRAPPER}} .voxel-wallet-widget .wallet-preset-btn.active' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'preset_btn_hover_text_color',
			[
				'label' => __( 'Hover/Active Text Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-preset-btn:hover, {{WRAPPER}} .voxel-wallet-widget .wallet-preset-btn.active' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'preset_btn_typography',
				'label' => __( 'Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-preset-btn',
			]
		);

		$this->add_responsive_control(
			'preset_btn_padding',
			[
				'label' => __( 'Padding', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'default' => [
					'top' => '8',
					'right' => '16',
					'bottom' => '8',
					'left' => '16',
					'unit' => 'px',
				],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-preset-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'preset_btn_margin',
			[
				'label' => __( 'Button Spacing', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 0,
						'max' => 30,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 8,
				],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-preset-amounts' => 'gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'preset_btn_border_radius',
			[
				'label' => __( 'Border Radius', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default' => [
					'top' => '6',
					'right' => '6',
					'bottom' => '6',
					'left' => '6',
					'unit' => 'px',
				],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-preset-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'preset_btn_border_width',
			[
				'label' => __( 'Border Width', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range' => [
					'px' => [
						'min' => 1,
						'max' => 5,
					],
				],
				'default' => [
					'unit' => 'px',
					'size' => 2,
				],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-preset-btn' => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;',
				],
			]
		);

		$this->end_controls_section();

		// ========================================
		// STYLE TAB - Submit Button
		// ========================================

		$this->start_controls_section(
			'submit_button_style_section',
			[
				'label' => __( 'Submit Button', 'voxel-payment-gateways' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'submit_btn_bg_color',
			[
				'label' => __( 'Background Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#4caf50',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-submit-btn' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'submit_btn_text_color',
			[
				'label' => __( 'Text Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-submit-btn' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'submit_btn_hover_bg_color',
			[
				'label' => __( 'Hover Background Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#388e3c',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-submit-btn:hover' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'submit_btn_hover_text_color',
			[
				'label' => __( 'Hover Text Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-submit-btn:hover' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'submit_btn_typography',
				'label' => __( 'Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-submit-btn',
			]
		);

		$this->add_responsive_control(
			'submit_btn_padding',
			[
				'label' => __( 'Padding', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'default' => [
					'top' => '12',
					'right' => '24',
					'bottom' => '12',
					'left' => '24',
					'unit' => 'px',
				],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-submit-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'submit_btn_margin',
			[
				'label' => __( 'Margin', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-submit-btn' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'submit_btn_border_radius',
			[
				'label' => __( 'Border Radius', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default' => [
					'top' => '6',
					'right' => '6',
					'bottom' => '6',
					'left' => '6',
					'unit' => 'px',
				],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-submit-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'submit_btn_border',
				'label' => __( 'Border', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-submit-btn',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'submit_btn_shadow',
				'label' => __( 'Box Shadow', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-submit-btn',
			]
		);

		$this->end_controls_section();

		// ========================================
		// STYLE TAB - Input Field
		// ========================================

		$this->start_controls_section(
			'input_style_section',
			[
				'label' => __( 'Input Field', 'voxel-payment-gateways' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'input_bg_color',
			[
				'label' => __( 'Background Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-amount-input' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'input_text_color',
			[
				'label' => __( 'Text Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#333333',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-amount-input' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'input_placeholder_color',
			[
				'label' => __( 'Placeholder Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#999999',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-amount-input::placeholder' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'input_prefix_color',
			[
				'label' => __( 'Currency Prefix Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#666666',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-currency-prefix' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'input_focus_border_color',
			[
				'label' => __( 'Focus Border Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#4caf50',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-amount-input:focus' => 'border-color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'input_typography',
				'label' => __( 'Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-amount-input',
			]
		);

		$this->add_responsive_control(
			'input_padding',
			[
				'label' => __( 'Padding', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-amount-input' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'input_border_radius',
			[
				'label' => __( 'Border Radius', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default' => [
					'top' => '6',
					'right' => '6',
					'bottom' => '6',
					'left' => '6',
					'unit' => 'px',
				],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-amount-input' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'input_border',
				'label' => __( 'Border', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-amount-input',
			]
		);

		$this->end_controls_section();

		// ========================================
		// STYLE TAB - Transaction History
		// ========================================

		$this->start_controls_section(
			'history_style_section',
			[
				'label' => __( 'Transaction History', 'voxel-payment-gateways' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
				'condition' => [
					'show_history' => 'yes',
				],
			]
		);

		// History Card
		$this->add_control(
			'history_card_heading',
			[
				'label' => __( 'Card', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::HEADING,
			]
		);

		$this->add_control(
			'history_bg_color',
			[
				'label' => __( 'Background Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#ffffff',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-history' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'history_border_radius',
			[
				'label' => __( 'Border Radius', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%' ],
				'default' => [
					'top' => '12',
					'right' => '12',
					'bottom' => '12',
					'left' => '12',
					'unit' => 'px',
				],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-history' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Border::get_type(),
			[
				'name' => 'history_border',
				'label' => __( 'Border', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-history',
				'fields_options' => [
					'border' => [
						'default' => 'solid',
					],
					'width' => [
						'default' => [
							'top' => '1',
							'right' => '1',
							'bottom' => '1',
							'left' => '1',
							'unit' => 'px',
						],
					],
					'color' => [
						'default' => '#e0e0e0',
					],
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Box_Shadow::get_type(),
			[
				'name' => 'history_shadow',
				'label' => __( 'Box Shadow', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-history',
			]
		);

		// History Title
		$this->add_control(
			'history_title_heading',
			[
				'label' => __( 'Section Title', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'history_title_color',
			[
				'label' => __( 'Title Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#333333',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-history > h3' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'history_title_typography',
				'label' => __( 'Title Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-history > h3',
			]
		);

		$this->add_responsive_control(
			'history_title_padding',
			[
				'label' => __( 'Title Padding', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-history > h3' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'history_title_border_color',
			[
				'label' => __( 'Title Border Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#e0e0e0',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-history > h3' => 'border-bottom-color: {{VALUE}};',
				],
			]
		);

		// Transaction Row
		$this->add_control(
			'tx_row_heading',
			[
				'label' => __( 'Transaction Row', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'tx_row_padding',
			[
				'label' => __( 'Row Padding', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-transaction' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'tx_row_border_color',
			[
				'label' => __( 'Row Border Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#f0f0f0',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-transaction' => 'border-bottom-color: {{VALUE}};',
				],
			]
		);

		// Transaction Type Label
		$this->add_control(
			'tx_type_heading',
			[
				'label' => __( 'Transaction Type', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'tx_type_color',
			[
				'label' => __( 'Type Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#333333',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-tx-type' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'tx_type_typography',
				'label' => __( 'Type Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-tx-type',
			]
		);

		// Transaction Date
		$this->add_control(
			'tx_date_heading',
			[
				'label' => __( 'Transaction Date', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'tx_date_color',
			[
				'label' => __( 'Date Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#888888',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-tx-date' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'tx_date_typography',
				'label' => __( 'Date Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-tx-date',
			]
		);

		// Credit Amount
		$this->add_control(
			'tx_credit_heading',
			[
				'label' => __( 'Credit Amount (Positive)', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'tx_credit_color',
			[
				'label' => __( 'Credit Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#2e7d32',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-tx-amount.credit' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'tx_credit_typography',
				'label' => __( 'Credit Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-tx-amount.credit',
			]
		);

		// Debit Amount
		$this->add_control(
			'tx_debit_heading',
			[
				'label' => __( 'Debit Amount (Negative)', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'tx_debit_color',
			[
				'label' => __( 'Debit Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#c62828',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-tx-amount.debit' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'tx_debit_typography',
				'label' => __( 'Debit Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-tx-amount.debit',
			]
		);

		// Empty State
		$this->add_control(
			'tx_empty_heading',
			[
				'label' => __( 'Empty State', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'tx_empty_color',
			[
				'label' => __( 'Empty Text Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#888888',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-no-transactions' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'tx_empty_typography',
				'label' => __( 'Empty Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-no-transactions',
			]
		);

		$this->end_controls_section();

		// ========================================
		// STYLE TAB - Limits Text
		// ========================================

		$this->start_controls_section(
			'limits_style_section',
			[
				'label' => __( 'Limits Text', 'voxel-payment-gateways' ),
				'tab' => \Elementor\Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'limits_color',
			[
				'label' => __( 'Color', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::COLOR,
				'default' => '#888888',
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-limits' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'limits_typography',
				'label' => __( 'Typography', 'voxel-payment-gateways' ),
				'selector' => '{{WRAPPER}} .voxel-wallet-widget .wallet-limits',
			]
		);

		$this->add_responsive_control(
			'limits_margin',
			[
				'label' => __( 'Margin', 'voxel-payment-gateways' ),
				'type' => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em' ],
				'selectors' => [
					'{{WRAPPER}} .voxel-wallet-widget .wallet-limits' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
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

		// Check for deposit result message
		$deposit_status = isset( $_GET['wallet_deposit'] ) ? sanitize_text_field( $_GET['wallet_deposit'] ) : null;
		$deposit_message = isset( $_GET['wallet_message'] ) ? sanitize_text_field( urldecode( $_GET['wallet_message'] ) ) : null;

		?>
		<div class="voxel-wallet-widget" id="<?php echo esc_attr( $widget_id ); ?>">
			<style>
				/* Default styles - can be overridden by Elementor controls */
				.voxel-wallet-widget {
					font-family: inherit;
					color: #333333;
				}
				.voxel-wallet-widget .wallet-header {
					margin-bottom: 24px;
				}
				.voxel-wallet-widget .wallet-title {
					font-size: 24px;
					font-weight: 600;
					margin: 0 0 8px 0;
					color: #333333;
				}
				.voxel-wallet-widget .wallet-description {
					margin: 0;
					color: #666666;
					font-size: 14px;
				}
				.voxel-wallet-widget .wallet-balance-card {
					background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
					color: #ffffff;
					padding: 24px;
					border-radius: 12px;
					margin-bottom: 24px;
				}
				.voxel-wallet-widget .wallet-balance-label {
					font-size: 14px;
					margin-bottom: 4px;
					opacity: 0.9;
				}
				.voxel-wallet-widget .wallet-balance-amount {
					font-size: 36px;
					font-weight: 700;
				}
				.voxel-wallet-widget .wallet-add-funds {
					background: #ffffff;
					border: 1px solid #e0e0e0;
					border-radius: 12px;
					padding: 24px;
					margin-bottom: 24px;
				}
				.voxel-wallet-widget .wallet-add-funds h3 {
					font-size: 18px;
					font-weight: 600;
					margin: 0 0 16px 0;
					color: #333333;
				}
				.voxel-wallet-widget .wallet-preset-amounts {
					display: flex;
					flex-wrap: wrap;
					gap: 8px;
					margin-bottom: 16px;
				}
				.voxel-wallet-widget .wallet-preset-btn {
					padding: 8px 16px;
					border: 2px solid #4caf50;
					background: transparent;
					color: #4caf50;
					border-radius: 6px;
					cursor: pointer;
					font-weight: 500;
					font-size: 14px;
					transition: all 0.2s ease;
				}
				.voxel-wallet-widget .wallet-preset-btn:hover,
				.voxel-wallet-widget .wallet-preset-btn.active {
					background: #4caf50;
					color: #ffffff;
				}
				.voxel-wallet-widget .wallet-amount-input-group {
					display: flex;
					gap: 12px;
					align-items: stretch;
				}
				.voxel-wallet-widget .wallet-amount-wrapper {
					flex: 1;
					position: relative;
				}
				.voxel-wallet-widget .wallet-currency-prefix {
					position: absolute;
					left: 12px;
					top: 50%;
					transform: translateY(-50%);
					font-weight: 600;
					color: #666666;
				}
				.voxel-wallet-widget .wallet-amount-input {
					width: 100%;
					padding: 12px 12px 12px 48px;
					border: 1px solid #dddddd;
					border-radius: 6px;
					font-size: 16px;
					box-sizing: border-box;
					background: #ffffff;
					color: #333333;
				}
				.voxel-wallet-widget .wallet-amount-input:focus {
					outline: none;
					border-color: #4caf50;
				}
				.voxel-wallet-widget .wallet-submit-btn {
					padding: 12px 24px;
					background: #4caf50;
					color: #ffffff;
					border: none;
					border-radius: 6px;
					cursor: pointer;
					font-weight: 600;
					font-size: 14px;
					transition: all 0.2s ease;
					white-space: nowrap;
				}
				.voxel-wallet-widget .wallet-submit-btn:hover {
					background: #388e3c;
				}
				.voxel-wallet-widget .wallet-submit-btn:disabled {
					opacity: 0.5;
					cursor: not-allowed;
				}
				.voxel-wallet-widget .wallet-limits {
					font-size: 12px;
					color: #888888;
					margin-top: 8px;
				}
				.voxel-wallet-widget .wallet-message {
					padding: 12px;
					border-radius: 6px;
					margin-bottom: 16px;
					font-size: 14px;
				}
				.voxel-wallet-widget .wallet-message.success {
					background: #e8f5e9;
					color: #2e7d32;
				}
				.voxel-wallet-widget .wallet-message.error {
					background: #ffebee;
					color: #c62828;
				}
				.voxel-wallet-widget .wallet-history {
					background: #ffffff;
					border: 1px solid #e0e0e0;
					border-radius: 12px;
					overflow: hidden;
				}
				.voxel-wallet-widget .wallet-history > h3 {
					font-size: 18px;
					font-weight: 600;
					margin: 0;
					padding: 16px 20px;
					color: #333333;
					border-bottom: 1px solid #e0e0e0;
				}
				.voxel-wallet-widget .wallet-transactions {
					max-height: 400px;
					overflow-y: auto;
				}
				.voxel-wallet-widget .wallet-transaction {
					display: flex;
					justify-content: space-between;
					align-items: center;
					padding: 14px 20px;
					border-bottom: 1px solid #f0f0f0;
				}
				.voxel-wallet-widget .wallet-transaction:last-child {
					border-bottom: none;
				}
				.voxel-wallet-widget .wallet-tx-info {
					display: flex;
					flex-direction: column;
					gap: 2px;
				}
				.voxel-wallet-widget .wallet-tx-type {
					font-weight: 500;
					font-size: 14px;
					color: #333333;
				}
				.voxel-wallet-widget .wallet-tx-date {
					font-size: 12px;
					color: #888888;
				}
				.voxel-wallet-widget .wallet-tx-amount {
					font-weight: 600;
					font-size: 14px;
				}
				.voxel-wallet-widget .wallet-tx-amount.credit {
					color: #2e7d32;
				}
				.voxel-wallet-widget .wallet-tx-amount.debit {
					color: #c62828;
				}
				.voxel-wallet-widget .wallet-no-transactions {
					padding: 24px;
					text-align: center;
					color: #888888;
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
				<div class="wallet-balance-label"><?php echo esc_html( $settings['label_available_balance'] ?: __( 'Available Balance', 'voxel-payment-gateways' ) ); ?></div>
				<div class="wallet-balance-amount" id="<?php echo esc_attr( $widget_id ); ?>-balance">
					<?php echo esc_html( $balance_formatted ); ?>
				</div>
			</div>

			<div class="wallet-add-funds">
				<h3><?php echo esc_html( $settings['label_add_funds_title'] ?: __( 'Add Funds', 'voxel-payment-gateways' ) ); ?></h3>

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
						<?php
						$min_label = $settings['label_min'] ?: __( 'Min', 'voxel-payment-gateways' );
						$max_label = $settings['label_max'] ?: __( 'Max', 'voxel-payment-gateways' );
						printf(
							'%1$s: %2$s | %3$s: %4$s',
							esc_html( $min_label ),
							esc_html( \VoxelPayPal\Wallet_Client::format_amount( $min_deposit, $currency ) ),
							esc_html( $max_label ),
							esc_html( \VoxelPayPal\Wallet_Client::format_amount( $max_deposit, $currency ) )
						); ?>
					</div>
				</form>
			</div>

			<?php if ( $settings['show_history'] === 'yes' ) : ?>
				<div class="wallet-history">
					<h3><?php echo esc_html( $settings['label_history_title'] ?: __( 'Transaction History', 'voxel-payment-gateways' ) ); ?></h3>
					<div class="wallet-transactions" id="<?php echo esc_attr( $widget_id ); ?>-transactions">
						<?php if ( empty( $transactions ) ) : ?>
							<div class="wallet-no-transactions">
								<?php echo esc_html( $settings['label_no_transactions'] ?: __( 'No transactions yet', 'voxel-payment-gateways' ) ); ?>
							</div>
						<?php else : ?>
							<?php foreach ( $transactions as $tx ) : ?>
								<div class="wallet-transaction">
									<div class="wallet-tx-info">
										<span class="wallet-tx-type">
											<?php
											$type_labels = [
												'deposit' => $settings['label_deposit'] ?: __( 'Deposit', 'voxel-payment-gateways' ),
												'purchase' => $settings['label_purchase'] ?: __( 'Purchase', 'voxel-payment-gateways' ),
												'refund' => $settings['label_refund'] ?: __( 'Refund', 'voxel-payment-gateways' ),
												'adjustment' => $settings['label_adjustment'] ?: __( 'Adjustment', 'voxel-payment-gateways' ),
											];
											$type_label = $type_labels[ $tx['type'] ] ?? ucfirst( $tx['type'] );

											// Add order reference for purchases
											if ( $tx['type'] === 'purchase' && $tx['reference_type'] === 'order' && $tx['reference_id'] ) {
												$type_label .= ' #' . $tx['reference_id'];
											}
											echo esc_html( $type_label );
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
						showMessage('<?php echo esc_js( $settings['label_invalid_amount'] ?: __( 'Please enter a valid amount', 'voxel-payment-gateways' ) ); ?>', 'error');
						return;
					}

					submitBtn.disabled = true;
					submitBtn.textContent = '<?php echo esc_js( $settings['label_processing'] ?: __( 'Processing...', 'voxel-payment-gateways' ) ); ?>';
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
							showMessage(data.message || '<?php echo esc_js( $settings['label_deposit_failed'] ?: __( 'Failed to initiate deposit', 'voxel-payment-gateways' ) ); ?>', 'error');
							submitBtn.disabled = false;
							submitBtn.textContent = '<?php echo esc_js( $settings['button_text'] ?: __( 'Add Funds', 'voxel-payment-gateways' ) ); ?>';
						}
					} catch (error) {
						showMessage('<?php echo esc_js( $settings['label_error_occurred'] ?: __( 'An error occurred', 'voxel-payment-gateways' ) ); ?>', 'error');
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

	protected function content_template(): void {
		?>
		<div class="voxel-wallet-widget">
			<# if ( settings.title ) { #>
				<div class="wallet-header">
					<h2 class="wallet-title">{{{ settings.title }}}</h2>
					<# if ( settings.description ) { #>
						<p class="wallet-description">{{{ settings.description }}}</p>
					<# } #>
				</div>
			<# } #>

			<div class="wallet-balance-card">
				<div class="wallet-balance-label">{{{ settings.label_available_balance || '<?php _e( 'Available Balance', 'voxel-payment-gateways' ); ?>' }}}</div>
				<div class="wallet-balance-amount">$0.00</div>
			</div>

			<div class="wallet-add-funds">
				<h3>{{{ settings.label_add_funds_title || '<?php _e( 'Add Funds', 'voxel-payment-gateways' ); ?>' }}}</h3>

				<# if ( settings.show_preset_amounts === 'yes' ) { #>
					<div class="wallet-preset-amounts">
						<button class="wallet-preset-btn">$10</button>
						<button class="wallet-preset-btn">$25</button>
						<button class="wallet-preset-btn">$50</button>
						<button class="wallet-preset-btn">$100</button>
					</div>
				<# } #>

				<div class="wallet-amount-input-group">
					<div class="wallet-amount-wrapper">
						<span class="wallet-currency-prefix">USD</span>
						<input type="number" placeholder="0.00" class="wallet-amount-input" />
					</div>
					<button class="wallet-submit-btn">
						{{{ settings.button_text || '<?php _e( 'Add Funds', 'voxel-payment-gateways' ); ?>' }}}
					</button>
				</div>
				<div class="wallet-limits">
					{{{ settings.label_min || '<?php _e( 'Min', 'voxel-payment-gateways' ); ?>' }}}: $1.00 | {{{ settings.label_max || '<?php _e( 'Max', 'voxel-payment-gateways' ); ?>' }}}: $10,000.00
				</div>
			</div>

			<# if ( settings.show_history === 'yes' ) { #>
				<div class="wallet-history">
					<h3>{{{ settings.label_history_title || '<?php _e( 'Transaction History', 'voxel-payment-gateways' ); ?>' }}}</h3>
					<div class="wallet-transactions">
						<div class="wallet-no-transactions">
							{{{ settings.label_no_transactions || '<?php _e( 'No transactions yet', 'voxel-payment-gateways' ); ?>' }}}
						</div>
					</div>
				</div>
			<# } #>
		</div>
		<?php
	}
}
