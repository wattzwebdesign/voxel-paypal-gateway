<?php

namespace VoxelPayPal\Controllers;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Stripe Controller
 * Adds enhancements to Voxel's built-in Stripe payment gateway
 */
class Stripe_Controller extends \Voxel\Controllers\Base_Controller {

	const OPTION_KEY = 'voxel_stripe_skip_zero_checkout';

	protected function hooks() {
		$this->on( 'voxel/stripe_payments/zero_amount/skip_checkout', '@skip_zero_amount_checkout' );
		$this->on( 'admin_footer', '@inject_stripe_enhancement_ui' );
		$this->on( 'admin_post_voxel_save_payment_settings', '@save_stripe_enhancements', 5 );
	}

	/**
	 * Filter to skip checkout when order amount is zero
	 *
	 * @param bool $skip Whether to skip checkout
	 * @return bool
	 */
	protected function skip_zero_amount_checkout( $skip ) {
		return (bool) get_option( self::OPTION_KEY, false );
	}

	/**
	 * Save stripe enhancements when Voxel saves payment settings
	 */
	protected function save_stripe_enhancements() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if our setting was submitted
		if ( isset( $_POST['voxel_stripe_skip_zero_checkout'] ) ) {
			$skip_zero = $_POST['voxel_stripe_skip_zero_checkout'] === '1';
			update_option( self::OPTION_KEY, $skip_zero );
		}
	}

	/**
	 * Inject enhancement UI into Stripe settings panel
	 */
	protected function inject_stripe_enhancement_ui() {
		// Only run on Voxel payments page with Stripe configuration
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'voxel-payments' ) {
			return;
		}

		$tab = $_GET['tab'] ?? '';
		if ( $tab !== 'configure.stripe' ) {
			return;
		}

		$is_enabled = get_option( self::OPTION_KEY, false );
		?>
		<style>
		/* Enhancement toggle styling */
		.stripe-enhancements-section {
			margin-top: 25px;
			padding-top: 20px;
			border-top: 1px solid rgba(255,255,255,0.1);
		}
		.stripe-enhancements-section .ts-group-head {
			margin-bottom: 15px;
		}
		.stripe-enhancements-info {
			background: linear-gradient(135deg, #f0f9ff 0%, #e8f4fd 100%);
			border: 1px solid #bde0fe;
			border-radius: 8px;
			padding: 12px 16px;
			margin-bottom: 15px;
			font-size: 13px;
			color: #475569;
			line-height: 1.5;
		}
		</style>
		<script>
		(function() {
			let injected = false;
			const isEnabled = <?php echo $is_enabled ? 'true' : 'false'; ?>;

			function isPaymentsTabActive() {
				// Check if the Payments tab is active by looking at the inner-tabs
				const activeTab = document.querySelector('#vx-payment-config .inner-tabs li.current-item a');
				if (activeTab && activeTab.textContent.trim() === 'Payments') {
					return true;
				}
				return false;
			}

			function injectEnhancementToggle() {
				// Check if we already injected
				if (document.querySelector('[data-stripe-enhancements]')) {
					// If injected but not on Payments tab, remove it
					if (!isPaymentsTabActive()) {
						const existing = document.querySelector('[data-stripe-enhancements]');
						if (existing) existing.remove();
						injected = false;
					}
					return true;
				}

				// Only inject on the Payments tab
				if (!isPaymentsTabActive()) {
					injected = false;
					return false;
				}

				// Find all .ts-group elements
				const allGroups = document.querySelectorAll('#vx-payment-config .ts-group');
				if (allGroups.length === 0) return false;

				const lastGroup = allGroups[allGroups.length - 1];

				// Create our enhancement section as a new group
				const enhancementGroup = document.createElement('div');
				enhancementGroup.className = 'ts-group stripe-enhancements-section';
				enhancementGroup.setAttribute('data-stripe-enhancements', 'true');
				enhancementGroup.innerHTML = `
					<div class="ts-group-head">
						<h3>Zero Amount Orders (VT)</h3>
					</div>
					<div class="stripe-enhancements-info">
						When an order total is $0 (from discounts or free items), customers normally still go through Stripe checkout. Enable this to skip checkout and complete the order immediately.
					</div>
					<div class="x-row">
						<div class="ts-form-group x-col-12 switch-slider">
							<label for="stripe-skip-zero-checkout">Skip checkout for zero amount orders</label>
							<div class="onoffswitch">
								<input
									type="checkbox"
									class="onoffswitch-checkbox"
									id="stripe-skip-zero-checkout"
									tabindex="0"
									${isEnabled ? 'checked' : ''}
								>
								<label class="onoffswitch-label" for="stripe-skip-zero-checkout"></label>
							</div>
							<input type="hidden" name="voxel_stripe_skip_zero_checkout" id="stripe-skip-zero-checkout-hidden" value="${isEnabled ? '1' : '0'}">
						</div>
					</div>
				`;

				// Insert after the last group
				lastGroup.parentNode.insertBefore(enhancementGroup, lastGroup.nextSibling);
				injected = true;

				// Add change listener to update hidden field
				const checkbox = document.getElementById('stripe-skip-zero-checkout');
				const hiddenField = document.getElementById('stripe-skip-zero-checkout-hidden');

				checkbox.addEventListener('change', function() {
					hiddenField.value = checkbox.checked ? '1' : '0';
				});

				return true;
			}

			// Poll for the General tab content to be ready
			let attempts = 0;
			const maxAttempts = 100;
			const pollInterval = setInterval(function() {
				attempts++;

				injectEnhancementToggle();

				if (attempts >= maxAttempts) {
					clearInterval(pollInterval);
				}
			}, 100);

			// Watch for tab changes within the Stripe settings
			const observer = new MutationObserver(function(mutations) {
				// Always try to inject/remove based on current tab
				injectEnhancementToggle();
			});

			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
		})();
		</script>
		<?php
	}
}
