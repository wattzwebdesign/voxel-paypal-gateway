/**
 * PayPal Marketplace Settings Vue Component
 */
export default {
	data() {
		return {
			payoutLogs: [],
			manualPayout: {
				vendor_id: '',
				amount: '',
				currency: 'USD',
				note: ''
			}
		};
	},
	methods: {
		/**
		 * Load payout logs
		 */
		async loadPayoutLogs() {
			try {
				const response = await fetch(
					window.ajaxurl + '?action=paypal.admin.get_payout_logs&vx=1&limit=20',
					{
						method: 'GET',
						headers: {
							'Content-Type': 'application/json'
						}
					}
				);

				const data = await response.json();

				if (data.success) {
					this.payoutLogs = data.logs || [];
				} else {
					alert('Failed to load payout logs: ' + (data.error || 'Unknown error'));
				}
			} catch (error) {
				console.error('Error loading payout logs:', error);
				alert('Failed to load payout logs: ' + error.message);
			}
		},

		/**
		 * Create manual payout
		 */
		async createManualPayout() {
			if (!this.manualPayout.vendor_id || !this.manualPayout.amount) {
				alert('Please fill in all required fields');
				return;
			}

			if (this.manualPayout.amount < 1) {
				alert('Amount must be at least 1.00');
				return;
			}

			if (!confirm('Create payout of ' + this.manualPayout.amount + ' ' + this.manualPayout.currency + ' to vendor #' + this.manualPayout.vendor_id + '?')) {
				return;
			}

			try {
				const response = await fetch(
					window.ajaxurl + '?action=paypal.admin.manual_payout&vx=1',
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/json'
						},
						body: JSON.stringify(this.manualPayout)
					}
				);

				const data = await response.json();

				if (data.success) {
					alert('Payout created successfully! Batch ID: ' + (data.payout_batch_id || 'N/A'));

					// Reset form
					this.manualPayout = {
						vendor_id: '',
						amount: '',
						currency: 'USD',
						note: ''
					};

					// Reload logs
					this.loadPayoutLogs();
				} else {
					alert('Failed to create payout: ' + (data.error || 'Unknown error'));
				}
			} catch (error) {
				console.error('Error creating manual payout:', error);
				alert('Failed to create payout: ' + error.message);
			}
		}
	},
	mounted() {
		// Set defaults for marketplace config
		if (this.config.marketplace) {
			if (typeof this.config.marketplace.enabled === 'undefined') {
				this.$set(this.config.marketplace, 'enabled', 0);
			} else {
				// Convert numeric to boolean for checkbox binding
				this.config.marketplace.enabled = !!this.config.marketplace.enabled;
			}

			if (typeof this.config.marketplace.fee_type === 'undefined') {
				this.$set(this.config.marketplace, 'fee_type', 'percentage');
			}
			if (typeof this.config.marketplace.fee_value === 'undefined') {
				this.$set(this.config.marketplace, 'fee_value', 10);
			}

			if (typeof this.config.marketplace.auto_payout === 'undefined') {
				this.$set(this.config.marketplace, 'auto_payout', 1);
			} else {
				// Convert numeric to boolean for checkbox binding
				this.config.marketplace.auto_payout = !!this.config.marketplace.auto_payout;
			}

			if (typeof this.config.marketplace.payout_delay_days === 'undefined') {
				this.$set(this.config.marketplace, 'payout_delay_days', 0);
			}
			if (typeof this.config.marketplace.shipping_responsibility === 'undefined') {
				this.$set(this.config.marketplace, 'shipping_responsibility', 'vendor');
			}
		}
	}
};
