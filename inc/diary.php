<?php

add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=booking', 'Booking Diary', 'Diary', 'manage_options', 'booking-diary', function () {

		if ( !current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		$config = json_decode( get_option( 'booking_icalendar_config', '{}' ) );
		$field  = $config->dtstart_field;

		echo '<style media="print">
			#adminmenumain, #day_selection { display: none }
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
		echo '<form action="" method="post" id="day_selection">';
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
			echo '<table border=1 class=spaced>';
			echo '<thead>';
			echo '<tr>';
			echo '<th><input type=checkbox id=selectall></th>';
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
				echo '<td><input class=selectthis type=checkbox name="booking_id[]" value=' . get_the_ID() . '>';
				echo '<td><a href="' . get_edit_post_link() . '">' . get_the_ID() . '</a></td>';
				echo '<td>' . date( 'H:m', strtotime( get_field( $field ) ) ) . '</td>';
				foreach ( $fields as $f ) {
					echo '<td>' . esc_html( _booking_do_field( $f ) ) . '</td>';
				}
				echo '</tr>';
			}
			echo '</tbody>';
			echo '</table>';
			echo '<p><input type=submit value="Export Printable Summaries" class="button button-primary"> (not implemented yet)</p>';
		}
		else {
			echo '<p>No bookings.</p>';
		}

		echo '</div>';
	} );
}, 1 );

