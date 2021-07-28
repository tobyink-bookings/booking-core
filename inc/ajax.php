<?php

function wp_ajax_pay_booking () {
	if ( ! class_exists( '\Stripe\StripeClient' ) ) {
		require_once( __DIR__ . '/../vendor/stripe/stripe-php/init.php' );
	}

	$id = (int) $_POST['id'];
	$tx =       $_POST['tx'];
	$dt = gmdate( 'Y-m-d H:i:s' );

	$stripe = new \Stripe\StripeClient( get_option( 'booking_stripe_secret' ) );
	$pi     = $stripe->paymentIntents->retrieve( $tx, [] );

	if ( $pi->status == 'succeeded' ) {
		// yay
	}
	else {
		wp_send_json( [ 'status' => 'error', 'pi' => $pi ] );
		wp_die();
	}

	$status = get_field( 'status', $id );

	if ( in_array( $status, explode( ',', get_option( 'booking_accepted_status', 'Accepted' ) ) ) ) {
		update_post_meta( $id, 'payment_datetime', $dt );
		update_post_meta( $id, 'payment_details', sprintf( "Paid: %s\nTransaction: %s\n", $dt, $tx ) );
		update_post_meta( $id, 'status', get_option( 'booking_payment_status', 'Paid' ) );

		$fields = get_fields( $id );
		$fields['id'] = sprintf( '%08d', $id );
		$fields['status'] = get_option( 'booking_payment_status', 'Paid' );
		booking_send_notifications( $id, [], $fields );

		wp_send_json( [ 'status' => 'ok' ] );
		wp_die();
	}

	wp_send_json( [ 'status' => 'error' ] );
	wp_die();
}

add_action( 'init', function () {
	add_action( 'wp_ajax_nopriv_pay_booking', 'wp_ajax_pay_booking' );
	add_action( 'wp_ajax_pay_booking', 'wp_ajax_pay_booking' );
} );
