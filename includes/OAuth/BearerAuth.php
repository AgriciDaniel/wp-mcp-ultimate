<?php
declare(strict_types=1);

namespace WpMcpUltimate\OAuth;

/**
 * Resolves an `Authorization: Bearer <token>` header issued by our own
 * token endpoint into a WordPress user, so OAuth-authenticated requests
 * reach the MCP transport's existing current_user_can() checks exactly
 * like Application Password (Basic Auth) requests already do.
 */
final class BearerAuth {

	public static function init(): void {
		add_filter( 'determine_current_user', array( self::class, 'authenticate' ), 20 );
	}

	/**
	 * @param int|false $user_id
	 * @return int|false
	 */
	public static function authenticate( $user_id ) {
		// Another auth method (cookie, Application Password) already resolved a user.
		if ( ! empty( $user_id ) ) {
			return $user_id;
		}

		// Tokens are consented for MCP access only, so they must not
		// authenticate arbitrary WordPress requests (wp/v2 REST, admin-ajax).
		if ( ! self::is_mcp_request() ) {
			return $user_id;
		}

		$header = self::get_authorization_header();
		if ( '' === $header || 0 !== stripos( $header, 'Bearer ' ) ) {
			return $user_id;
		}

		$token   = trim( substr( $header, 7 ) );
		$payload = '' !== $token ? TokenStore::get_access_token( $token ) : null;

		if ( ! $payload || empty( $payload['user_id'] ) ) {
			return $user_id;
		}

		return (int) $payload['user_id'];
	}

	private static function is_mcp_request(): bool {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( false !== strpos( $path, '/' . rest_get_url_prefix() . '/mcp/wp-mcp-ultimate' ) ) {
			return true;
		}

		// Plain-permalink installs route REST through ?rest_route=.
		if ( isset( $_GET['rest_route'] ) && 0 === strpos( (string) wp_unslash( $_GET['rest_route'] ), '/mcp/wp-mcp-ultimate' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing check only, no state change.
			return true;
		}

		return false;
	}

	private static function get_authorization_header(): string {
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}

		// Some hosts (e.g. behind FastCGI/PHP-FPM) strip the Authorization
		// header unless redirected via .htaccess; check the redirected copy too.
		if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		return '';
	}
}
