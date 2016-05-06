<?php
defined( 'ABSPATH' ) or die( 'Script kiddies make kitty sad.' );

if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}
 
delete_option( 'draftsforfriends_shared_posts' );
delete_option( 'draftsforfriends_version' );