<?php

add_filter( 'booking_settings', function ( $keys ) {
	$keys['section_email']               = [ 'heading' => 'Emails' ];
	$keys['booking_notifications']       = [ 'label' => 'Notifications Data (JSON, Advanced Use Only)', 'notrim' => true, 'type' => 'textarea' ];
	$keys['booking_global_template']     = [ 'label' => 'Global Email Template (HTML)', 'type' => 'textarea' ];
	return $keys;
} );

function _booking_process_template ( $template, $fields ) {
	$php_template = '?>' . preg_replace_callback( '/\{\{(.+?)\}\}/', function ( $mmm ) {
		return '<?php echo (' . $mmm[1] . '); ?>';
	}, $template );
	extract( $fields );
	ob_start();
	eval( $php_template );
	return ob_get_clean();
}

function booking_send_notifications ( $post_id, $NEW ) {

	$messages = json_decode( get_option( 'booking_notifications', '[]' ), false );

	foreach ( $messages as $msg ) {

		if ( $msg->disabled ) {
			continue;
		}

		if ( $msg->to_status && ( $msg->to_status != $NEW['status'] ) ) {
			continue;
		}

		if ( $msg->conditions ) {
			$okay = call_user_func( function ( $cond, $post_id, $NEW ) {
				extract( $NEW );
				return eval( "return ($cond);" );
			}, $msg->conditions, $post_id, $NEW );
			if ( ! $okay ) {
				continue;
			}
		}

		$to      = _booking_process_template( $msg->to,      $NEW );
		$from    = _booking_process_template( $msg->from,    $NEW );
		$subject = _booking_process_template( $msg->subject, $NEW );
		$body    = _booking_process_template( $msg->body,    $NEW );

		$headers = [ "From: $from" ];
		if ( $msg->html ) {
			$headers []= "Content-Type: text/html";

			if ( $msg->html_global_template ) {
				$template = get_option( 'booking_global_template' );
				$body = _booking_process_template( $template, [
					'body'      => wpautop( $body ),
					'subject'   => $subject,
				] );
			}
		}

		error_log( "[$subject] $from -> $to" );
		wp_mail( $to, $subject, $body, $headers );
	}
}
