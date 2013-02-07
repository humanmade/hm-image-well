<?php
/*
Plugin Name: Image Well Plugin
Description: Allows the use of upload image wells on the site
Author: Human Made Limited
Version: 0.1
Author URI: http://hmn.md
*/

define( 'IMAGE_WELL_VERSION', '1.0.0' );

define( 'IMAGE_WELL_PATH', trailingslashit( str_replace( '\\', '/',  dirname( __FILE__ ) ) ) );
define( 'IMAGE_WELL_URL', str_replace( str_replace( '\\', '/', WP_CONTENT_DIR ), WP_CONTENT_URL, IMAGE_WELL_PATH ) );

add_theme_support( 'image_wells' );

require_once( IMAGE_WELL_PATH . 'class.image-well.php' );

function image_well( $id, $attachment_id, $args = array() ) {

	$image_well = new Upload_Image_Well( $id, $attachment_id, $args );
	$image_well->html();
}

add_action( 'admin_init', 'image_well_enqueue_assets' );
function image_well_enqueue_assets() {

    Upload_Image_Well::enqueue_scripts();
}

//Hook to handle the upload of the image
add_action( 'wp_ajax_plupload_image_upload', function() {

	switch_to_blog( HT_User::current_user()->get_site()->get_id() );

	call_user_func( array( 'Upload_Image_Well', 'handle_upload' ) );

	restore_current_blog();
} );