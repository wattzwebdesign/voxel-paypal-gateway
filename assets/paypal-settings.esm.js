/**
 * PayPal Settings Component
 *
 * Note: Using Select fields instead of Switchers for marketplace settings
 * to ensure string values ('0' or '1') are used throughout, matching the schema.
 */

export default {
	props: {
		provider: Object,
		settings: Object,
		data: Object,
	},

	data() {
		return {
			// Component state
		};
	},

	mounted() {
		// Initialize marketplace settings with defaults if not present
		if (!this.settings.marketplace) {
			this.$set(this.settings, 'marketplace', {
				enabled: '0',
				fee_type: 'percentage',
				fee_value: '10',
				auto_payout: '1',
				payout_delay_days: '0',
				shipping_responsibility: 'vendor',
			});
		}

		// Ensure all values are strings (in case loaded from old data)
		if (this.settings.marketplace) {
			// Normalize enabled
			if (this.settings.marketplace.enabled !== '0' && this.settings.marketplace.enabled !== '1') {
				this.settings.marketplace.enabled = this.settings.marketplace.enabled ? '1' : '0';
			}

			// Normalize auto_payout
			if (this.settings.marketplace.auto_payout !== '0' && this.settings.marketplace.auto_payout !== '1') {
				this.settings.marketplace.auto_payout = this.settings.marketplace.auto_payout ? '1' : '0';
			}

			// Ensure numeric fields are strings
			if (this.settings.marketplace.fee_value && typeof this.settings.marketplace.fee_value !== 'string') {
				this.settings.marketplace.fee_value = String(this.settings.marketplace.fee_value);
			}

			if (this.settings.marketplace.payout_delay_days && typeof this.settings.marketplace.payout_delay_days !== 'string') {
				this.settings.marketplace.payout_delay_days = String(this.settings.marketplace.payout_delay_days);
			}
		}

		// Debug logging
		console.log('PayPal Settings initialized:', this.settings.marketplace);
	},

	methods: {
		// Add any custom methods here if needed
	},
};
