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

	/**
	 * Whether this request will actually be dispatched to the MCP REST route.
	 *
	 * This must mirror how WordPress resolves the route, not just what the
	 * URL path looks like: WP::parse_request() gives $_POST/$_GET precedence
	 * over the rewrite-derived value, so `rest_route` in the query string
	 * overrides the pretty-permalink match. Checking only REQUEST_URI would
	 * let `POST /wp-json/mcp/wp-mcp-ultimate?rest_route=/wp/v2/users` — which
	 * dispatches to wp/v2 — still authenticate with an MCP-scoped token.
	 */
	private static function is_mcp_request(): bool {
		$mcp_route = '/mcp/wp-mcp-ultimate';

		// Same precedence WordPress applies: POST, then GET, then the URL path.
		$rest_route = null;
		if ( isset( $_POST['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- routing check only, no state change.
			$rest_route = (string) wp_unslash( $_POST['rest_route'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( isset( $_GET['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing check only, no state change.
			$rest_route = (string) wp_unslash( $_GET['rest_route'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( null !== $rest_route ) {
			return 0 === strpos( $rest_route, $mcp_route );
		}

		// No rest_route override: derive the route from the path after the
		// REST prefix, exactly as the rewrite rule would.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$prefix      = '/' . rest_get_url_prefix() . '/';
		$pos         = strpos( $path, $prefix );
		if ( false === $pos ) {
			return false;
		}

		$route = substr( $path, $pos + strlen( $prefix ) - 1 ); // Keep leading slash.
		return 0 === strpos( $route, $mcp_route );
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
