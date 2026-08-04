<?php
declare(strict_types=1);

namespace WpMcpUltimate\OAuth;

/**
 * Bootstraps the OAuth 2.1 authorization server bundled with this
 * plugin, so MCP clients that require OAuth (e.g. Claude's Connectors)
 * can authenticate instead of using an Application Password header
 * directly.
 *
 * Routes are matched by hand against REQUEST_URI on 'init' rather than
 * WordPress rewrite rules, so they work immediately without requiring
 * the site owner to re-save Permalinks after updating the plugin.
 */
final class Server {

	public static function init(): void {
		BearerAuth::init();
		add_action( 'init', array( self::class, 'route' ), 0 );
		add_filter( 'rest_post_dispatch', array( self::class, 'add_www_authenticate_header' ), 10, 3 );
	}

	/**
	 * Advertises where to discover this server's OAuth metadata on an
	 * unauthenticated (401) request to the MCP endpoint, per RFC 9728
	 * section 5.1 — the standard way MCP clients locate the
	 * authorization server without needing it hardcoded or guessed.
	 *
	 * @param \WP_REST_Response|\WP_HTTP_Response|\WP_Error $response
	 */
	public static function add_www_authenticate_header( $response, \WP_REST_Server $server, \WP_REST_Request $request ) {
		if ( ! ( $response instanceof \WP_REST_Response ) || 401 !== $response->get_status() ) {
			return $response;
		}

		if ( ! str_ends_with( untrailingslashit( $request->get_route() ), '/mcp/wp-mcp-ultimate' ) ) {
			return $response;
		}

		$response->header(
			'WWW-Authenticate',
			'Bearer resource_metadata="' . home_url( '/.well-known/oauth-protected-resource' ) . '"'
		);

		return $response;
	}

	// Only the two /.well-known documents are location-constrained (clients
	// derive them from the issuer origin). The interactive/machine endpoints
	// are discovered from the metadata, so they live under a plugin-specific
	// prefix that cannot shadow a real WordPress page slug like /authorize.
	private const ROUTES = array(
		'/.well-known/oauth-authorization-server',
		'/.well-known/oauth-protected-resource',
		'/wp-mcp-oauth/authorize',
		'/wp-mcp-oauth/token',
		'/wp-mcp-oauth/register',
	);

	public static function route(): void {
		$path = self::relative_request_path();
		if ( null === $path || ! in_array( $path, self::ROUTES, true ) ) {
			return;
		}

		// These responses are per-request/dynamic (auth codes, tokens,
		// login redirects) and must never be served from a page cache.
		self::prevent_caching();

		switch ( $path ) {
			case '/.well-known/oauth-authorization-server':
				WellKnown::handle( 'authorization-server' );
				return;
			case '/.well-known/oauth-protected-resource':
				WellKnown::handle( 'protected-resource' );
				return;
			case '/wp-mcp-oauth/authorize':
				AuthorizationEndpoint::handle();
				return;
			case '/wp-mcp-oauth/token':
				TokenEndpoint::handle();
				return;
			case '/wp-mcp-oauth/register':
				RegistrationEndpoint::handle();
				return;
		}
	}

	private static function prevent_caching(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			// Convention respected by LiteSpeed Cache, WP Super Cache, W3 Total Cache, WP Rocket, etc.
			define( 'DONOTCACHEPAGE', true );
		}
		do_action( 'litespeed_control_set_nocache', 'wp-mcp-ultimate oauth endpoint' );
		nocache_headers();
	}

	/**
	 * The request path relative to this site's home_url(), or null if
	 * the request isn't under this site at all (shouldn't normally
	 * happen, but WordPress can be reached via alternate hostnames).
	 */
	private static function relative_request_path(): ?string {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$request_path = '' === $request_path ? '/' : rtrim( $request_path, '/' );

		$home_path = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		$home_path = rtrim( $home_path, '/' );

		if ( '' !== $home_path && 0 !== strpos( $request_path, $home_path ) ) {
			return null;
		}

		$relative = substr( $request_path, strlen( $home_path ) );
		return '' === $relative ? '/' : $relative;
	}
}
