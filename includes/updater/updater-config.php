<?php

// this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
define( 'IBX_WPFOMO_SL_URL', 'https://wpfomify.com' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file

define( 'IBX_WPFOMO_ITEM_NAME', 'WPfomify AppSumo Deal' );

// the name of the settings page for the license input to be displayed
define( 'IBX_WPFOMO_LICENSE_PAGE', 'wpfomo-settings' );

if ( ! class_exists( 'IBX_WPFomo_Plugin_Updater' ) ) {
	// load our custom updater
	include( dirname( __FILE__ ) . '/EDD_SL_Plugin_Updater.php' );
}

function ibx_wpfomo_plugin_updater() {

	// retrieve our license key from the DB
	$license_key = trim( get_option( 'ibx_wpfomo_license_key' ) );

	// setup the updater
	$updater = new IBX_WPFomo_Plugin_Updater( IBX_WPFOMO_SL_URL, IBX_WPFOMO_DIR . '/ibx-wpfomify.php', array(
			'version' 	=> IBX_WPFOMO_VER, 			// current version number
			'license' 	=> $license_key, 			// license key (used get_option above to retrieve from DB)
			'item_name' => IBX_WPFOMO_ITEM_NAME,	// name of this plugin
			'author' 	=> 'IdeaBox Creations',  	// author of this plugin
			'beta'		=> false,
		)
	);

}
add_action( 'admin_init', 'ibx_wpfomo_plugin_updater', 0 );

function wpfomo_sanitize_license( $new ) {
	$old = get_option( 'ibx_wpfomo_license_key' );
	if ( $old && $old != $new ) {
		delete_option( 'ibx_wpfomo_license_status' ); // new license has been entered, so must reactivate
	}
	return $new;
}

/************************************
* this illustrates how to activate
* a license key
*************************************/

function ibx_wpfomo_activate_license() {

	// listen for our activate button to be clicked
	if ( isset( $_POST['ibx_wpfomo_license_activate'] ) ) {

		// run a quick security check
		if ( ! check_admin_referer( 'ibx_wpfomo_license_activate_nonce', 'ibx_wpfomo_license_activate_nonce' ) ) {
			return; // get out if we didn't click the Activate button
		}

		// retrieve the license from the database
		$license = trim( get_option( 'ibx_wpfomo_license_key' ) );

		// data to send in our API request
		$api_params = array(
			'edd_action' => 'activate_license',
			'license'    => $license,
			'item_name'  => urlencode( IBX_WPFOMO_ITEM_NAME ), // the name of our product in EDD
			'url'        => home_url(),
		);

		// Call the custom API.
		$response = wp_remote_post( IBX_WPFOMO_SL_URL, array(
			'timeout' => 15,
			'sslverify' => false,
			'body' => $api_params,
		) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'An error occurred, please try again.', 'ibx-wpfomo' );
			}
		} else {

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( false === $license_data->success ) {

				switch ( $license_data->error ) {

					case 'expired' :
						$message = sprintf(
							// translators: %s is for date and time.
							__( 'Your license key expired on %s.', 'ibx-wpfomo' ),
							date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
						);
						break;

					case 'revoked' :

						$message = __( 'Your license key has been disabled.', 'ibx-wpfomo' );
						break;

					case 'missing' :

						$message = __( 'Invalid license.', 'ibx-wpfomo' );
						break;

					case 'invalid' :
					case 'site_inactive' :

						$message = __( 'Your license is not active for this URL.', 'ibx-wpfomo' );
						break;

					case 'item_name_mismatch' :
						// translators: %s is for WPfomify plugin name.
						$message = sprintf( __( 'This appears to be an invalid license key for %s.', 'ibx-wpfomo' ), IBX_WPFOMO_ITEM_NAME );
						break;

					case 'no_activations_left':

						$message = __( 'Your license key has reached its activation limit.', 'ibx-wpfomo' );
						break;

					default :

						$message = __( 'An error occurred, please try again.', 'ibx-wpfomo' );
						break;
				} // End switch().
			} // End if().
		} // End if().

		// Check if anything passed on a message constituting a failure
		if ( ! empty( $message ) ) {
			$base_url = IBX_WPFomo_Admin::get_form_action();
			$redirect = add_query_arg( array(
				'sl_activation' => 'false',
				'message' => urlencode( $message ),
			), $base_url );

			wp_redirect( $redirect );
			exit();
		}

		// $license_data->license will be either "valid" or "invalid"
		update_option( 'ibx_wpfomo_license_status', $license_data->license );

		wp_redirect( IBX_WPFomo_Admin::get_form_action() );
		exit();
	} // End if().
}
add_action( 'admin_init', 'ibx_wpfomo_activate_license' );


/***********************************************
* Illustrates how to deactivate a license key.
* This will decrease the site count
***********************************************/

function ibx_wpfomo_deactivate_license() {

	// listen for our activate button to be clicked
	if ( isset( $_POST['ibx_wpfomo_license_deactivate'] ) ) {

		// run a quick security check
		if ( ! check_admin_referer( 'ibx_wpfomo_license_deactivate_nonce', 'ibx_wpfomo_license_deactivate_nonce' ) ) {
			return; // get out if we didn't click the Activate button
		}

		// retrieve the license from the database
		$license = trim( get_option( 'ibx_wpfomo_license_key' ) );

		// data to send in our API request
		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $license,
			'item_name'  => urlencode( IBX_WPFOMO_ITEM_NAME ), // the name of our product in EDD
			'url'        => home_url(),
		);

		// Call the custom API.
		$response = wp_remote_post( IBX_WPFOMO_SL_URL, array(
			'timeout' => 15,
			'sslverify' => false,
			'body' => $api_params,
		) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'An error occurred, please try again.', 'ibx-wpfomo' );
			}

			$base_url = IBX_WPFomo_Admin::get_form_action();
			$redirect = add_query_arg( array(
				'sl_activation' => 'false',
				'message' => urlencode( $message ),
			), $base_url );

			wp_redirect( $redirect );
			exit();
		}

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		// $license_data->license will be either "deactivated" or "failed"
		if ( 'deactivated' == $license_data->license ) {
			delete_option( 'ibx_wpfomo_license_status' );
		}

		wp_redirect( IBX_WPFomo_Admin::get_form_action() );
		exit();
	} // End if().
}
add_action( 'admin_init', 'ibx_wpfomo_deactivate_license' );

function ibx_wpfomo_check_license() {

	global $wp_version;

	$license = trim( get_option( 'ibx_wpfomo_license_key' ) );

	$api_params = array(
		'edd_action' => 'check_license',
		'license' => $license,
		'item_name' => urlencode( IBX_WPFOMO_ITEM_NAME ),
		'url'       => home_url(),
	);

	// Call the custom API.
	$response = wp_remote_post( IBX_WPFOMO_SL_URL, array(
		'timeout' => 15,
		'sslverify' => false,
		'body' => $api_params,
	) );

	if ( is_wp_error( $response ) ) {
		return false;
	}

	$license_data = json_decode( wp_remote_retrieve_body( $response ) );

	if ( 'valid' == $license_data->license ) {
		return 'valid';
		// this license is still valid
	} else {
		return 'invalid';
		// this license is no longer valid
	}
}

/**
 * Catch errors from the activation method above and displaying it to the customer
 */
function ibx_wpfomo_admin_notices() {
	if ( isset( $_GET['sl_activation'] ) && ! empty( $_GET['message'] ) ) {

		switch ( $_GET['sl_activation'] ) {
			case 'false':
				$message = urldecode( $_GET['message'] );
				?>
				<div class="error" style="background: #fbfbfb; border-top: 1px solid #eee; border-right: 1px solid #eee;">
					<p><?php echo $message; ?></p>
				</div>
				<?php
				break;

			case 'true':
			default:
				break;
		}
	}
}
add_action( 'admin_notices', 'ibx_wpfomo_admin_notices' );
