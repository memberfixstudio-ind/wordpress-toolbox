<?php
/**
 * Access to WordPress's default session storage.
 *
 * WordPress keeps each user's sessions in the `session_tokens` user meta,
 * keyed by a hash of the cookie token (the "verifier"). Core can only end a
 * session when it has the raw token, which only the browser holding it knows.
 * This subclass reuses core's own storage code to end one session by its
 * verifier, so an admin can sign out a single device.
 *
 * @package SessionWatch
 */

defined( 'ABSPATH' ) || exit;

/**
 * Session storage for one user, with verifier-level access.
 */
final class Session_Watch_Tokens extends WP_User_Meta_Session_Tokens {

	/**
	 * Get the session storage for a user.
	 *
	 * @param int $user_id User ID.
	 * @return Session_Watch_Tokens
	 */
	public static function for_user( $user_id ) {
		return new self( (int) $user_id );
	}

	/**
	 * Active sessions keyed by verifier. Expired sessions are left out.
	 *
	 * @return array[]
	 */
	public function sessions_by_verifier() {
		$sessions = $this->get_sessions();
		$now      = time();

		return array_filter(
			$sessions,
			static function ( $session ) use ( $now ) {
				return is_array( $session ) && isset( $session['expiration'] ) && $session['expiration'] >= $now;
			}
		);
	}

	/**
	 * End one session.
	 *
	 * @param string $verifier Hashed session token.
	 * @return bool True when a session was found and removed.
	 */
	public function end_by_verifier( $verifier ) {
		$sessions = $this->get_sessions();

		if ( ! isset( $sessions[ $verifier ] ) ) {
			return false;
		}

		$this->update_session( $verifier, null );
		return true;
	}
}
