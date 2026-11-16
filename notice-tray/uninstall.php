<?php
/**
 * Remove Notice Tray data when the plugin is deleted.
 *
 * The only thing stored is each person's "show notices on the page" choice.
 *
 * @package NoticeTray
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete the user meta for every user in one query.
delete_metadata( 'user', 0, 'notice_tray_paused', '', true );
