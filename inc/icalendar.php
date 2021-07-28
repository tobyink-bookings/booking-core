<?php

add_action( 'booking_export_buttons', function () {
	$filename = get_option( 'booking_icalendar_filename' );
	if ( ! $filename ) {
		return false;
	}

	$url = home_url( sprintf( 'booking-export/%s', $filename ) );
	printf(
		"<p>iCalendar subscribe URL (make sure you subscribe to the URL; don't just download it and import):<br><code>%s</code></p>",
		htmlspecialchars( $url )
	);
} );

add_action( 'parse_request', function () {
	global $wp, $post;

	$pagename = $wp->query_vars['pagename'];
	if ( ! ( $pagename && preg_match( '/^booking-export/', $pagename ) ) ) {
		return false;
	}

	$filename = get_option( 'booking_icalendar_filename' );
	if ( ! $filename ) {
		return false;
	}

	$url = sprintf( 'booking-export/%s', $filename );
	if ( $pagename != $url ) {
		return false;
	}

	$config = json_decode( get_option( 'booking_icalendar_config', '{}' ), false );

	$site = get_option( 'blogname', 'WordPress' );

	header( 'Cache-Control: no-cache, must-revalidate' ); // HTTP/1.1
	header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );   // Date in the past
	header( 'Content-Type: text/calendar; charset=utf-8' );

	echo "BEGIN:VCALENDAR\n";
	echo "PRODID:-//Toby Ink Ltd//NONSGML TIL Bookings " . BOOKINGS_PLUGIN_VERSION . "//EN\n";
	echo "VERSION:2.0\n";
	echo "CALSCALE:GREGORIAN\n";
	echo "URL:" . home_url( $url ) . "\n";
	echo "NAME:$site\n";
	echo "X-WR-CALNAME:$site\n";
	echo "REFRESH-INTERVAL;VALUE=DURATION:PT20M\n";
	echo "X-PUBLISHED-TTL:PT20M\n";
	echo "METHOD:PUBLISH\n";

	$args = [
		'post_type'      => 'booking',
		'posts_per_page' => -1,
	];
	if ( $config->status ) {
		$args['meta_query'] = [ [ 'key' => 'status', 'value' => $config->status, 'compare' => 'IN' ] ];
	}

	$query = new WP_Query( $args );

	$dtfmt = 'Ymd\\THis\\Z';

	while ( $query->have_posts() ) {
		$query->the_post();

		$data = get_fields( $post->ID );
		$data['id'] = sprintf( '%08d', $post->ID );

		$dtstart = ( $config->dtstart_field ? get_field( $config->dtstart_field, $post->ID ) : $post->post_date_gmt );

		echo "BEGIN:VEVENT\n";
		printf( "UID:booking-%08d@%s\n", $post->ID, $_SERVER["SERVER_NAME"] );
		printf( "DTSTAMP:%s\n", gmdate( $dtfmt, strtotime( $post->post_modified_gmt ) ) );
		printf( "DTSTART:%s\n", gmdate( $dtfmt, strtotime( $dtstart ) ) );
		if ( $config->fields ) {
			foreach ( (array) $config->fields as $k => $v ) {
				printf( "%s:%s\n", strtoupper( $k ), _booking_process_template( $v, $data ) );
			}
		}
		echo "END:VEVENT\n";
	}

	echo "END:VCALENDAR\n";
	die();
}, 99, 0 );
