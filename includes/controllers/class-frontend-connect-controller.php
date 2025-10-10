<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\PayPal_Connect_Client;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Frontend Connect Controller
 * Handles vendor-facing marketplace features
 */
class Frontend_Connect_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		$this->on( 'voxel_ajax_paypal.vendor.save_paypal_email', '@save_vendor_paypal_email' );
		$this->on( 'voxel_ajax_paypal.vendor.get_paypal_email', '@get_vendor_paypal_email' );
		$this->on( 'voxel_ajax_paypal.vendor.get_earnings', '@get_vendor_earnings' );
		$this->on( 'voxel_ajax_paypal.vendor.get_payout_history', '@get_payout_history' );

		// Add to WordPress user profile
		$this->on( 'show_user_profile', '@render_paypal_email_field' );
		$this->on( 'edit_user_profile', '@render_paypal_email_field' );
		$this->on( 'personal_options_update', '@save_paypal_email_from_profile' );
		$this->on( 'edit_user_profile_update', '@save_paypal_email_from_profile' );

		// Add to Voxel dashboard if hook exists
		$this->on( 'voxel/user-profile/render-fields', '@render_paypal_email_field' );
	}

	/**
	 * Save vendor PayPal email
	 */
	protected function save_vendor_paypal_email() {
		try {
			// Log that endpoint was called
			error_log( 'PayPal: save_vendor_paypal_email endpoint called' );
			error_log( 'PayPal: Request method: ' . $_SERVER['REQUEST_METHOD'] );
			error_log( 'PayPal: Content-Type: ' . ( $_SERVER['CONTENT_TYPE'] ?? 'not set' ) );

			// Check if marketplace is enabled (same pattern as widget)
			$marketplace_enabled_raw = \Voxel\get( 'payments.paypal.marketplace.enabled', '0' );
			$marketplace_enabled = (bool) $marketplace_enabled_raw;
			error_log( 'PayPal: Marketplace enabled raw value: ' . var_export( $marketplace_enabled_raw, true ) );
			error_log( 'PayPal: Marketplace enabled (bool): ' . var_export( $marketplace_enabled, true ) );

			if ( ! $marketplace_enabled ) {
				error_log( 'PayPal: Marketplace mode is not enabled' );
				throw new \Exception( 'Marketplace mode is not enabled. Please enable it in PayPal settings.' );
			}

			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				error_log( 'PayPal: User not logged in' );
				throw new \Exception( 'You must be logged in to save your PayPal email.' );
			}

			error_log( 'PayPal: User ID: ' . $user_id );

			$data = json_decode( file_get_contents( 'php://input' ), true );
			error_log( 'PayPal: Raw data: ' . print_r( $data, true ) );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				error_log( 'PayPal: JSON decode error: ' . json_last_error_msg() );
				throw new \Exception( 'Invalid request data format.' );
			}

			$email = sanitize_email( $data['email'] ?? '' );
			error_log( 'PayPal: Email to save: ' . $email );

			if ( empty( $email ) ) {
				error_log( 'PayPal: Email is empty' );
				throw new \Exception( 'Please enter a PayPal email address.' );
			}

			if ( ! is_email( $email ) ) {
				error_log( 'PayPal: Invalid email format' );
				throw new \Exception( 'Please enter a valid email address.' );
			}

			$success = PayPal_Connect_Client::set_vendor_paypal_email( $user_id, $email );
			error_log( 'PayPal: Save result: ' . ( $success ? 'success' : 'failed' ) );
			error_log( 'PayPal: Save result type: ' . gettype( $success ) );

			if ( ! $success ) {
				error_log( 'PayPal: update_user_meta returned false' );
				throw new \Exception( 'Failed to save PayPal email to database. Please try again or contact support.' );
			}

			// Verify the email was saved
			$saved_email = PayPal_Connect_Client::get_vendor_paypal_email( $user_id );
			error_log( 'PayPal: Verified saved email: ' . $saved_email );

			wp_send_json( [
				'success' => true,
				'message' => 'PayPal email saved successfully',
				'email' => $saved_email,
			] );

		} catch ( \Exception $e ) {
			error_log( 'PayPal: Error saving email: ' . $e->getMessage() );
			error_log( 'PayPal: Error trace: ' . $e->getTraceAsString() );
			wp_send_json( [
				'success' => false,
				'error' => $e->getMessage(),
			], 400 );
		}
	}

	/**
	 * Get vendor PayPal email
	 */
	protected function get_vendor_paypal_email() {
		try {
			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				throw new \Exception( 'User not logged in' );
			}

			$email = PayPal_Connect_Client::get_vendor_paypal_email( $user_id );

			wp_send_json( [
				'success' => true,
				'email' => $email,
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'error' => $e->getMessage(),
			], 400 );
		}
	}

	/**
	 * Get vendor earnings summary
	 */
	protected function get_vendor_earnings() {
		try {
			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				throw new \Exception( 'User not logged in' );
			}

			// Query orders where vendor is the product author
			$orders = \Voxel\Product_Types\Orders\Order::query( [
				'vendor_id' => $user_id,
				'status' => \Voxel\ORDER_COMPLETED,
				'limit' => 1000,
			] );

			$total_earnings = 0;
			$total_fees = 0;
			$order_count = 0;

			foreach ( $orders as $order ) {
				$vendor_earnings = $order->get_details( 'marketplace.vendor_earnings' );
				$platform_fee = $order->get_details( 'marketplace.platform_fee' );

				if ( $vendor_earnings ) {
					$total_earnings += floatval( $vendor_earnings );
					$total_fees += floatval( $platform_fee ?? 0 );
					$order_count++;
				}
			}

			// Query pending payouts
			$pending_payouts = $this->get_pending_payout_count( $user_id );

			wp_send_json( [
				'success' => true,
				'earnings' => [
					'total' => round( $total_earnings, 2 ),
					'total_fees' => round( $total_fees, 2 ),
					'order_count' => $order_count,
					'pending_payouts' => $pending_payouts,
				],
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'error' => $e->getMessage(),
			], 400 );
		}
	}

	/**
	 * Get vendor payout history
	 */
	protected function get_payout_history() {
		try {
			$user_id = get_current_user_id();

			if ( ! $user_id ) {
				throw new \Exception( 'User not logged in' );
			}

			$limit = intval( $_GET['limit'] ?? 20 );
			$offset = intval( $_GET['offset'] ?? 0 );

			// Query vendor sub-orders (payouts)
			$args = [
				'post_type' => 'voxel_vendor_order',
				'author' => $user_id,
				'posts_per_page' => $limit,
				'offset' => $offset,
				'orderby' => 'date',
				'order' => 'DESC',
			];

			$query = new \WP_Query( $args );
			$payouts = [];

			foreach ( $query->posts as $post ) {
				$parent_order_id = get_post_meta( $post->ID, 'parent_order_id', true );
				$vendor_amount = get_post_meta( $post->ID, 'vendor_amount', true );
				$payout_status = get_post_meta( $post->ID, 'payout_status', true );
				$payout_item_id = get_post_meta( $post->ID, 'payout_item_id', true );
				$created_at = get_post_meta( $post->ID, 'created_at', true );
				$payout_updated_at = get_post_meta( $post->ID, 'payout_updated_at', true );

				$payouts[] = [
					'id' => $post->ID,
					'parent_order_id' => $parent_order_id,
					'amount' => floatval( $vendor_amount ),
					'status' => $payout_status,
					'payout_item_id' => $payout_item_id,
					'created_at' => $created_at,
					'updated_at' => $payout_updated_at,
				];
			}

			wp_send_json( [
				'success' => true,
				'payouts' => $payouts,
				'total' => $query->found_posts,
			] );

		} catch ( \Exception $e ) {
			wp_send_json( [
				'success' => false,
				'error' => $e->getMessage(),
			], 400 );
		}
	}

	/**
	 * Render vendor PayPal email field on user profile
	 */
	protected function render_paypal_email_field( $user ) {
		// Get user ID from $user object or current user
		$user_id = is_object( $user ) ? $user->ID : get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		// Check if marketplace is enabled
		$marketplace_enabled = (bool) \Voxel\get( 'payments.paypal.marketplace.enabled', 0 );

		if ( ! $marketplace_enabled ) {
			return;
		}

		// Check if user is a vendor (has published posts of product post types)
		$post_count = count_user_posts( $user_id );

		// Output field HTML
		$current_email = PayPal_Connect_Client::get_vendor_paypal_email( $user_id );
		?>
		<h2><?php _e( 'PayPal Marketplace Settings', 'voxel-paypal-gateway' ); ?></h2>
		<table class="form-table">
			<tr>
				<th>
					<label for="vendor_paypal_email">
						<?php _e( 'PayPal Email for Payouts', 'voxel-paypal-gateway' ); ?>
					</label>
				</th>
				<td>
					<input
						type="email"
						id="vendor_paypal_email"
						name="vendor_paypal_email"
						value="<?php echo esc_attr( $current_email ?? '' ); ?>"
						class="regular-text"
						placeholder="vendor@example.com"
					/>
					<p class="description">
						<?php _e( 'Enter your PayPal email address to receive payments from your sales. If not set, your account email will be used.', 'voxel-paypal-gateway' ); ?>
					</p>
					<?php if ( $post_count > 0 ): ?>
						<p class="description">
							<strong><?php printf( _n( 'You have %d listing.', 'You have %d listings.', $post_count, 'voxel-paypal-gateway' ), $post_count ); ?></strong>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Save PayPal email from WordPress user profile
	 */
	protected function save_paypal_email_from_profile( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if ( ! isset( $_POST['vendor_paypal_email'] ) ) {
			return;
		}

		$email = sanitize_email( $_POST['vendor_paypal_email'] );

		if ( empty( $email ) ) {
			// Allow clearing the email
			delete_user_meta( $user_id, 'paypal_email' );
			return;
		}

		if ( ! is_email( $email ) ) {
			return;
		}

		PayPal_Connect_Client::set_vendor_paypal_email( $user_id, $email );
	}

	/**
	 * Get count of pending payouts for vendor
	 */
	protected function get_pending_payout_count( int $vendor_id ): int {
		$args = [
			'post_type' => 'voxel_vendor_order',
			'author' => $vendor_id,
			'posts_per_page' => -1,
			'meta_query' => [
				[
					'key' => 'payout_status',
					'value' => [ 'pending', 'processing' ],
					'compare' => 'IN',
				],
			],
		];

		$query = new \WP_Query( $args );
		return $query->found_posts;
	}
}
