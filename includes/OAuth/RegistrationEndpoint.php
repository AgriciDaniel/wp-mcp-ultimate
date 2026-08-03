<?php
declare(strict_types=1);

namespace WpMcpUltimate\OAuth;

/**
 * Dynamic Client Registration (RFC 7591), public clients only (PKCE,
 * no client secret) — matches how MCP clients register themselves.
 */
final class RegistrationEndpoint {

	private const RATE_LIMIT         = 5;      // Registrations per IP per window.
	private const RATE_LIMIT_WINDOW  = 3600;   // 1 hour.
	private const MAX_REDIRECT_URIS  = 5;      // Per client.
	private const MAX_URI_LENGTH     = 2000;   // Per redirect_uri.
	private const MAX_CLIENT_NAME    = 200;    // Characters.

	public static function handle(): void {
		Responses::maybe_handle_preflight();

		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			Responses::oauth_error( 'invalid_request', 'Only POST is supported.', 405 );
		}

		// Registration is unauthenticated by design (RFC 7591 public
		// clients), so it must be bounded: rate-limit per IP and cap the
		// total number of stored clients (TokenStore prunes the oldest).
		// NOTE: REMOTE_ADDR is the direct peer; behind a reverse proxy or CDN
		// every visitor may share one address, making this a per-window cap
		// for the whole site rather than per client. That is intentionally
		// fail-safe (it can only over-limit, never under-limit).
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		if ( '' !== $ip ) {
			// Fixed window keyed by the current time bucket, so a busy window
			// cannot slide its own expiry forward and lock the endpoint
			// indefinitely — each bucket expires on its own.
			$bucket   = (int) floor( time() / self::RATE_LIMIT_WINDOW );
			$rate_key = 'mcp_oauth_reg_' . md5( $ip ) . '_' . $bucket;
			$attempts = (int) get_transient( $rate_key );
			if ( $attempts >= self::RATE_LIMIT ) {
				Responses::oauth_error( 'invalid_request', 'Too many registration attempts. Try again later.', 429 );
			}
			set_transient( $rate_key, $attempts + 1, self::RATE_LIMIT_WINDOW );
		}

		$raw  = file_get_contents( 'php://input' );
		$body = json_decode( (string) $raw, true );

		if ( ! is_array( $body ) ) {
			Responses::oauth_error( 'invalid_client_metadata', 'Request body must be a JSON object.' );
		}

		$redirect_uris = $body['redirect_uris'] ?? null;
		if ( ! is_array( $redirect_uris ) || empty( $redirect_uris ) ) {
			Responses::oauth_error( 'invalid_redirect_uri', 'redirect_uris is required and must be a non-empty array.' );
		}
		if ( count( $redirect_uris ) > self::MAX_REDIRECT_URIS ) {
			Responses::oauth_error( 'invalid_redirect_uri', 'Too many redirect_uris.' );
		}

		foreach ( $redirect_uris as $uri ) {
			if ( ! is_string( $uri ) || strlen( $uri ) > self::MAX_URI_LENGTH || ! self::is_valid_redirect_uri( $uri ) ) {
				Responses::oauth_error( 'invalid_redirect_uri', 'Every redirect_uri must be an https:// URL, or an http:// loopback URL.' );
			}
		}

		$client_name = isset( $body['client_name'] ) && is_string( $body['client_name'] )
			? sanitize_text_field( mb_substr( $body['client_name'], 0, self::MAX_CLIENT_NAME ) )
			: 'MCP Client';

		$client_id = bin2hex( random_bytes( 16 ) );
		$issued_at = time();

		TokenStore::save_client(
			$client_id,
			array(
				'client_name'   => $client_name,
				'redirect_uris' => array_values( $redirect_uris ),
				'created'       => $issued_at,
			)
		);

		Responses::json(
			array(
				'client_id'                  => $client_id,
				'client_id_issued_at'        => $issued_at,
				'client_name'                => $client_name,
				'redirect_uris'              => array_values( $redirect_uris ),
				'grant_types'                => array( 'authorization_code', 'refresh_token' ),
				'response_types'             => array( 'code' ),
				'token_endpoint_auth_method' => 'none',
			),
			201
		);
	}

	public static function is_valid_redirect_uri( string $uri ): bool {
		$parts = wp_parse_url( $uri );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		if ( 'https' === $parts['scheme'] ) {
			return true;
		}

		// RFC 8252: native/local clients get an http loopback exception.
		if ( 'http' === $parts['scheme'] && in_array( $parts['host'], array( '127.0.0.1', 'localhost', '::1' ), true ) ) {
			return true;
		}

		return false;
	}
}
