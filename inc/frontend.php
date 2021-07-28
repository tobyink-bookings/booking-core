<?php

add_action( 'init', function () {
	acf_register_form( [
		'id'               => 'new-booking',
		'post_id'          => 'new_post',
		'new_post'         => [ 'post_type' => 'booking', 'post_status' => 'publish' ],
		'post_title'       => false,
		'post_content'     => false,
		'uploader'         => 'basic',
		'return'           => home_url( get_option( 'booking_url_booking_thanks' ) ),
		'submit_value'     => 'Request booking',
		'field_groups'     => explode( ',', get_option( 'booking_fg_client' ) ),
	] );
} );

add_action( 'wp_head', function () {
	$content = get_the_content();
	if ( has_shortcode( $content, 'booking-booking' ) ) {

		acf_form_head();

		echo '<style>
		.acf-field { border: 0 !important }
		.acf-form-submit { padding: 15px 12px }
		ul.acf-radio-list li, ul.acf-checkbox-list li { font-size: inherit !important }
		@media (max-width: 767.98px) { .acf-field { width: 100% !important } }
		</style>';

		$bias = false;
		if ( $b = get_option( 'booking_places_bias' ) ) {
			$bias = json_decode( $b );
		}
		wp_enqueue_script( 'booking-booking', plugins_url( '../assets/booking-booking.js', __FILE__ ), [ 'jquery' ], false, true );
		wp_localize_script( 'booking-booking', 'BookingBookingOpts', [
			'placesApiKey' => get_option( 'booking_places_api_key' ),
			'placesBias'   => $bias,
		] );
	}
}, 1 );

add_shortcode( 'booking-booking',  function ( $atts, $content='') {
	ob_start();
	acf_form('new-booking');
	return ob_get_clean();
} );

add_shortcode( 'booking-payment', function ( $atts, $content='') {

	list( $id, $secret ) = explode( '-', $_GET['id'], 2 );
	$id = (int) $id;

	if ( $id < 1 ) {
		return '<p>Could not find booking.</p>';
	}

	$real_secret = get_field( 'secret', $id );

	if ( $real_secret !== $secret ) {
		return '<p>Could not find booking.</p>';
	}

	$status = get_field( 'status', $id );

	if ( ! in_array( $status, explode( ',', get_option( 'booking_accepted_status', 'Accepted' ) ) ) ) {
		return sprintf(
			'<p class="lead"><strong>Appointment ID:</strong> %08d.<br /><strong>Status:</strong> %s.</p>',
			$id,
			$status
		);
	}

	$fee = get_field( 'fee', $id );

	if ( ! class_exists( '\Stripe\StripeClient' ) ) {
		require_once( __DIR__ . '/../vendor/stripe/stripe-php/init.php' );
	}
	$html = '<script src="https://js.stripe.com/v3/"></script>';

	$stripe = new \Stripe\StripeClient( get_option( 'booking_stripe_secret' ) );
	$intent = $stripe->paymentIntents->create( [
		'amount' => (int) ( 100 * $fee ),
		'currency' => 'gbp',
		'payment_method_types' => [ 'card' ],
	] );

	$html .= sprintf(
		'<p class="lead"><strong>Appointment ID:</strong> %08d.<br /><strong>Appointment fee:</strong> &pound;%d.</p>',
		$id,
		$fee
	);

	$html .= '<form id="payment-form">';
	$html .= '<div class="form-group">';
	$html .= '<label>Card details</label>';
	$html .= '<div class="form-control"><div id="card-element"></div></div>';
	$html .= '</div>';
	$html .= '<div id="card-errors" role="alert"></div>';
	$html .= '<button class="btn btn-primary" id="payment-submit">Pay</button>';
	$html .= '</form>';

	$config = get_option( 'booking_stripe_config' );
	if ( ! $config ) {
		$config = '{ "fields": { "name": "{{$full_name}}", "email": "{{$email_address}}" } }';
	}
	$config = json_decode( $config, true );
	$billing_details = [];
	$data = get_fields( $id );
	foreach ( $config['billing_details'] as $key => $value ) {
		$billing_details[$key] = _booking_process_template( $value, $data );
	}

	$html .= '<script type="text/javascript">
	var clientSecret = "' . $intent->client_secret . '";
	var stripe = Stripe( "' . get_option( 'booking_stripe_key' ) . '" );
	var elements = stripe.elements();
	var card = elements.create( "card" );
	card.mount( "#card-element" );
	card.addEventListener( "change", ({error}) => {
		const displayError = document.getElementById( "card-errors" );
		if ( error ) {
			displayError.textContent = error.message;
		}
		else {
			displayError.textContent = "";
		}
	} );
	var form   = document.getElementById( "payment-form" );
	var button = document.getElementById( "payment-submit" );
	form.addEventListener( "submit", function ( ev ) {
		button.disabled = true;
		ev.preventDefault();
		stripe.confirmCardPayment( clientSecret, {
			payment_method: {
				card: card,
				billing_details: ' . json_encode( $billing_details ) . '
			}
		} ).then( function ( result ) {
			if (result.error) {
				window.alert( result.error.message );
				button.disabled = false;
			}
			else {
				if ( result.paymentIntent.status === "succeeded" ) {
					var tx = result.paymentIntent.id;
					jQuery.ajax( {
						type: "POST",
						url: "'.admin_url('admin-ajax.php').'",
						data: { "action": "pay_booking", "tx": tx, id: ' . $id . ' },
						success: function ( data ) {
							if (data.status == "ok") {
								location.href = "' . addslashes( home_url( get_option('booking_url_payment_thanks') ) ) . '";
							}
							else {
								window.alert( "Accepted payment but there was a problem processing your payment. Please contact support." );
								button.disabled = false;
							}
						}
					} );
				}
			}
		} );
	} );
</script>';

	return $html;
} );

