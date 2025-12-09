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
				enable_card: '1',
				enable_digital_wallets: '1',
				enable_ach: '0',
				enable_cash_app: '0',
				enable_gift_cards: '0',
			});
		}

		if (!this.settings.sandbox) {
			this.$set(this.settings, 'sandbox', {
				application_id: '',
				access_token: '',
				location_id: '',
				webhook_signature_key: '',
			});
		}

		if (!this.settings.live) {
			this.$set(this.settings, 'live', {
				application_id: '',
				access_token: '',
				location_id: '',
				webhook_signature_key: '',
			});
		}

		console.log('Square Settings initialized:', this.settings);
	},

	methods: {},
};
