<?php

/**
 * Plugin Name:       TIL Bookings
 * Description:       Booking form with payments. Requires Advanced Custom Fields (Wordpress plugin) and Stripe (payment gateway).
 * Version:           1.6
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            Toby Ink Ltd
 * Author URI:        https://toby.ink/hire/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html 
 */

define( 'BOOKINGS_PLUGIN_VERSION', '1.6' );

if ( function_exists( 'get_field' ) ) {
	require_once( __DIR__ . '/inc/email.php' );
	require_once( __DIR__ . '/inc/cpt.php' );
	require_once( __DIR__ . '/inc/admin.php' );
	require_once( __DIR__ . '/inc/frontend.php' );
	require_once( __DIR__ . '/inc/ajax.php' );
	require_once( __DIR__ . '/inc/export.php' );
	require_once( __DIR__ . '/inc/icalendar.php' );
}
else {
	add_action( 'admin_notices', function () {
		echo '<div class="error notice"><p>Bookings plugin requires the Advanced Custom Fields plugin to be installed and enabled.</p></div>';
	} );
}
