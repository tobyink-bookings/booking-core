<?php

add_action( 'booking_export_buttons', function () {
	$url = sprintf(
		'%s&action=booking_export_csv&_wpnonce=%s',
		admin_url( 'admin.php?page=booking-export' ),
		wp_create_nonce( 'booking_export_csv' )
	);
	printf(
		'<p><a href="%s" class="page-title-action">Export to CSV</a></p>',
		htmlspecialchars( $url )
	);
} );

function _booking_do_field ( $wanted ) {
	global $post;
	list( $f, $fmt, $param ) = explode( '~', $wanted );

	if ( $f === 'id' ) {
		$value = sprintf( '%08d', $post->ID );
	}
	elseif ( in_array( $f, [ 'post_title', 'post_date', 'post_date_gmt', 'post_status', 'post_name', 'post_modified', 'post_modified_gmt' ] ) ) {
		$value = $post->$f;
	}
	else {
		$value = get_field( $f, $post->ID );
	}

	if ( $fmt == 'count' ) {
		return count( $value );
	}
	elseif ( $fmt == 'json' ) {
		return json_encode( $value );
	}
	elseif ( $fmt == 'date' ) {
		return date( $param, strtotime( $value ) );
	}
	elseif ( $fmt == 'sprintf' ) {
		return sprintf( $param, $value );
	}
	elseif ( $fmt == 'checked' ) {
		$checked = [];
		foreach ( $value as $k => $v ) {
			if ( $v ) {
				$checked []= $k;
			}
		}
		return implode( "; ", $checked );
	}
	elseif ( $fmt == 'summary' ) {
		return implode( "; ", array_map( function ( $item ) {
			return implode( " ", array_values($item) );
		}, $value ) );
	}
	else {
		return $value;
	}
}

if ( isset( $_GET['action'] ) && $_GET['action'] == 'booking_export_csv' ) {
	add_action( 'admin_init', function () {

		if( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if( ! is_admin() ) {
			return false;
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'booking_export_csv' ) ) {
			die( 'Security check error' );
		}

		$filename = sprintf( 'bookings-%s-%d.csv', $_SERVER['SERVER_NAME'], time() );
		$fields   = explode( ',', get_option( 'booking_export_fields', 'id,post_date,status' ) );

		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Content-Description: File Transfer' );
		header( 'Content-type: text/csv' );
		header( "Content-Disposition: attachment; filename={$filename}" );
		header( 'Expires: 0' );
		header( 'Pragma: public' );

		$out = fopen('php://output', 'w');
		fputcsv( $out, $fields );

		$query = new WP_Query( [
			'post_type'      => 'booking',
			'posts_per_page' => -1,
		] );

		while ( $query->have_posts() ) {
			$query->the_post();
			$values = array_map( '_booking_do_field', $fields );
			fputcsv( $out, $values );
		}

		fclose( $out );
		die();
	} );
}



