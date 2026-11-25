<?php
/**
 * Plugin Name:       Notice Tray
 * Plugin URI:        https://github.com/memberfixstudio-ind/wordpress-toolbox/tree/main/notice-tray
 * Description:       Moves the pile of plugin and theme notices off the top of every admin screen into one tidy tray in the admin bar. Errors stay where you can see them. No settings.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Rohan Salave
 * Author URI:        https://www.linkedin.com/in/rohan-salave-362475189/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       notice-tray
 * Domain Path:       /languages
 *
 * @package NoticeTray
 */

defined( 'ABSPATH' ) || exit;

define( 'NOTICE_TRAY_VERSION', '1.0.0' );
define( 'NOTICE_TRAY_URL', plugin_dir_url( __FILE__ ) );

/**
 * User meta key that stores a person's "show notices on the page" choice.
 */
const NOTICE_TRAY_PAUSED_META = 'notice_tray_paused';

/**
 * Load translations shipped in /languages.
 */
function notice_tray_load_textdomain() {
	load_plugin_textdomain( 'notice-tray', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'notice_tray_load_textdomain' );

/**
 * Whether the tray should run for the current user on the current admin screen.
 *
 * @return bool
 */
function notice_tray_is_active() {
	if ( ! is_admin() || wp_doing_ajax() || ! is_user_logged_in() || ! is_admin_bar_showing() ) {
		return false;
	}

	$paused = (bool) get_user_meta( get_current_user_id(), NOTICE_TRAY_PAUSED_META, true );
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	// The block editor shows its own notices in its own UI, so leave it alone.
	if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
		return false;
	}

	/**
	 * Filters whether Notice Tray collects notices on the current screen.
	 *
	 * @param bool           $active Whether the tray is active. False when the user paused it.
	 * @param WP_Screen|null $screen The current admin screen.
	 */
	return (bool) apply_filters( 'notice_tray_enabled', ! $paused, $screen );
}

/**
 * Whether the current user has paused the tray.
 *
 * @return bool
 */
function notice_tray_is_paused() {
	return (bool) get_user_meta( get_current_user_id(), NOTICE_TRAY_PAUSED_META, true );
}

/**
 * URL that switches the tray on or off for the current user and comes back here.
 *
 * @param bool $pause True to pause, false to resume.
 * @return string
 */
function notice_tray_toggle_url( $pause ) {
	$back = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : admin_url();

	return add_query_arg(
		array(
			'action'   => 'notice_tray_toggle',
			'pause'    => $pause ? '1' : '0',
			'redirect' => $back,
			'_wpnonce' => wp_create_nonce( 'notice_tray_toggle' ),
		),
		admin_url( 'admin-post.php' )
	);
}

/**
 * Save the pause choice. Each person decides for themselves, so the only
 * capability needed is being logged in to wp-admin.
 */
function notice_tray_handle_toggle() {
	check_admin_referer( 'notice_tray_toggle' );

	if ( ! current_user_can( 'read' ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'notice-tray' ), 403 );
	}

	$pause = isset( $_GET['pause'] ) && '1' === sanitize_key( wp_unslash( $_GET['pause'] ) );

	if ( $pause ) {
		update_user_meta( get_current_user_id(), NOTICE_TRAY_PAUSED_META, 1 );
	} else {
		delete_user_meta( get_current_user_id(), NOTICE_TRAY_PAUSED_META );
	}

	$redirect = isset( $_GET['redirect'] ) ? esc_url_raw( wp_unslash( $_GET['redirect'] ) ) : '';
	wp_safe_redirect( wp_validate_redirect( $redirect, admin_url() ) );
	exit;
}
add_action( 'admin_post_notice_tray_toggle', 'notice_tray_handle_toggle' );

/**
 * Add the tray button to the admin bar. JavaScript fills in the count.
 *
 * @param WP_Admin_Bar $bar Admin bar instance.
 */
function notice_tray_admin_bar( $bar ) {
	if ( ! is_admin() || ! is_user_logged_in() ) {
		return;
	}

	if ( notice_tray_is_paused() ) {
		$bar->add_node(
			array(
				'id'     => 'notice-tray',
				'parent' => 'top-secondary',
				'title'  => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Tray off', 'notice-tray' ) . '</span>',
				'href'   => notice_tray_toggle_url( false ),
				'meta'   => array(
					'class' => 'notice-tray-paused',
					'title' => __( 'Notices are shown on the page. Click to collect them in the tray again.', 'notice-tray' ),
				),
			)
		);
		return;
	}

	if ( ! notice_tray_is_active() ) {
		return;
	}

	$bar->add_node(
		array(
			'id'     => 'notice-tray',
			'parent' => 'top-secondary',
			'title'  => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">' . esc_html__( 'Notices', 'notice-tray' ) . '</span><span class="notice-tray-count" aria-hidden="true"></span>',
			'href'   => '#notice-tray-panel',
			'meta'   => array( 'class' => 'notice-tray-node is-empty' ),
		)
	);
}
add_action( 'admin_bar_menu', 'notice_tray_admin_bar', 1 );

/**
 * Load the tray script and styles on admin screens where it is active.
 */
function notice_tray_enqueue() {
	if ( ! is_user_logged_in() || ! is_admin_bar_showing() ) {
		return;
	}

	wp_enqueue_style( 'notice-tray', NOTICE_TRAY_URL . 'assets/notice-tray.css', array( 'dashicons' ), NOTICE_TRAY_VERSION );

	if ( ! notice_tray_is_active() ) {
		return;
	}

	// "common" makes notices dismissible and moves them under the page title; run after it.
	wp_enqueue_script( 'notice-tray', NOTICE_TRAY_URL . 'assets/notice-tray.js', array( 'jquery', 'common' ), NOTICE_TRAY_VERSION, true );

	/**
	 * Filters CSS selectors for notices that should always stay on the page.
	 *
	 * Errors, the "Settings saved" message, the post "updated" message and inline
	 * notices are kept by default because they are about what you just did.
	 *
	 * @param string[] $selectors CSS selectors.
	 */
	$keep = apply_filters(
		'notice_tray_keep_selectors',
		array(
			'.notice-error',
			'.error',
			'.inline',
			'.hidden',
			'#message',
			'[id^="setting-error-"]',
			'.notice-tray-keep',
		)
	);

	wp_localize_script(
		'notice-tray',
		'noticeTray',
		array(
			'keep'     => implode( ',', array_filter( array_map( 'trim', array_map( 'strval', (array) $keep ) ) ) ),
			'pauseUrl' => notice_tray_toggle_url( true ),
			'i18n'     => array(
				'title'      => __( 'Notices', 'notice-tray' ),
				'empty'      => __( 'All clear. There are no notices on this screen.', 'notice-tray' ),
				'close'      => __( 'Close notices', 'notice-tray' ),
				'pause'      => __( 'Show notices on the page instead', 'notice-tray' ),
				/* translators: %d: number of notices. */
				'countOne'   => __( '%d notice', 'notice-tray' ),
				/* translators: %d: number of notices. */
				'countMany'  => __( '%d notices', 'notice-tray' ),
				'buttonOne'  => __( 'Notices, 1 waiting', 'notice-tray' ),
				/* translators: %d: number of notices. */
				'buttonMany' => __( 'Notices, %d waiting', 'notice-tray' ),
				'buttonNone' => __( 'Notices, none waiting', 'notice-tray' ),
				'hint'       => __( 'Errors and confirmations of what you just did stay on the page.', 'notice-tray' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'notice_tray_enqueue' );
