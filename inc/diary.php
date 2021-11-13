<?php

add_filter( 'booking_settings', function ( $keys ) {
	$keys['section_diary']               = [ 'heading' => 'Diary' ];
	$keys['booking_cols_diary']          = [ 'label' => 'Columns to show in Diary' ];
	$keys['booking_printable_template']  = [ 'label' => 'Printable Template (HTML)', 'type' => 'textarea' ];
	return $keys;
} );

add_action( 'admin_menu', function () {

	add_submenu_page( 'edit.php?post_type=booking', 'Prinatble Booking Form', 'Printable Form', 'manage_options', 'booking-printable-form', function () {

		$bookings = $_GET['booking'];
		if ( empty($bookings) || ! is_array($bookings) ) {
			echo '<div class=wrap><p>Error: no bookings.</p></div>';
			return true;
		}

		$template = get_option( 'booking_printable_template', '<p>ID #{{$id}}</p>' );

		echo '<style media="print">
			#adminmenumain, .noprint { display: none }
			#wpcontent { margin-left: 0 }
			#wpfooter { display: none }
			.page-break { visibility: hidden; page-break-after: always; }
		</style>';

		echo '<div class=wrap>';
		foreach ( $bookings as $i => $b ) {
			$fields               = get_fields( $b );
			$fields['id']         = sprintf( '%08d', $b );
			$fields['secret_url'] = home_url( sprintf( '%s?id=%08d-%s', get_option( 'booking_url_payment' ), $b, $fields['secret'] ) );
			if ( $i > 0 ) { echo '<hr class=page-break>'; }
			echo _booking_process_template( $template, $fields );
		}
		echo '</div>';
	} );

	add_filter( 'submenu_file', function ( $submenu_file ) {
		global $plugin_page;
		$hidden_submenus = [
			'booking-printable-form' => 'booking-diary',
		];
		if ( $plugin_page && isset( $hidden_submenus[$plugin_page] ) ) {
			$submenu_file =  $hidden_submenus[$plugin_page];
		}
		foreach ( $hidden_submenus as $submenu => $unused ) {
			remove_submenu_page( 'edit.php?post_type=booking', $submenu );
		}
		return $submenu_file;
	} );

	add_submenu_page( 'edit.php?post_type=booking', 'Booking Diary', 'Diary', 'manage_options', 'booking-diary', function () {

		if ( !current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		$config = json_decode( get_option( 'booking_icalendar_config', '{}' ) );
		$field  = $config->dtstart_field;

		echo '<style media="print">
			#adminmenumain, .noprint { display: none }
			#wpcontent, #wpfooter { margin-left: 0 }
		</style>';

		echo '<style media="screen">
			table.spaced th, table.spaced td { padding: 2px 8px }
		</style>';

		echo '<script>
		jQuery( document ).ready( function ($) {
			console.log( "Installing click handler..." );
			$( "#selectall" ).click( function ( e ) {
				console.log( "Handling" );
				$( ".selectthis" ).prop( "checked", $( "#selectall" ).is( ":checked" ) );
			} );
		} );
		</script>';

		echo '<div class=wrap>';
		echo '<h1>Booking Diary</h1>';

		// Show form
		if ( ! $_REQUEST['modus'] ) {
			$_REQUEST['modus'] = 'today';
		}
		echo '<form action="" method="post" class=noprint>';
		echo '<div>';
		echo '<label style="margin-right:1.5rem"><input ' . ( ($_REQUEST['modus']=='yesterday')  ? 'checked ' : '' ) . 'type=radio name=modus value=yesterday> Yesterday</label>';
		echo '<label style="margin-right:1.5rem"><input ' . ( ($_REQUEST['modus']=='today')      ? 'checked ' : '' ) . 'type=radio name=modus value=today> Today</label>';
		echo '<label style="margin-right:1.5rem"><input ' . ( ($_REQUEST['modus']=='tomorrow')   ? 'checked ' : '' ) . 'type=radio name=modus value=tomorrow> Tomorrow</label>';
		echo '<label style="margin-right:1.5rem"><input ' . ( ($_REQUEST['modus']=='overmorrow') ? 'checked ' : '' ) . 'type=radio name=modus value=overmorrow> Overmorrow</label>';
		echo '<label style="margin-right:0.5rem"><input ' . ( ($_REQUEST['modus']=='other')      ? 'checked ' : '' ) . 'type=radio name=modus value=other> Another Day:</label>';
		echo '<input type=date name=anotherday value="' . esc_html($_REQUEST['anotherday']) . '">';
		echo '<input type=submit value=Go style="margin-left:1.5rem" class="button">';
		echo '</div>';
		echo '</form>';

		if ( $_REQUEST['modus'] == 'today' ) {
			$date = date( 'Y-m-d' );
		}
		elseif ( $_REQUEST['modus'] == 'yesterday' ) {
			$date = date( 'Y-m-d', time()-(24*60*60) );
		}
		elseif ( $_REQUEST['modus'] == 'tomorrow' ) {
			$date = date( 'Y-m-d', time()+(24*60*60) );
		}
		elseif ( $_REQUEST['modus'] == 'overmorrow' ) {
			$date = date( 'Y-m-d', time()+(48*60*60) );
		}
		else {
			$date = date( 'Y-m-d', strtotime( $_REQUEST['anotherday'] ) );
		}

		$nextdate = date( 'Y-m-d', strtotime($date)+(24*60*60) );

		echo '<h2>' . date( 'd M Y', strtotime($date) ) . '</h2>';

		$query1 = new WP_Query( [
			'post_type'  => 'booking',
			'meta_query' => [
				[ 'key' => $field, 'compare' => 'BETWEEN', 'value' => [ $date, $nextdate ] ],
			],
			'order' => 'ASC',
			'orderby' => 'meta_value',
			'meta_key' => $field,
			'posts_per_page' => 1000,
		] );

		$fields = explode( ',', get_option( 'booking_cols_diary', 'email_address' ) );

		if ( $query1->have_posts() ) {
			echo '<form method=get action="' . esc_html( admin_url( 'edit.php' ) ) . '">';
			echo '<input type=hidden name=post_type value=booking>';
			echo '<input type=hidden name=page value=booking-printable-form>';
			echo '<table border=1 class=spaced>';
			echo '<thead>';
			echo '<tr>';
			echo '<th class=noprint><input type=checkbox id=selectall></th>';
			echo '<th>ID</th>';
			echo '<th>Time</th>';
			foreach ( $fields as $f ) {
				echo '<th>' . esc_html($f) . '</th>';
			}
			echo '</tr>';
			echo '</thead>';
			echo '<tbody>';
			while ( $query1->have_posts() ) {
				$query1->the_post();

				echo '<tr>';
				echo '<td class=noprint><input class=selectthis type=checkbox name="booking[]" value=' . get_the_ID() . '>';
				echo '<td><a href="' . esc_html( admin_url( 'edit.php?post_type=booking&page=booking-printable-form&booking[]=' . get_the_ID() ) ) . '">' . get_the_ID() . '</a></td>';
				echo '<td>' . date( 'H:m', strtotime( get_field( $field ) ) ) . '</td>';
				foreach ( $fields as $f ) {
					echo '<td>' . esc_html( _booking_do_field( $f ) ) . '</td>';
				}
				echo '</tr>';
			}
			echo '</tbody>';
			echo '</table>';
			echo '<p class=noprint><input type=submit value="Export Printable Summaries" class="button button-primary"></p>';
			echo '</form>';
		}
		else {
			echo '<p>No bookings.</p>';
		}

		echo '</div>';
	} );
}, 1 );

