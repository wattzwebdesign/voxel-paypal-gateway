/**
 * PayPal Settings Component
 */

export default {
	props: {
		provider: Object,
		settings: Object,
		data: Object,
	},

	data() {
		return {
			// Add any component state here if needed
		};
	},

	mounted() {
		// Debug logging
		console.log('PayPal Settings mounted');
		console.log('Settings:', this.settings);
		console.log('Provider:', this.provider);
	},

	methods: {
		// Add any methods here if needed
	},
};
