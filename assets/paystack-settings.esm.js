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
		// Initialize default settings if not present
		if (!this.settings.payments) {
			this.$set(this.settings, 'payments', {
				order_approval: 'automatic',
				brand_name: '',
			});
		}

		if (!this.settings.sandbox) {
			this.$set(this.settings, 'sandbox', {
				secret_key: '',
				public_key: '',
				webhook_secret: '',
			});
		}

		if (!this.settings.live) {
			this.$set(this.settings, 'live', {
				secret_key: '',
				public_key: '',
				webhook_secret: '',
			});
		}

		if (!this.settings.marketplace) {
			this.$set(this.settings, 'marketplace', {
				enabled: '0',
				fee_type: 'percentage',
				fee_value: '10',
				fee_bearer: 'account',
			});
		}

		console.log('Paystack Settings initialized:', this.settings);
	},

	methods: {},
};
