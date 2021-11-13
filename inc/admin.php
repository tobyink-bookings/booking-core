<?php

add_action( 'admin_init', function () {
	add_filter( 'manage_booking_posts_columns', function ( $cols ) {
		unset( $cols['title'] );
		unset( $cols['date'] );
		$cols['id']                   = 'Booking ID';
		$cols['date']                 = 'Booking date';
		$cols['status']               = 'Booking status';
		foreach ( explode( ',', get_option( 'booking_cols', '' ) ) as $x ) {
			$cols[$x] = ucwords( str_replace( '_', ' ', $x ) );
		}
		return $cols;
	} );

	add_filter( 'manage_edit-booking_sortable_columns', function ( $cols ) {
		$cols['status']               = 'booking-status';
		$cols['id']                   = 'booking-id';
		foreach ( explode( ',', get_option( 'booking_cols', '' ) ) as $x ) {
			$cols[$x] = "booking-$x";
		}
		return $cols;
	} );

	add_action( 'pre_get_posts', function ( $query ) {
		if( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( 'booking-id' === $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'ID' );
		}
		elseif ( substr( $query->get( 'orderby' ), 0, 8 ) === 'booking-' ) {
			$k = substr( $query->get( 'orderby' ), 8 );
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'meta_key', $key );
		}
		return $query;
	} );

	add_action( 'manage_booking_posts_custom_column', function ( $col, $post_id ) {
		if ( $col === 'id' ) {
			printf( '%08d', $post_id );
		}
		elseif ( $col === 'status' ) {
			$s = get_field( $col, $post_id );
			if ( ! $s ) $s = 'Requested';
			echo $s;
		}
		elseif ( $col === 'fee' ) {
			$fee = get_field( 'fee', $post_id );
			if ( $fee ) {
				echo '&pound;' . $fee;
			}
			else {
				echo '&mdash;';
			}
		}
		elseif ( $col === 'full_name' ) {
			printf( '<a href="mailto:%s">%s</a>', get_field( 'email_address', $post_id ), get_field( 'full_name', $post_id ) );
		}
		elseif ( in_array( $col, explode( ',', get_option( 'booking_cols', '' ) ) ) ) {
			echo htmlspecialchars( get_field( $col, $post_id ) );
		}

	}, 10, 2 );

	add_filter( 'post_row_actions', function ( $actions, $post ) {
		if ( $post->post_type == 'booking' ) {
			$s = get_field( 'status', $post->ID );
			if ( ! in_array( $s, explode( ',', get_option( 'booking_cancelled_status', 'Accepted' ) ) )) {
				unset( $actions['trash'] );
			}
			if ( in_array( $s, explode( ',', get_option( 'booking_accepted_status', 'Accepted' ) ) ) ) {
				$secret = get_field( 'secret', $post->ID );
				$actions['pay'] = sprintf(
					'<a href="%s">Pay</a>',
					esc_html( home_url( sprintf( '%s?id=%08d-%s', get_option( 'booking_url_payment' ), $post->ID, $secret ) ) )
				);
			}
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}, 10, 2 );
} );

add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=booking', 'Booking Export', 'Export', 'manage_options', 'booking-export', function () {
		if ( !current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
		echo '<div class=wrap>';
		echo '<h1>Export Booking Data</h1>';
		do_action( 'booking_export_buttons' );
		echo '</div>';
	} );

	add_submenu_page( 'edit.php?post_type=booking', 'Booking Settings', 'Settings', 'manage_options', 'booking-settings', function () {

		if ( !current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		echo '<div class=wrap>';
		echo '<h1>Booking Settings</h1>';

		$keys = apply_filters( 'booking_settings', [
			'section_acf'                 => [ 'heading' => 'Field Setup' ],
			'booking_fg_client'           => [ 'label' => 'Booking Field Groups for Client' ],
			'booking_fg_admin'            => [ 'label' => 'Booking Field Groups for Admin' ],
			'booking_cols'                => [ 'label' => 'Columns to show in All Bookings' ],

			'section_status'              => [ 'heading' => 'Statuses' ],
			'booking_default_status'      => [ 'label' => 'Booking Default Status' ],
			'booking_accepted_status'     => [ 'label' => 'Booking Accepted Status' ],
			'booking_payment_status'      => [ 'label' => 'Booking Paid Status' ],
			'booking_cancelled_status'    => [ 'label' => 'Booking Cancelled Status' ],

			'section_urls'                => [ 'heading' => 'Page URLs' ],
			'booking_url_booking_thanks'  => [ 'label' => 'URL for Booking Form Thanks' ],
			'booking_url_payment'         => [ 'label' => 'URL for Payment Form' ],
			'booking_url_payment_thanks'  => [ 'label' => 'URL for Payment Form Thanks' ],

			'section_stripe'              => [ 'heading' => 'Stripe Configuration' ],
			'booking_stripe_key'          => [ 'label' => 'Stripe Publishable Key' ],
			'booking_stripe_secret'       => [ 'label' => 'Stripe Secret Key' ],
			'booking_stripe_config'       => [ 'label' => 'Stripe Config (Advanced Use Only)', 'notrim' => true, 'type' => 'textarea' ],

			'section_places'              => [ 'heading' => 'Google Places API Configuration' ],
			'booking_places_api_key'      => [ 'label' => 'Places API Key' ],
			'booking_places_bias'         => [ 'label' => 'Places Bias (JSON)', 'notrim' => true ],
		] );

		$form_html = '';
		foreach ( $keys as $key => $field_data ) {
			if ( isset($field_data['heading']) ) {
				$form_html .= "<h2>" . $field_data['heading'] . "</h2>";
				continue;
			}

			$key   = strtolower( $key );
			$val   = get_option( $key );
			$label = $field_data['label'];
			$type  = isset($field_data['type']) ? $field_data['type'] : 'text';

			if ( array_key_exists($key, $_POST) && stripslashes($_POST[$key]) != $val ) {
				$val = stripslashes($_POST[$key]);
				if ( ! $field_data['notrim'] ) {
					$val = implode( ',', array_map(
						function ( $str ) { return trim( $str ); },
						explode( ',', $val )
					) );
				}
				update_option( $key, $val );
				echo "<div class=updated><p>Updated <strong>${label}</strong></p></div>\n";
			}

			$form_html .= "<p><label for=${key}>${label}</label><br>\n";
			if ( $type == 'textarea' ) {
				$form_html .= "<textarea style='width:100%' name=${key} id=${key} rows=8 cols=78>" . esc_html($val) . "</textarea></p>\n";
			}
			else {
				$form_html .= "<input style='width:100%' type=text name=${key} id=${key} value=\"" . esc_html($val) . "\" size=40></p>\n";
			}
		}

		echo "<form style='max-width:960px' method=post action=''>\n";
		echo $form_html;
		echo "<p><input type=submit class='button button-primary button-large' value='Save'></p>\n";
		echo "</form>\n";
		echo '</div>';
	} );

	add_submenu_page( 'edit.php?post_type=booking', 'Form Editor', 'Form Editor', 'manage_options', 'booking-form-editor', function () {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		echo '<div class=wrap>';
		echo '<h1>Booking Form Fields</h1>';

		echo '<p>This plugin uses Advanced Custom Fields as its form editor. Client field groups are shown to the client when booking. Both sets of field groups are shown in the backend and are available in email notifications, exports, etc.</p>';

		$kinds = [
			'booking_fg_client' => 'Booking Field Groups for Client',
			'booking_fg_admin'  => 'Booking Field Groups for Admin',
		];

		foreach ( $kinds as $setting => $label ) {
			echo "<h2>$label</h2>";
			$groups = explode( ',', get_option( $setting ) );
			if ( count($groups) ) {
				echo '<ul style="margin-left:2em;list-style:disc">';
				foreach ( $groups as $fg ) {
					$fg = trim( $fg );
					$fginfo = acf_get_field_group( $fg );
					printf(
						'<li><strong><a href="%s">%s</a></strong><br>%s</li>',
						admin_url( 'post.php?post=' . $fginfo['ID'] . '&action=edit' ),
						esc_html( $fginfo['title'] ),
						implode( ', ', array_map( function ( $f ) {
							return sprintf( '<code>%s</code>', esc_html($f['name']) );
						}, acf_get_fields( $fginfo ) ) )
					);
				}
				echo '</ul>';
			}
			else {
				printf(
					'<p>No fields set up. Set up at least one field group in <a href="%s">Custom Fields</a> and ensure its key is listed as "%s" in <a href="%s">settings</a>.</p>',
					admin_url('edit.php?post_type=acf-field-group'),
					$label,
					admin_url('edit.php?post_type=booking&page=booking-settings')
				);
			}
		}

		echo '</div>';
	} );

	add_submenu_page( 'edit.php?post_type=booking', 'Email Notifications', 'Email Notifications', 'manage_options', 'booking-email-notifications', function () {

		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		echo '<div class=wrap>';
		echo '<h1>Booking Email Notifications</h1>';

		$json_data = get_option( 'booking_notifications', '[ { "disabled": true, "description": "Untitled message" } ]' );
		if ( array_key_exists( 'json_data', $_POST ) ) {
			$json_data = stripslashes( $_POST['json_data'] );
			update_option( 'booking_notifications', $json_data );
			echo "<div class=updated><p>Saved changes to email notifications.</p></div>\n";
		}
?>
<div style="display:flex;max-width:1100px;margin:0.5rem -0.5rem;border:1px solid #ccc;border-radius:5px;background:#e4e4e4">
	<div style="flex:1;margin:0 0.5rem">
		<p>
			<label for="mail" style="display:block">Message</label>
			<select multiple id="mail" style="width:100%;height:50%;min-height:12rem"></select>
		</p>
		<p>
			<button class="button" id="new_mail">New email notification</button>
		</p>
	</div>
	<div style="flex:2;margin:0 0.5rem">
		<p>
			<label for="description" style="display:block">Description</label>
			<input id="description" type="text" style="width:100%">
		</p>
		<p>
			<label for="to_status" style="display:block">Send message when booking reaches this status</label>
			<input id="to_status" type="text" style="width:100%">
			<small style="color:#999">If this is left blank, the email will be sent on ALL status changes, which is probably not what you want.</small>
		</p>
		<p>
			<label for="to" style="display:block">To</label>
			<input id="to" type="text" style="width:100%">
			<small style="color:#999">Setting this to <code style="font-size:inherit">{{$email_address}}</code> will send the message to the client.</small>
		</p>
		<p>
			<label for="from" style="display:block">From</label>
			<input id="from" type="text" style="width:100%">
		</p>
		<p>
			<label for="subject" style="display:block">Subject</label>
			<input id="subject" type="text" style="width:100%">
		</p>
		<p>
			<label for="body" style="display:block">Body</label>
			<textarea id="body" cols="60" rows="12" style="width:100%"></textarea>
			<small style="color:#999">Use <code style="font-size:inherit">{{...}}</code> to insert short PHP expressions. For example <code style="font-size:inherit">{{$full_name}}</code> to insert the client's full name; <code style="font-size:inherit">{{$id}}</code> to insert the booking ID; <code style="font-size:inherit">{{$secret_url}}</code> to insert the payment URL; <code style="font-size:inherit">{{$some_acf_field}}</code> to insert a field set up in ACF.</small>
		</p>
		<p>
			<input id="html" type="checkbox">
			<label for="html">Body is HTML</label><br>
			<small style="color:#999">If sending HTML mail, remember to use <code style="font-size:inherit">{{esc_html(...)}}</code> in the body to escape special characters as appropriate.</small>
		</p>
		<p>
			<input id="html_global_template" type="checkbox">
			<label for="html_global_template">Apply global HTML template</label>
		</p>
		<p>
			<label for="conditions" style="display:block">Additional conditions</label>
			<input id="conditions" type="text" style="width:100%">
			<small style="color:#999">A PHP expression which, if evaluated to decide whether the mail gets sent. ACF fields are available as variables.</small>
		</p>
		<p>
			<input id="disabled" type="checkbox">
			<label for="disabled">Disable this email notification</label>
		</p>
		<p style="text-align:right">
			<button class="button" style="color:red" id="remove_mail">Remove email notification</button>
		</p>
	</div>
</div>
<form action="" method="post">
	<p>
		<input type="hidden" name="json_data" value="" id="json_data">
		<input id="save" type="submit" value="Save all" class="button button-primary button-large">
	</p>
</form>

<script type="text/javascript">
function email_configuration ( $, messages ) {

	var fields = [ 'description', 'to', 'from', 'subject', 'body', 'html', 'html_global_template', 'conditions', 'to_status', 'disabled' ];

	function loadForm ( i ) {
		var msg = messages[i];
		for ( var fn in fields ) {
			var f = fields[fn];
			if ( f === 'disabled' || f === 'html' || f === 'html_global_template' ) {
				$( '#' + f ).prop( 'checked', msg[f] );
			}
			else {
				$( '#' + f ).val( msg[f] );
			}
		}
		return false;
	}

	function saveForm ( i ) {
		var msg = messages[i];
		for ( var fn in fields ) {
			var f = fields[fn];
			if ( f === 'disabled' || f === 'html' || f === 'html_global_template' ) {
				msg[f] = $( '#' + f ).prop( 'checked' );
			}
			else {
				msg[f] = $( '#' + f ).val();
			}
		}
		return false;
	}

	var $selectbox = $( '#mail' );
	$selectbox.find( 'option' ).remove();
	for ( var i in messages ) {
		var msg   = messages[i];
		var label = ( msg.disabled ? '* ' : '' ) + msg.description; 
		$selectbox.append( '<option>' + label + '</option>' );
	}

	var currentSelection = -1;
	$selectbox.change( function ( e ) {
		if ( currentSelection >= 0 ) {
			saveForm( currentSelection );
		}
		currentSelection = $selectbox.prop( 'selectedIndex' );
		loadForm( currentSelection );
	} );
	$selectbox.prop( 'selectedIndex', 0 );
	$selectbox.change();

	$( '#new_mail' ).click( function ( e ) {
		messages.push( { description: 'Untitled message', disabled: true } );
		$selectbox.append( '<option>* Untitled message</option>' );
	} );

	$( '#remove_mail' ).click( function ( e ) {
		if ( confirm( "Are you sure you want to remove this email?" ) ) {
			messages.splice( currentSelection, 1 );
			$( $selectbox.find( 'option' )[currentSelection] ).remove();
			currentSelection = -1;
			$selectbox.prop( 'selectedIndex', 0 );
			$selectbox.change();
		}
	} );

	$( '#description, #disabled' ).change( function () {
		var label = ( $( '#disabled' ).prop( 'checked' ) ? '* ' : '' ) + $( '#description' ).val();
		$( $selectbox.find( 'option' )[currentSelection] ).text( label );
	} );

	$( '#save' ).click( function ( e ) {
		if ( currentSelection >= 0 ) {
			saveForm( currentSelection );
		}
		$( '#json_data' ).val( JSON.stringify( messages ) );
	} );
}

email_configuration( jQuery, <?php echo $json_data; ?> );

</script>
<?php

		echo '</div>';
	} );

	add_submenu_page( 'edit.php?post_type=booking', 'Bookings Help', 'Help', 'manage_options', 'bookings-help', function () {
		echo '<div class=wrap>';
		echo '<h1>Bookings Help</h1>';
		echo '<h2>Shortcodes</h2>';
		echo '<p>The shortcode <code>[booking-booking]</code> should be used on the booking form page.</p>';
		echo '<p>The shortcode <code>[booking-payment]</code> should be used on the payment form page.</p>';
		echo '<h2>Fields</h2>';
		echo "<p>The following (admin) fields are very special to the system and should't be altered: status, payment_datetime, payment_details, fee.</p>";
		echo "<p>The following (client) fields are somewhat special to the system and probably shouldn't be altered: email_address.</p>";
		echo '<h2>Statuses</h2>';
		printf( '<img src="%s/assets/status-flow-diagram.png" alt="Status diagram" />', plugin_dir_url( __DIR__ . '/../.' ) );
		echo '<h3>Requested</h3>';
		echo '<p>When the booking form is filled in, it is given the requested status.</p>';
		echo '<h3>Declined</h3>';
		echo '<p>If the admin needs to turn down a booking at any point before it has been paid, they should set it to Declined.</p>';
		echo '<h3>Accepted</h3>';
		echo '<p>The admin should change a booking from Requested to Accepted to proceed further. The admin needs to specify the appointment date/time, location, and fee.</p>';
		echo '<h3>Paid</h3>';
		echo '<p>When the client fills in the payment form, the status changes to Paid.</p>';
		echo '<h3>Cancelled</h3>';
		echo '<p>If the booking needs to be cancelled after having been paid, it should be changed to the Cancelled status by the admin. The client will not automatically be refunded; this can be done through the Stripe dashboard or other means.</p>';

		echo '<hr>';
		echo '<p><a href="https://dashboard.stripe.com/" target="_blank">Open the Stripe Dashboard</a></p>';
		echo '</div>';
	} );
} );

add_action( 'wp_dashboard_setup', function () {
	wp_add_dashboard_widget( 'booking_widget', 'Incoming Bookings', function () {
		global $post;

		$status = get_option( 'booking_default_status', 'Requested' );
		$args = [
			'post_type'      => 'booking',
			'posts_per_page' => -1,
			'meta_query'     => [
				'relation' => 'OR',
				[ 'key' => 'status', 'compare' => 'IN', 'value' => [ $status, '' ] ],
				[ 'key' => 'status', 'compare' => 'NOT EXISTS', 'value' => '' ],
			],
		];
		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			echo "<p>Incoming requests:</p>";
			echo "<ul>";
			while ( $query->have_posts() ) {
				$query->the_post();
				printf( '<li><a href="%s">%08d</a></li>', get_edit_post_link(), $post->ID );
			}
			echo "</ul>";
		}
		else {
			echo "<p>No incoming bookings.</p>";
		}
	} );
} );
