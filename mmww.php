<?php
/*
Plugin Name: MMWW
Plugin URI: http://www.plumislandmedia.net/wordpress-plugins/mmww/
Description: Use the Media Metadata Workflow Wizard to integrate your media metadata workflow with WordPress's Media Library. If you create lots of images, audio clips, or video clips you probably work hard to put metadata (titles, authors, copyrights, track names, dates, and all that) into them. Now you can have that metadata stored into the Media Library automatically when you upload your media files.
Author: Ollie Jones
Version: 1.0.7
Author URI: http://www.plumislandmedia.net/about/
License: GPLv2 or later
*/
/** current version number  */
if ( !defined( 'MMWW_VERSION_NUM' ) ) {
    define('MMWW_VERSION_NUM', '1.0.7');
}
/* set up some handy globals */
if ( !defined( 'MMWW_THEME_DIR' ) ) {
    define('MMWW_THEME_DIR', ABSPATH . 'wp-content/themes/' . get_template());
}
if ( !defined( 'MMWW_PLUGIN_NAME' ) ) {
    define('MMWW_PLUGIN_NAME', trim( dirname( plugin_basename( __FILE__ ) ), '/' ));
}
if ( !defined( 'MMWW_PLUGIN_DIR' ) ) {
    define('MMWW_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . MMWW_PLUGIN_NAME);
}
if ( !defined( 'MMWW_PLUGIN_URL' ) ) {
    define('MMWW_PLUGIN_URL', WP_PLUGIN_URL . '/' . MMWW_PLUGIN_NAME);
}
if ( !defined( 'MMWW_POSTMETA_KEY' ) ) {
    define('MMWW_POSTMETA_KEY', '_' . MMWW_PLUGIN_NAME . '_metadata');
}

register_activation_hook( __FILE__, 'mmww_activate' );

$saved = get_include_path();
set_include_path( $saved . PATH_SEPARATOR . MMWW_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'code' );

add_action( 'init', 'mmww_do_everything' );
add_action( 'plugins_loaded', 'mmww_l12n' );

function mmww_do_everything()
{
    if ( is_admin() && current_user_can( 'manage_options' ) ) {
        require_once('code/pdfextras.php');
        require_once('code/reread.php');
        require_once('code/mmww_admin.php');
    }
    if ( current_user_can( 'upload_files' ) ) {
        require_once('code/mmww_media_upload.php');
        if ( version_compare( get_bloginfo( 'version' ), '3.5', '<' ) ) {
            require_once('code/audio_shortcode_34_support.php');
        } else {
            require_once('code/audio_shortcode_35_support.php');
        }
    }
}

function mmww_l12n() {
    if ( is_admin() ) {
        /* no need for translation except in admin */
        load_plugin_textdomain( 'mmww', false, dirname( plugin_basename( __FILE__ ) ) .
            DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR );
    } 
}

function mmww_activate()
{
    if ( version_compare( get_bloginfo( 'version' ), '3.1', '<' ) ) {
        deactivate_plugins( basename( __FILE__ ) ); /* fail activation */
    }
    /* make sure the options are loaded, but don't overwrite existing version */
    add_option( 'mmww_version', MMWW_VERSION_NUM, false, 'no' );

    /* check version and upgrade plugin if need be. */
    if ( MMWW_VERSION_NUM != ($opt = get_option( 'mmww_version', '0.0.0' )) ) {
        /* do update procedure here as needed */
        update_option( 'mmww_version', MMWW_VERSION_NUM );
    }

    /* handle options settings defaults */
    $o = array(
        'audio_shortcode' => 'media', /* never, media  -- choose one */
        'audio_caption' => '({credit} )({title} )({album} )({year} )(Copyright &copy; {copyright} )([{tags}] )({description})',
        'audio_title' => '({title})',
        'audio_displaycaption' => '({grouptitle} )({title} )({album} )({credit})',
        'image_caption' => '({title} )({credit} )(Copyright &copy; {copyright} )([{tags}] )({description}))',
        'image_displaycaption' => '({title})',
        'image_alt' => '({title} )({credit})',
        'image_title' => '({title})',
        'application_caption' => '({title} )({credit} )(Copyright &copy; {copyright} )([{tags}] )({description})',
        'application_title' => '({title})',
        'use_creation_date' => 'no',
        'populate_tags' => 'yes',
        'populate_ratings' => 'yes',
        'tag_taxonomy_name' => 'media-tags',
        'rating_taxonomy_name' => 'media-ratings',


    );
    add_option( 'mmww_options', $o, false, 'no' );
}
