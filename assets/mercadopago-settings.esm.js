export default {
	props: {
		provider: Object,
		settings: Object,
		data: Object,
	},

	data() {
		return {};
	},

	mounted() {
		// Initialize mode and currency if not present
		if (!this.settings.mode) {
			this.$set(this.settings, 'mode', 'sandbox');
		}

		if (!this.settings.currency) {
			this.$set(this.settings, 'currency', 'ARS');
		}

		// Initialize default settings if not present
		if (!this.settings.payments) {
			this.$set(this.settings, 'payments', {
				order_approval: 'automatic',
				brand_name: '',
			});
		}

		if (!this.settings.sandbox) {
			this.$set(this.settings, 'sandbox', {
				access_token: '',
				public_key: '',
				application_id: '',
				client_secret: '',
				webhook_secret: '',
			});
		}

		if (!this.settings.live) {
			this.$set(this.settings, 'live', {
				access_token: '',
				public_key: '',
				application_id: '',
				client_secret: '',
				webhook_secret: '',
			});
		}

		if (!this.settings.marketplace) {
			this.$set(this.settings, 'marketplace', {
				enabled: '0',
				fee_type: 'percentage',
				fee_value: '10',
			});
		}

		// Ensure marketplace.enabled is a string
		if (this.settings.marketplace && this.settings.marketplace.enabled !== '0' && this.settings.marketplace.enabled !== '1') {
			this.settings.marketplace.enabled = this.settings.marketplace.enabled ? '1' : '0';
		}

		console.log('Mercado Pago Settings initialized:', this.settings);
	},

	methods: {},
};
