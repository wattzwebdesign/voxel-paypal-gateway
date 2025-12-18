/**
 * Offline Payment Settings Component
 * Vue component for Voxel admin settings
 */
export default {
	props: {
		provider: Object,
		settings: Object,
		data: Object,
	},

	data() {
		return {};
	},

	methods: {
		editInstructions() {
			if ( typeof Voxel_Dynamic === 'undefined' ) {
				console.error( 'Voxel_Dynamic is not available' );
				return;
			}

			Voxel_Dynamic.edit( this.settings.instructions || '', {
				groups: this.data.dynamic_groups || {},
				onSave: ( content ) => {
					this.settings.instructions = content;
				},
			} );
		},
	},
};
