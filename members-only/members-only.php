<?php
/**
 * Plugin Name:       Members Only
 * Plugin URI:        https://github.com/memberfixstudio-ind/wordpress-toolbox/tree/main/members-only
 * Description:       A block that shows what's inside it only to signed-in visitors, signed-out visitors, chosen roles, MemberPress members or LearnDash students. Everyone else sees a short sign-in prompt, or nothing. No settings.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Rohan Salave
 * Author URI:        https://www.linkedin.com/in/rohan-salave-362475189/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       members-only
 * Domain Path:       /languages
 *
 * @package MembersOnly
 */

defined( 'ABSPATH' ) || exit;

define( 'MEMBERS_ONLY_VERSION', '1.0.0' );
define( 'MEMBERS_ONLY_URL', plugin_dir_url( __FILE__ ) );
define( 'MEMBERS_ONLY_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Audiences the block understands.
 */
const MEMBERS_ONLY_AUDIENCES = array( 'members', 'guests', 'roles', 'memberships', 'courses' );

/**
 * Load translations shipped in /languages.
 */
function members_only_load_textdomain() {
	load_plugin_textdomain( 'members-only', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'members_only_load_textdomain', 0 );

/**
 * Register the assets and the block.
 */
function members_only_register() {
	wp_register_style( 'members-only', MEMBERS_ONLY_URL . 'assets/style.css', array(), MEMBERS_ONLY_VERSION );
	wp_register_style( 'members-only-editor', MEMBERS_ONLY_URL . 'assets/editor.css', array(), MEMBERS_ONLY_VERSION );

	wp_register_script(
		'members-only-editor',
		MEMBERS_ONLY_URL . 'assets/editor.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
		MEMBERS_ONLY_VERSION,
		true
	);
	wp_set_script_translations( 'members-only-editor', 'members-only', MEMBERS_ONLY_DIR . 'languages' );

	register_block_type(
		MEMBERS_ONLY_DIR . 'block.json',
		array(
			'render_callback' => 'members_only_render',
		)
	);
}
add_action( 'init', 'members_only_register' );

/**
 * Give the editor the roles, memberships and courses it can offer.
 *
 * Runs only in the block editor, so nothing is queried on other screens.
 */
function members_only_editor_data() {
	$data = array(
		'roles'        => members_only_role_choices(),
		'memberships'  => members_only_post_choices( 'memberpressproduct', class_exists( 'MeprUser' ) ),
		'courses'      => members_only_post_choices( 'sfwd-courses', function_exists( 'sfwd_lms_has_access' ) ),
		'hasMepr'      => class_exists( 'MeprUser' ),
		'hasLearnDash' => function_exists( 'sfwd_lms_has_access' ),
	);

	wp_add_inline_script(
		'members-only-editor',
		'window.membersOnlyData = ' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP ) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'members_only_editor_data' );

/**
 * Roles as value/label pairs, in the site's own order.
 *
 * @return array[]
 */
function members_only_role_choices() {
	$choices = array();
	foreach ( wp_roles()->get_names() as $slug => $name ) {
		$choices[] = array(
			'value' => (string) $slug,
			'label' => translate_user_role( $name ),
		);
	}
	return $choices;
}

/**
 * Published posts of a type as value/label pairs, for the membership and course pickers.
 *
 * @param string $post_type Post type.
 * @param bool   $available Whether the plugin behind the post type is active.
 * @return array[]
 */
function members_only_post_choices( $post_type, $available ) {
	if ( ! $available || ! post_type_exists( $post_type ) ) {
		return array();
	}

	$ids = get_posts(
		array(
			'post_type'              => $post_type,
			'post_status'            => 'publish',
			'numberposts'            => 200,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	$choices = array();
	foreach ( $ids as $id ) {
		$title     = trim( wp_strip_all_tags( get_the_title( $id ) ) );
		$choices[] = array(
			'value' => (int) $id,
			/* translators: %d: post ID. */
			'label' => '' !== $title ? $title : sprintf( __( 'Untitled (#%d)', 'members-only' ), $id ),
		);
	}
	return $choices;
}

/**
 * Normalize the block attributes, since saved markup can be edited by hand.
 *
 * @param array $attributes Raw attributes.
 * @return array
 */
function members_only_attributes( $attributes ) {
	$audience = isset( $attributes['audience'] ) ? (string) $attributes['audience'] : 'members';

	return array(
		'audience'    => in_array( $audience, MEMBERS_ONLY_AUDIENCES, true ) ? $audience : 'members',
		'roles'       => array_values( array_filter( array_map( 'sanitize_key', (array) ( $attributes['roles'] ?? array() ) ) ) ),
		'memberships' => array_values( array_filter( array_map( 'absint', (array) ( $attributes['memberships'] ?? array() ) ) ) ),
		'courses'     => array_values( array_filter( array_map( 'absint', (array) ( $attributes['courses'] ?? array() ) ) ) ),
		'showPrompt'  => ! isset( $attributes['showPrompt'] ) || (bool) $attributes['showPrompt'],
		'promptText'  => isset( $attributes['promptText'] ) ? (string) $attributes['promptText'] : '',
	);
}

/**
 * Whether a user may see the section.
 *
 * Fails closed: an audience whose plugin is missing, or one with nothing
 * chosen, shows the section to nobody.
 *
 * @param array $args    Normalized attributes.
 * @param int   $user_id User ID, 0 for a signed-out visitor.
 * @return bool
 */
function members_only_user_can_view( $args, $user_id ) {
	$can = false;

	switch ( $args['audience'] ) {
		case 'guests':
			$can = 0 === $user_id;
			break;

		case 'members':
			$can = $user_id > 0;
			break;

		case 'roles':
			if ( $user_id > 0 && $args['roles'] ) {
				$user = get_userdata( $user_id );
				$can  = $user && array_intersect( $args['roles'], (array) $user->roles );
			}
			break;

		case 'memberships':
			if ( $user_id > 0 && $args['memberships'] && class_exists( 'MeprUser' ) ) {
				try {
					$member = new MeprUser( $user_id );
					if ( method_exists( $member, 'is_already_subscribed_to' ) ) {
						foreach ( $args['memberships'] as $membership_id ) {
							if ( $member->is_already_subscribed_to( $membership_id ) ) {
								$can = true;
								break;
							}
						}
					}
				} catch ( Throwable $e ) {
					$can = false;
				}
			}
			break;

		case 'courses':
			if ( $user_id > 0 && $args['courses'] && function_exists( 'sfwd_lms_has_access' ) ) {
				foreach ( $args['courses'] as $course_id ) {
					if ( sfwd_lms_has_access( $course_id, $user_id ) ) {
						$can = true;
						break;
					}
				}
			}
			break;
	}

	/**
	 * Filters whether a user may see a Members Only section.
	 *
	 * @param bool  $can     Whether the section is shown.
	 * @param array $args    Normalized block attributes (audience, roles, memberships, courses, ...).
	 * @param int   $user_id User ID, 0 for a signed-out visitor.
	 */
	return (bool) apply_filters( 'members_only_can_view', (bool) $can, $args, $user_id );
}

/**
 * Who the section is for, in words, for the badge editors see on the front end.
 *
 * @param array $args Normalized attributes.
 * @return string
 */
function members_only_audience_label( $args ) {
	switch ( $args['audience'] ) {
		case 'guests':
			return __( 'signed-out visitors', 'members-only' );
		case 'roles':
			$names = array();
			$roles = wp_roles()->get_names();
			foreach ( $args['roles'] as $slug ) {
				$names[] = isset( $roles[ $slug ] ) ? translate_user_role( $roles[ $slug ] ) : $slug;
			}
			return $names ? implode( ', ', $names ) : __( 'nobody (no roles chosen)', 'members-only' );
		case 'memberships':
			if ( ! $args['memberships'] ) {
				return __( 'nobody (no memberships chosen)', 'members-only' );
			}
			/* translators: %s: membership names. */
			return sprintf( __( 'members of %s', 'members-only' ), members_only_titles( $args['memberships'] ) );
		case 'courses':
			if ( ! $args['courses'] ) {
				return __( 'nobody (no courses chosen)', 'members-only' );
			}
			/* translators: %s: course names. */
			return sprintf( __( 'students of %s', 'members-only' ), members_only_titles( $args['courses'] ) );
		default:
			return __( 'signed-in visitors', 'members-only' );
	}
}

/**
 * Post titles joined with commas, for the editor badge.
 *
 * @param int[] $ids Post IDs.
 * @return string
 */
function members_only_titles( $ids ) {
	$titles = array();
	foreach ( $ids as $id ) {
		$title    = trim( wp_strip_all_tags( get_the_title( $id ) ) );
		$titles[] = '' !== $title ? $title : '#' . $id;
	}
	return implode( ', ', $titles );
}

/**
 * Render callback.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Inner blocks, already rendered.
 * @param WP_Block $block      Block instance.
 * @return string
 */
function members_only_render( $attributes, $content, $block = null ) {
	$args    = members_only_attributes( $attributes );
	$user_id = get_current_user_id();

	if ( members_only_user_can_view( $args, $user_id ) ) {
		return $content;
	}

	// People who can edit this post still see the section, marked, so they can check it.
	$post_id = ( $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) ? (int) $block->context['postId'] : (int) get_the_ID();
	$editor  = $post_id ? current_user_can( 'edit_post', $post_id ) : current_user_can( 'edit_theme_options' );

	/**
	 * Filters whether people who can edit the post see hidden sections, with a label.
	 *
	 * @param bool  $show    Default true for people who can edit the post.
	 * @param array $args    Normalized block attributes.
	 * @param int   $post_id Post the block belongs to, 0 in templates.
	 */
	if ( $editor && apply_filters( 'members_only_show_to_editors', true, $args, $post_id ) ) {
		wp_enqueue_style( 'members-only' );
		return sprintf(
			'<div class="members-only-preview"><p class="members-only-preview__badge">%1$s</p>%2$s</div>',
			esc_html(
				sprintf(
					/* translators: %s: who can see the section, e.g. "Signed-out visitors". */
					__( 'Members Only: shown to %s. You see it because you can edit this page.', 'members-only' ),
					members_only_audience_label( $args )
				)
			),
			$content
		);
	}

	// Signed-out visitors never need a prompt for a section meant for signed-out visitors.
	if ( ! $args['showPrompt'] || 'guests' === $args['audience'] ) {
		return '';
	}

	return members_only_prompt( $args, $user_id, $post_id );
}

/**
 * The box shown in place of a hidden section.
 *
 * @param array $args    Normalized attributes.
 * @param int   $user_id Current user ID.
 * @param int   $post_id Current post ID.
 * @return string
 */
function members_only_prompt( $args, $user_id, $post_id ) {
	wp_enqueue_style( 'members-only' );

	if ( '' !== trim( $args['promptText'] ) ) {
		$message = $args['promptText'];
	} elseif ( $user_id ) {
		$message = __( "This section is for members with different access. If you think you should see it, contact the site's team.", 'members-only' );
	} else {
		$message = __( 'This section is for members. Log in to see it.', 'members-only' );
	}

	// Send people back to the page they were on, which may be an archive or a template.
	global $wp;
	$return = ( $wp instanceof WP ) ? home_url( add_query_arg( array(), $wp->request ) ) : '';
	if ( is_singular() && $post_id ) {
		$return = get_permalink( $post_id );
	}
	$links  = '';
	if ( ! $user_id ) {
		$links .= sprintf(
			'<a class="members-only-prompt__button" href="%1$s">%2$s</a>',
			esc_url( wp_login_url( $return ) ),
			esc_html__( 'Log in', 'members-only' )
		);
		if ( get_option( 'users_can_register' ) ) {
			$links .= sprintf(
				' <a class="members-only-prompt__link" href="%1$s">%2$s</a>',
				esc_url( wp_registration_url() ),
				esc_html__( 'Create an account', 'members-only' )
			);
		}
	}

	$html = sprintf(
		'<div class="members-only-prompt" role="note"><svg class="members-only-prompt__icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="22" height="22"><path fill="currentColor" d="M17 10h-1V7a4 4 0 0 0-8 0v3H7a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2Zm-7-3a2 2 0 1 1 4 0v3h-4Zm3 9.73V18h-2v-1.27a2 2 0 1 1 2 0Z"/></svg><p class="members-only-prompt__text">%1$s</p>%2$s</div>',
		esc_html( $message ),
		$links ? '<p class="members-only-prompt__actions">' . $links . '</p>' : ''
	);

	/**
	 * Filters the prompt shown in place of a hidden section. Return '' to show nothing.
	 *
	 * @param string $html    Prompt markup.
	 * @param array  $args    Normalized block attributes.
	 * @param int    $user_id Current user ID, 0 when signed out.
	 */
	return (string) apply_filters( 'members_only_prompt_html', $html, $args, $user_id );
}
