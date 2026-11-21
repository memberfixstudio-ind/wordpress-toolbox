<?php
/**
 * Plugin Name:       Session Watch
 * Plugin URI:        https://github.com/memberfixstudio-ind/wordpress-toolbox/tree/main/session-watch
 * Description:       See who is signed in right now, on which devices and from where. Spot accounts used from several IP addresses and sign out one device, one person or everyone else. No settings.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Rohan Salave
 * Author URI:        https://www.linkedin.com/in/rohan-salave-362475189/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       session-watch
 * Domain Path:       /languages
 *
 * @package SessionWatch
 */

defined( 'ABSPATH' ) || exit;

define( 'SESSION_WATCH_VERSION', '1.0.0' );
define( 'SESSION_WATCH_URL', plugin_dir_url( __FILE__ ) );
define( 'SESSION_WATCH_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Admin page slug, under Users.
 */
const SESSION_WATCH_PAGE = 'session-watch';

/**
 * People listed per page.
 */
const SESSION_WATCH_PER_PAGE = 20;

/**
 * Load translations shipped in /languages.
 */
function session_watch_load_textdomain() {
	load_plugin_textdomain( 'session-watch', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'session_watch_load_textdomain' );

/**
 * Capability needed to see sessions and end them. Sessions include IP
 * addresses, so the default is the same capability that edits users.
 *
 * @return string
 */
function session_watch_capability() {
	/**
	 * Filters the capability needed to open Users → Signed in.
	 *
	 * Ending a person's sessions also needs `edit_user` for that person.
	 *
	 * @param string $capability Default 'edit_users'.
	 */
	return (string) apply_filters( 'session_watch_capability', 'edit_users' );
}

/**
 * Whether sessions live in WordPress's default user meta storage, which is
 * the only storage this plugin knows how to read.
 *
 * @return bool
 */
function session_watch_storage_supported() {
	/** This filter is documented in wp-includes/class-wp-session-tokens.php */
	$manager = apply_filters( 'session_token_manager', 'WP_User_Meta_Session_Tokens' );

	return is_string( $manager ) && is_a( $manager, 'WP_User_Meta_Session_Tokens', true );
}

/**
 * Session storage for one user.
 *
 * @param int $user_id User ID.
 * @return Session_Watch_Tokens
 */
function session_watch_tokens( $user_id ) {
	require_once SESSION_WATCH_DIR . 'includes/class-session-watch-tokens.php';
	return Session_Watch_Tokens::for_user( $user_id );
}

/**
 * Number of different IP addresses at which an account is flagged.
 *
 * @return int
 */
function session_watch_ip_threshold() {
	/**
	 * Filters how many different IP addresses mark an account as
	 * "signed in from several places".
	 *
	 * @param int $threshold Default 2.
	 */
	return max( 2, (int) apply_filters( 'session_watch_ip_threshold', 2 ) );
}

/**
 * Every person with at least one active session, most recent sign-in first.
 *
 * Reads all `session_tokens` rows in one query instead of loading users.
 *
 * @return array[] Keyed by user ID. Each item has 'sessions', 'ips' and 'latest'.
 */
function session_watch_collect() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One read of a core meta key; nothing to cache across requests.
	$rows = $wpdb->get_results(
		$wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", 'session_tokens' )
	);

	$now    = time();
	$people = array();

	foreach ( (array) $rows as $row ) {
		$sessions = maybe_unserialize( $row->meta_value );
		if ( ! is_array( $sessions ) ) {
			continue;
		}

		$active = array();
		foreach ( $sessions as $verifier => $session ) {
			if ( is_array( $session ) && isset( $session['expiration'] ) && $session['expiration'] >= $now ) {
				$active[ (string) $verifier ] = $session;
			}
		}

		if ( ! $active ) {
			continue;
		}

		uasort(
			$active,
			static function ( $a, $b ) {
				return (int) ( $b['login'] ?? 0 ) <=> (int) ( $a['login'] ?? 0 );
			}
		);

		$ips = array_unique( array_filter( array_map( 'strval', wp_list_pluck( $active, 'ip' ) ) ) );

		$people[ (int) $row->user_id ] = array(
			'sessions' => $active,
			'ips'      => array_values( $ips ),
			'latest'   => (int) ( reset( $active )['login'] ?? 0 ),
		);
	}

	uasort(
		$people,
		static function ( $a, $b ) {
			return $b['latest'] <=> $a['latest'];
		}
	);

	return $people;
}

/**
 * A short, readable description of a browser's user agent string.
 *
 * @param string $agent User agent.
 * @return array{label: string, type: string} Type is 'desktop', 'phone' or 'tablet'.
 */
function session_watch_describe_agent( $agent ) {
	$agent = (string) $agent;

	$browsers = array(
		'Edge'             => '/Edg(e|A|iOS)?\//',
		'Opera'            => '/(OPR|Opera)\//',
		'Samsung Internet' => '/SamsungBrowser\//',
		'Firefox'          => '/(Firefox|FxiOS)\//',
		'Chrome'           => '/(Chrome|CriOS)\//',
		'Safari'           => '/Safari\//',
	);
	$systems  = array(
		'iPadOS'   => '/iPad/',
		'iOS'      => '/(iPhone|iPod)/',
		'Android'  => '/Android/',
		'ChromeOS' => '/CrOS/',
		'Windows'  => '/Windows/',
		'macOS'    => '/Mac OS X|Macintosh/',
		'Linux'    => '/Linux/',
	);

	$browser = '';
	foreach ( $browsers as $name => $pattern ) {
		if ( preg_match( $pattern, $agent ) ) {
			$browser = $name;
			break;
		}
	}

	$system = '';
	foreach ( $systems as $name => $pattern ) {
		if ( preg_match( $pattern, $agent ) ) {
			$system = $name;
			break;
		}
	}

	if ( 'iPadOS' === $system || ( 'Android' === $system && false === strpos( $agent, 'Mobile' ) ) ) {
		$type = 'tablet';
	} elseif ( in_array( $system, array( 'iOS', 'Android' ), true ) || false !== strpos( $agent, 'Mobile' ) ) {
		$type = 'phone';
	} else {
		$type = 'desktop';
	}

	if ( $browser && $system ) {
		/* translators: 1: browser name, 2: operating system. Example: "Chrome on Windows". */
		$label = sprintf( __( '%1$s on %2$s', 'session-watch' ), $browser, $system );
	} elseif ( $browser || $system ) {
		$label = $browser ? $browser : $system;
	} elseif ( '' !== $agent ) {
		$label = __( 'Other app or browser', 'session-watch' );
	} else {
		$label = __( 'Unknown device', 'session-watch' );
	}

	/**
	 * Filters how a session's device is described.
	 *
	 * @param array  $device Array with 'label' and 'type' ('desktop', 'phone' or 'tablet').
	 * @param string $agent  The raw user agent string.
	 */
	return apply_filters( 'session_watch_device', array( 'label' => $label, 'type' => $type ), $agent );
}

/**
 * The verifier of the session the current request is using.
 *
 * @return string
 */
function session_watch_current_verifier() {
	$token = wp_get_session_token();
	return $token ? hash( 'sha256', $token ) : '';
}

/**
 * URL of the Signed in screen.
 *
 * @param array $args Extra query arguments.
 * @return string
 */
function session_watch_page_url( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => SESSION_WATCH_PAGE ), $args ), admin_url( 'users.php' ) );
}

/**
 * Add Users → Signed in.
 */
function session_watch_menu() {
	$hook = add_users_page(
		__( 'Signed-in users', 'session-watch' ),
		__( 'Signed in', 'session-watch' ),
		session_watch_capability(),
		SESSION_WATCH_PAGE,
		'session_watch_render_page'
	);

	add_action(
		'admin_print_styles-' . $hook,
		static function () {
			wp_enqueue_style( 'session-watch', SESSION_WATCH_URL . 'assets/session-watch.css', array(), SESSION_WATCH_VERSION );
		}
	);
	add_action(
		'admin_print_scripts-' . $hook,
		static function () {
			wp_enqueue_script( 'session-watch', SESSION_WATCH_URL . 'assets/session-watch.js', array(), SESSION_WATCH_VERSION, true );
			wp_localize_script(
				'session-watch',
				'sessionWatch',
				array(
					/* translators: %s: person's name. */
					'confirmUser'   => __( 'Sign %s out on every device?', 'session-watch' ),
					'confirmOthers' => __( 'Sign out everyone except you? They will need to log in again.', 'session-watch' ),
				)
			);
		}
	);
}
add_action( 'admin_menu', 'session_watch_menu' );

/**
 * Stop unless the current user may manage sessions for this person.
 *
 * @param int $user_id User whose sessions are being ended.
 */
function session_watch_check_access( $user_id ) {
	if ( ! current_user_can( session_watch_capability() ) || ! current_user_can( 'edit_user', $user_id ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to end this person’s sessions.', 'session-watch' ), 403 );
	}
	if ( ! session_watch_storage_supported() ) {
		wp_die( esc_html__( 'Sessions on this site are stored by another plugin, so Session Watch cannot change them.', 'session-watch' ), 400 );
	}
}

/**
 * Go back to the Signed in screen with a result message.
 *
 * @param string $result Result code.
 * @param int    $count  Number of sessions ended.
 */
function session_watch_redirect( $result, $count ) {
	$back = isset( $_POST['_wp_http_referer'] ) ? esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- Checked by the caller.
	$back = wp_validate_redirect( $back, session_watch_page_url() );
	$back = remove_query_arg( array( 'session_watch', 'ended' ), $back );

	wp_safe_redirect(
		add_query_arg(
			array(
				'session_watch' => $result,
				'ended'         => (int) $count,
			),
			$back
		)
	);
	exit;
}

/**
 * End one session (one device).
 */
function session_watch_handle_end_one() {
	$user_id  = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	$verifier = isset( $_POST['verifier'] ) ? sanitize_key( wp_unslash( $_POST['verifier'] ) ) : '';

	check_admin_referer( 'session_watch_end_' . $user_id . '_' . $verifier );
	session_watch_check_access( $user_id );

	if ( ! preg_match( '/^[a-f0-9]{32,128}$/', $verifier ) ) {
		session_watch_redirect( 'missing', 0 );
	}

	if ( get_current_user_id() === $user_id && session_watch_current_verifier() === $verifier ) {
		session_watch_redirect( 'self', 0 );
	}

	$ended = session_watch_tokens( $user_id )->end_by_verifier( $verifier ) ? 1 : 0;

	if ( $ended ) {
		/**
		 * Fires after Session Watch ends sessions.
		 *
		 * @param int    $user_id User whose sessions were ended.
		 * @param int    $count   Number of sessions ended.
		 * @param string $scope   'one', 'user' or 'others'.
		 */
		do_action( 'session_watch_sessions_ended', $user_id, $ended, 'one' );
	}

	session_watch_redirect( $ended ? 'ended' : 'missing', $ended );
}
add_action( 'admin_post_session_watch_end_one', 'session_watch_handle_end_one' );

/**
 * End every session for one person. Your own current session is kept.
 */
function session_watch_handle_end_user() {
	$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

	check_admin_referer( 'session_watch_end_user_' . $user_id );
	session_watch_check_access( $user_id );

	$ended = session_watch_end_for_user( $user_id );

	if ( $ended ) {
		/** This action is documented in session-watch.php */
		do_action( 'session_watch_sessions_ended', $user_id, $ended, 'user' );
	}

	session_watch_redirect( $ended ? 'ended' : 'missing', $ended );
}
add_action( 'admin_post_session_watch_end_user', 'session_watch_handle_end_user' );

/**
 * Sign out everyone except the current session.
 */
function session_watch_handle_end_others() {
	check_admin_referer( 'session_watch_end_others' );
	session_watch_check_access( get_current_user_id() );

	$total = 0;
	foreach ( array_keys( session_watch_collect() ) as $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			continue;
		}

		$ended = session_watch_end_for_user( $user_id );
		if ( $ended ) {
			/** This action is documented in session-watch.php */
			do_action( 'session_watch_sessions_ended', $user_id, $ended, 'others' );
		}
		$total += $ended;
	}

	session_watch_redirect( 'ended', $total );
}
add_action( 'admin_post_session_watch_end_others', 'session_watch_handle_end_others' );

/**
 * End a person's sessions, keeping the one the current request uses.
 *
 * @param int $user_id User ID.
 * @return int Number of sessions ended.
 */
function session_watch_end_for_user( $user_id ) {
	$tokens = session_watch_tokens( $user_id );
	$keep   = get_current_user_id() === (int) $user_id ? session_watch_current_verifier() : '';
	$ended  = 0;

	foreach ( array_keys( $tokens->sessions_by_verifier() ) as $verifier ) {
		if ( $verifier !== $keep && $tokens->end_by_verifier( $verifier ) ) {
			++$ended;
		}
	}

	return $ended;
}

/**
 * Show the result of the last action.
 */
function session_watch_result_notice() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
	$result = isset( $_GET['session_watch'] ) ? sanitize_key( wp_unslash( $_GET['session_watch'] ) ) : '';
	$count  = isset( $_GET['ended'] ) ? absint( $_GET['ended'] ) : 0;
	// phpcs:enable

	if ( 'ended' === $result && $count ) {
		/* translators: %s: number of sessions. */
		$message = sprintf( _n( '%s session ended. That device will need to log in again.', '%s sessions ended. Those devices will need to log in again.', $count, 'session-watch' ), number_format_i18n( $count ) );
		$type    = 'success';
	} elseif ( 'ended' === $result || 'missing' === $result ) {
		$message = __( 'Nothing to end. The session had already expired or been signed out.', 'session-watch' );
		$type    = 'info';
	} elseif ( 'self' === $result ) {
		$message = __( 'That is the session you are using now. Use Log Out in the admin bar to end it.', 'session-watch' );
		$type    = 'warning';
	} else {
		return;
	}

	wp_admin_notice(
		esc_html( $message ),
		array(
			'type'        => $type,
			'dismissible' => true,
		)
	);
}

/**
 * Render one POST button that runs an action.
 *
 * @param string $action  admin-post action.
 * @param string $nonce   Nonce action.
 * @param array  $fields  Hidden fields.
 * @param string $label   Button text.
 * @param array  $attrs   Extra button attributes.
 */
function session_watch_action_button( $action, $nonce, $fields, $label, $attrs = array() ) {
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sw-action">';
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	foreach ( $fields as $name => $value ) {
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
	}
	wp_nonce_field( $nonce );

	$attributes = '';
	foreach ( $attrs as $name => $value ) {
		$attributes .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
	}
	echo '<button type="submit"' . $attributes . '>' . esc_html( $label ) . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput -- Attributes escaped above.
	echo '</form>';
}

/**
 * Format a timestamp as "3 hours ago" or "in 2 days", with the full date in the site timezone.
 *
 * @param int $timestamp Unix timestamp.
 * @return string HTML.
 */
function session_watch_time( $timestamp ) {
	$timestamp = (int) $timestamp;
	if ( ! $timestamp ) {
		return esc_html__( 'Unknown', 'session-watch' );
	}

	$diff = human_time_diff( $timestamp, time() );
	/* translators: %s: time span such as "3 hours". */
	$relative = $timestamp <= time() ? sprintf( __( '%s ago', 'session-watch' ), $diff ) : sprintf( __( 'in %s', 'session-watch' ), $diff );
	$full     = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );

	return '<time datetime="' . esc_attr( gmdate( 'c', $timestamp ) ) . '" title="' . esc_attr( $full ) . '">' . esc_html( $relative ) . '</time>';
}

/**
 * Render Users → Signed in.
 */
function session_watch_render_page() {
	if ( ! current_user_can( session_watch_capability() ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to view this page.', 'session-watch' ), 403 );
	}

	echo '<div class="wrap sw-wrap">';
	echo '<h1 class="wp-heading-inline">' . esc_html__( 'Signed-in users', 'session-watch' ) . '</h1>';

	if ( ! session_watch_storage_supported() ) {
		echo '<hr class="wp-header-end">';
		wp_admin_notice(
			esc_html__( 'Sessions on this site are stored by another plugin instead of the WordPress default, so Session Watch cannot read them.', 'session-watch' ),
			array( 'type' => 'warning' )
		);
		echo '</div>';
		return;
	}

	$people    = session_watch_collect();
	$threshold = session_watch_ip_threshold();
	$me        = get_current_user_id();
	$current   = session_watch_current_verifier();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
	$view   = isset( $_GET['view'] ) && 'several' === $_GET['view'] ? 'several' : 'all';
	$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
	$only   = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
	$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	// phpcs:enable

	$several = array_filter(
		$people,
		static function ( $person ) use ( $threshold ) {
			return count( $person['ips'] ) >= $threshold;
		}
	);

	$total_sessions = array_sum( array_map( 'count', wp_list_pluck( $people, 'sessions' ) ) );
	$others         = $total_sessions - ( isset( $people[ $me ] ) && $current && isset( $people[ $me ]['sessions'][ $current ] ) ? 1 : 0 );

	if ( $others > 0 && current_user_can( 'edit_users' ) ) {
		session_watch_action_button(
			'session_watch_end_others',
			'session_watch_end_others',
			array(),
			__( 'Sign out everyone else', 'session-watch' ),
			array(
				'class'          => 'page-title-action sw-end-others',
				'data-sw-others' => '1',
			)
		);
	}
	echo '<hr class="wp-header-end">';

	session_watch_result_notice();

	echo '<p class="sw-intro">' . esc_html__( 'Everyone who is logged in right now, with each browser or device they are using. Ending a session signs that device out straight away.', 'session-watch' ) . '</p>';

	// Summary.
	echo '<ul class="sw-stats">';
	printf(
		'<li class="sw-stat"><span class="sw-stat-number">%1$s</span><span class="sw-stat-label">%2$s</span></li>',
		esc_html( number_format_i18n( count( $people ) ) ),
		esc_html( _n( 'person signed in', 'people signed in', count( $people ), 'session-watch' ) )
	);
	printf(
		'<li class="sw-stat"><span class="sw-stat-number">%1$s</span><span class="sw-stat-label">%2$s</span></li>',
		esc_html( number_format_i18n( $total_sessions ) ),
		esc_html( _n( 'active session', 'active sessions', $total_sessions, 'session-watch' ) )
	);
	printf(
		'<li class="sw-stat%1$s"><span class="sw-stat-number">%2$s</span><span class="sw-stat-label">%3$s</span></li>',
		$several ? ' is-flagged' : '',
		esc_html( number_format_i18n( count( $several ) ) ),
		esc_html( _n( 'account on several IP addresses', 'accounts on several IP addresses', count( $several ), 'session-watch' ) )
	);
	echo '</ul>';

	// Filters.
	if ( $only ) {
		$list = isset( $people[ $only ] ) ? array( $only => $people[ $only ] ) : array();
	} else {
		$list = 'several' === $view ? $several : $people;
	}

	if ( '' !== $search ) {
		cache_users( array_keys( $list ) );
		$list = array_filter(
			$list,
			static function ( $user_id ) use ( $search ) {
				$user = get_userdata( $user_id );
				if ( ! $user ) {
					return false;
				}
				foreach ( array( $user->user_login, $user->user_email, $user->display_name ) as $field ) {
					if ( false !== stripos( (string) $field, $search ) ) {
						return true;
					}
				}
				return false;
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	echo '<div class="sw-toolbar">';
	echo '<ul class="subsubsub">';
	printf(
		'<li><a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a> |</li> ',
		esc_url( session_watch_page_url() ),
		'all' === $view && ! $only ? ' class="current" aria-current="page"' : '',
		esc_html__( 'All', 'session-watch' ),
		esc_html( number_format_i18n( count( $people ) ) )
	);
	printf(
		'<li><a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a></li>',
		esc_url( session_watch_page_url( array( 'view' => 'several' ) ) ),
		'several' === $view && ! $only ? ' class="current" aria-current="page"' : '',
		esc_html__( 'Several IP addresses', 'session-watch' ),
		esc_html( number_format_i18n( count( $several ) ) )
	);
	echo '</ul>';

	echo '<form method="get" class="search-box sw-search" role="search">';
	echo '<input type="hidden" name="page" value="' . esc_attr( SESSION_WATCH_PAGE ) . '">';
	if ( 'several' === $view ) {
		echo '<input type="hidden" name="view" value="several">';
	}
	echo '<label class="screen-reader-text" for="sw-search-input">' . esc_html__( 'Search signed-in users', 'session-watch' ) . '</label>';
	echo '<input type="search" id="sw-search-input" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Name, username or email', 'session-watch' ) . '">';
	submit_button( __( 'Search', 'session-watch' ), '', '', false );
	echo '</form>';
	echo '</div>';

	$total_pages = max( 1, (int) ceil( count( $list ) / SESSION_WATCH_PER_PAGE ) );
	$paged       = min( $paged, $total_pages );
	$page_people = array_slice( $list, ( $paged - 1 ) * SESSION_WATCH_PER_PAGE, SESSION_WATCH_PER_PAGE, true );

	cache_users( array_keys( $page_people ) );

	if ( ! $page_people ) {
		echo '<div class="sw-empty">';
		if ( '' !== $search || $only ) {
			esc_html_e( 'No signed-in users match.', 'session-watch' );
		} elseif ( 'several' === $view ) {
			esc_html_e( 'Nobody is signed in from more than one IP address right now.', 'session-watch' );
		} else {
			esc_html_e( 'Nobody is signed in right now.', 'session-watch' );
		}
		echo '</div></div>';
		return;
	}

	echo '<table class="widefat sw-table">';
	echo '<caption class="screen-reader-text">' . esc_html__( 'Signed-in users and their sessions', 'session-watch' ) . '</caption>';
	echo '<thead><tr>';
	echo '<th scope="col" class="sw-col-user">' . esc_html__( 'Person', 'session-watch' ) . '</th>';
	echo '<th scope="col" class="sw-col-sessions">' . esc_html__( 'Sessions', 'session-watch' ) . '</th>';
	echo '<th scope="col" class="sw-col-actions"><span class="screen-reader-text">' . esc_html__( 'Actions', 'session-watch' ) . '</span></th>';
	echo '</tr></thead><tbody>';

	foreach ( $page_people as $user_id => $person ) {
		session_watch_render_person( $user_id, $person, $threshold, $me, $current );
	}

	echo '</tbody></table>';

	if ( $total_pages > 1 ) {
		echo '<div class="tablenav bottom"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'    => add_query_arg( 'paged', '%#%', remove_query_arg( array( 'session_watch', 'ended' ) ) ),
					'format'  => '',
					'current' => $paged,
					'total'   => $total_pages,
				)
			)
		);
		echo '</div></div>';
	}

	echo '<p class="sw-footnote">' . esc_html__( 'Several IP addresses is a hint, not proof: phones switch between Wi-Fi and mobile data, and VPNs change addresses too.', 'session-watch' ) . '</p>';
	echo '</div>';
}

/**
 * Render one person's row.
 *
 * @param int    $user_id   User ID.
 * @param array  $person    Collected sessions.
 * @param int    $threshold IP threshold.
 * @param int    $me        Current user ID.
 * @param string $current   Current session verifier.
 */
function session_watch_render_person( $user_id, $person, $threshold, $me, $current ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return;
	}

	$can_edit = current_user_can( 'edit_user', $user_id );
	$roles    = array_map(
		static function ( $role ) {
			$names = wp_roles()->get_names();
			return isset( $names[ $role ] ) ? translate_user_role( $names[ $role ] ) : $role;
		},
		(array) $user->roles
	);
	$flagged  = count( $person['ips'] ) >= $threshold;

	echo '<tr class="sw-person' . ( $flagged ? ' is-flagged' : '' ) . '">';

	// Person.
	echo '<td class="sw-col-user">';
	echo '<div class="sw-user">' . get_avatar( $user_id, 40, '', '', array( 'class' => 'sw-avatar' ) );
	echo '<div><strong class="sw-name">';
	if ( $can_edit ) {
		echo '<a href="' . esc_url( get_edit_user_link( $user_id ) ) . '">' . esc_html( $user->display_name ) . '</a>';
	} else {
		echo esc_html( $user->display_name );
	}
	echo '</strong>';
	if ( $user_id === $me ) {
		echo ' <span class="sw-badge">' . esc_html__( 'You', 'session-watch' ) . '</span>';
	}
	echo '<span class="sw-meta">' . esc_html( $user->user_login . ( $roles ? ' · ' . implode( ', ', $roles ) : '' ) ) . '</span>';
	if ( $flagged ) {
		/* translators: %s: number of IP addresses. */
		echo '<span class="sw-flag">' . esc_html( sprintf( __( 'Signed in from %s IP addresses', 'session-watch' ), number_format_i18n( count( $person['ips'] ) ) ) ) . '</span>';
	}
	echo '</div></div></td>';

	// Sessions.
	echo '<td class="sw-col-sessions"><ul class="sw-sessions">';
	foreach ( $person['sessions'] as $verifier => $session ) {
		$device  = session_watch_describe_agent( $session['ua'] ?? '' );
		$is_this = $user_id === $me && $verifier === $current;

		echo '<li class="sw-session">';
		echo '<span class="sw-device dashicons dashicons-' . esc_attr( 'phone' === $device['type'] ? 'smartphone' : ( 'tablet' === $device['type'] ? 'tablet' : 'desktop' ) ) . '" aria-hidden="true"></span>';
		echo '<span class="sw-session-main"><span class="sw-device-label">' . esc_html( $device['label'] ) . '</span>';
		if ( $is_this ) {
			echo ' <span class="sw-badge sw-badge-this">' . esc_html__( 'This browser', 'session-watch' ) . '</span>';
		}
		echo '<span class="sw-session-meta">';
		echo '<span class="sw-ip">' . esc_html( ! empty( $session['ip'] ) ? $session['ip'] : __( 'IP unknown', 'session-watch' ) ) . '</span>';
		/* translators: %s: relative time such as "3 hours ago". */
		echo '<span>' . sprintf( esc_html__( 'Logged in %s', 'session-watch' ), session_watch_time( $session['login'] ?? 0 ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped in session_watch_time().
		/* translators: %s: relative time such as "in 2 days". */
		echo '<span>' . sprintf( esc_html__( 'Expires %s', 'session-watch' ), session_watch_time( $session['expiration'] ?? 0 ) ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- Escaped in session_watch_time().
		echo '</span></span>';

		if ( $can_edit && ! $is_this ) {
			session_watch_action_button(
				'session_watch_end_one',
				'session_watch_end_' . $user_id . '_' . $verifier,
				array(
					'user_id'  => $user_id,
					'verifier' => $verifier,
				),
				__( 'End', 'session-watch' ),
				array(
					'class'      => 'button button-small sw-end',
					/* translators: 1: device such as "Chrome on Windows", 2: person's name. */
					'aria-label' => sprintf( __( 'End the %1$s session for %2$s', 'session-watch' ), $device['label'], $user->display_name ),
				)
			);
		}
		echo '</li>';
	}
	echo '</ul></td>';

	// Actions.
	echo '<td class="sw-col-actions">';
	$count     = count( $person['sessions'] );
	$only_this = $user_id === $me && 1 === $count && isset( $person['sessions'][ $current ] );
	if ( $can_edit && ! $only_this ) {
		session_watch_action_button(
			'session_watch_end_user',
			'session_watch_end_user_' . $user_id,
			array( 'user_id' => $user_id ),
			$user_id === $me ? __( 'Sign out my other devices', 'session-watch' ) : __( 'Sign out everywhere', 'session-watch' ),
			array(
				'class'        => 'button sw-end-user',
				'data-sw-name' => $user_id === $me ? '' : $user->display_name,
			)
		);
	}
	echo '</td></tr>';
}

/**
 * Add a "Signed in" column to the Users list.
 *
 * @param string[] $columns Columns.
 * @return string[]
 */
function session_watch_users_column( $columns ) {
	if ( current_user_can( session_watch_capability() ) && session_watch_storage_supported() ) {
		$columns['session_watch'] = __( 'Signed in', 'session-watch' );
	}
	return $columns;
}
add_filter( 'manage_users_columns', 'session_watch_users_column' );

/**
 * Fill the "Signed in" column.
 *
 * @param string $output  Column output.
 * @param string $column  Column name.
 * @param int    $user_id User ID.
 * @return string
 */
function session_watch_users_column_value( $output, $column, $user_id ) {
	if ( 'session_watch' !== $column ) {
		return $output;
	}

	$count = count( session_watch_tokens( $user_id )->sessions_by_verifier() );
	if ( ! $count ) {
		return '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Not signed in', 'session-watch' ) . '</span>';
	}

	/* translators: %s: number of sessions. */
	$label = sprintf( _n( '%s session', '%s sessions', $count, 'session-watch' ), number_format_i18n( $count ) );
	return '<a href="' . esc_url( session_watch_page_url( array( 'user_id' => $user_id ) ) ) . '">' . esc_html( $label ) . '</a>';
}
add_filter( 'manage_users_custom_column', 'session_watch_users_column_value', 10, 3 );

/**
 * Link to the screen from the Plugins list.
 *
 * @param string[] $links Action links.
 * @return string[]
 */
function session_watch_action_links( $links ) {
	if ( current_user_can( session_watch_capability() ) ) {
		array_unshift( $links, '<a href="' . esc_url( session_watch_page_url() ) . '">' . esc_html__( 'View sessions', 'session-watch' ) . '</a>' );
	}
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'session_watch_action_links' );
