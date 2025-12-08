<?php

namespace VoxelPayPal\Controllers;

use VoxelPayPal\Offline_Payment_Service;
use VoxelPayPal\Payment_Methods\Offline_Payment;

if ( ! defined('ABSPATH') ) {
	exit;
}

/**
 * Offline Payment Controller
 * Registers offline payment service and payment methods with Voxel
 */
class Offline_Controller extends \Voxel\Controllers\Base_Controller {

	protected function hooks() {
		// Register payment methods only - service registration is handled in main plugin file
		$this->filter( 'voxel/product-types/payment-methods', '@register_payment_methods' );
	}

	/**
	 * Register Offline payment methods
	 */
	protected function register_payment_methods( $payment_methods ) {
		$payment_methods['offline_payment'] = Offline_Payment::class;
		return $payment_methods;
	}
}
