<?php

add_action( 'init', function () {

	register_post_type( 'booking', [
		'labels'                => [
			'name'                  => __( 'Bookings', 'cpt-booking' ),
			'singular_name'         => __( 'Booking', 'cpt-booking' ),
			'all_items'             => __( 'All Bookings', 'cpt-booking' ),
			'archives'              => __( 'Booking Archives', 'cpt-booking' ),
			'attributes'            => __( 'Booking Attributes', 'cpt-booking' ),
			'insert_into_item'      => __( 'Insert into booking', 'cpt-booking' ),
			'uploaded_to_this_item' => __( 'Uploaded to this booking', 'cpt-booking' ),
			'featured_image'        => _x( 'Featured Image', 'booking', 'cpt-booking' ),
			'set_featured_image'    => _x( 'Set featured image', 'booking', 'cpt-booking' ),
			'remove_featured_image' => _x( 'Remove featured image', 'booking', 'cpt-booking' ),
			'use_featured_image'    => _x( 'Use as featured image', 'booking', 'cpt-booking' ),
			'filter_items_list'     => __( 'Filter bookings list', 'cpt-booking' ),
			'items_list_navigation' => __( 'Bookings list navigation', 'cpt-booking' ),
			'items_list'            => __( 'Bookings list', 'cpt-booking' ),
			'new_item'              => __( 'New Booking', 'cpt-booking' ),
			'add_new'               => __( 'Add New', 'cpt-booking' ),
			'add_new_item'          => __( 'Add New Booking', 'cpt-booking' ),
			'edit_item'             => __( 'Edit Booking', 'cpt-booking' ),
			'view_item'             => __( 'View Booking', 'cpt-booking' ),
			'view_items'            => __( 'View Bookings', 'cpt-booking' ),
			'search_items'          => __( 'Search bookings', 'cpt-booking' ),
			'not_found'             => __( 'No bookings found', 'cpt-booking' ),
			'not_found_in_trash'    => __( 'No bookings found in trash', 'cpt-booking' ),
			'parent_item_colon'     => __( 'Parent Booking:', 'cpt-booking' ),
			'menu_name'             => __( 'Bookings', 'cpt-booking' ),
		],
		'public'                => false,
		'hierarchical'          => false,
		'show_ui'               => true,
		'show_in_nav_menus'     => true,
		'supports'              => [ ],
		'has_archive'           => true,
		'rewrite'               => true,
		'query_var'             => true,
		'menu_position'         => null,
		'menu_icon'             => 'dashicons-clipboard',
		'show_in_rest'          => true,
		'rest_base'             => 'booking',
		'rest_controller_class' => 'WP_REST_Posts_Controller',
	] );

	add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
		if ( $data['post_type'] == 'booking' ) {
			$data['post_title'] = 'Booking';
		}
		return $data;
	}, 10, 2 );

	add_action( 'acf/save_post', function ( $post_id ) {

		global $BOOKING_STATUS_CHANGED;

		if ( 'booking' !== get_post_type( $post_id ) ) {
			error_log( "HERE: $post_id => " . get_post_type( $post_id ) );
			return false;
		}

		$OLD = get_fields( $post_id );
		$NEW = [];
		$groups = array_merge(
			explode( ',', get_option( 'booking_fg_client' ) ),
			explode( ',', get_option( 'booking_fg_admin' ) )
		);
		foreach ( $groups as $group ) {
			$F = acf_get_fields( acf_get_field_group( $group ) );
			foreach ( $F as $field ) {
				$NEW[ $field['name'] ] = $_POST['acf'][ $field['key'] ];
			}
		}

		$existing = false;

		if ( $post_id > 0 ) {
			$secret = get_post_meta( $post_id, 'secret', true );
			if ( ! $secret ) {
				$secret = wp_generate_password( 12, false, false );
				update_post_meta( $post_id, 'secret', $secret );
			}

			$NEW['id']         = sprintf( '%08d', $post_id );
			$NEW['secret']     = $secret;
			$NEW['secret_url'] = home_url( sprintf( '%s?id=%08d-%s', get_option( 'booking_url_payment' ), $post_id, $secret ) );

			$existing = get_post_meta( $post_id, 'existing_booking', true );
		}

		if ( ( ! $existing ) && ( ! $NEW['status'] ) ) {
			$NEW['status'] = get_option( 'booking_default_status', 'Requested' );
			update_post_meta( $post_id, 'status', $NEW['status'] );
		}

		if ( ( ! $existing ) || ( $NEW['status'] != $OLD['status'] ) ) {
			$BOOKING_STATUS_CHANGED = true;
		}
	}, 5, 1 );

	add_action( 'acf/save_post', function ( $post_id ) {

		global $BOOKING_STATUS_CHANGED;

		if ( $BOOKING_STATUS_CHANGED ) {
			$NEW = get_fields( $post_id );
			$NEW['id']         = sprintf( '%08d', $post_id );
			$NEW['secret']     = get_post_meta( $post_id, 'secret', true );
			$NEW['secret_url'] = home_url( sprintf( '%s?id=%08d-%s', get_option( 'booking_url_payment' ), $post_id, $NEW['secret'] ) );
			booking_send_notifications( $post_id, $NEW );
		}

		if ( $post_id > 0 ) {
			update_post_meta( $post_id, 'existing_booking', $post_id );
		}

	}, 55, 1 );

} );
