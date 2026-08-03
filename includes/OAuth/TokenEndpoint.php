<?php
declare(strict_types=1);

namespace WpMcpUltimate\OAuth;

/**
 * Token endpoint: exchanges an authorization code (with PKCE) or a
 * refresh token for a Bearer access token.
 */
final class TokenEndpoint {

	public static function handle(): void {
		Responses::maybe_handle_preflight();

		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			Responses::oauth_error( 'invalid_request', 'Only POST is supported.', 405 );
		}

		$params      = self::parse_body();
		$grant_type  = $params['grant_type'] ?? '';

		if ( 'authorization_code' === $grant_type ) {
			self::handle_authorization_code_grant( $params );
			return;
		}

		if ( 'refresh_token' === $grant_type ) {
			self::handle_refresh_token_grant( $params );
			return;
		}

		Responses::oauth_error( 'unsupported_grant_type', 'grant_type must be authorization_code or refresh_token.' );
	}

	private static function handle_authorization_code_grant( array $params ): void {
		$code          = (string) ( $params['code'] ?? '' );
		$redirect_uri  = (string) ( $params['redirect_uri'] ?? '' );
		$client_id     = (string) ( $params['client_id'] ?? '' );
		$code_verifier = (string) ( $params['code_verifier'] ?? '' );

		if ( '' === $code || '' === $code_verifier ) {
			Responses::oauth_error( 'invalid_request', 'code and code_verifier are required.' );
		}

		$payload = TokenStore::consume_code( $code );
		if ( ! $payload ) {
			Responses::oauth_error( 'invalid_grant', 'The authorization code is invalid, expired, or already used.' );
		}

		if ( $payload['client_id'] !== $client_id || $payload['redirect_uri'] !== $redirect_uri ) {
			Responses::oauth_error( 'invalid_grant', 'client_id or redirect_uri does not match the original authorization request.' );
		}

		$computed_challenge = rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' );
		if ( ! hash_equals( $payload['code_challenge'], $computed_challenge ) ) {
			Responses::oauth_error( 'invalid_grant', 'code_verifier does not match the original code_challenge.' );
		}

		self::issue_tokens( $client_id, (int) $payload['user_id'], (string) $payload['scope'] );
	}

	private static function handle_refresh_token_grant( array $params ): void {
		$refresh_token = (string) ( $params['refresh_token'] ?? '' );
		$client_id     = (string) ( $params['client_id'] ?? '' );

		if ( '' === $refresh_token ) {
			Responses::oauth_error( 'invalid_request', 'refresh_token is required.' );
		}

		// OAuth 2.1 requires public clients to authenticate the token request
		// by sending client_id on every call.
		if ( '' === $client_id ) {
			Responses::oauth_error( 'invalid_request', 'client_id is required.' );
		}

		$payload = TokenStore::consume_refresh_token( $refresh_token );
		if ( ! $payload ) {
			Responses::oauth_error( 'invalid_grant', 'The refresh token is invalid or expired.' );
		}

		if ( $payload['client_id'] !== $client_id ) {
			Responses::oauth_error( 'invalid_grant', 'client_id does not match the original grant.' );
		}

		self::issue_tokens( (string) $payload['client_id'], (int) $payload['user_id'], (string) $payload['scope'] );
	}

	private static function issue_tokens( string $client_id, int $user_id, string $scope ): void {
		$access_token  = TokenStore::generate_token();
		$refresh_token = TokenStore::generate_token();

		$token_payload = array(
			'client_id' => $client_id,
			'user_id'   => $user_id,
			'scope'     => $scope,
		);

		TokenStore::store_access_token( $access_token, $token_payload );
		TokenStore::store_refresh_token( $refresh_token, $token_payload );

		Responses::json(
			array(
				'access_token'  => $access_token,
				'token_type'    => 'Bearer',
				'expires_in'    => TokenStore::access_token_ttl(),
				'refresh_token' => $refresh_token,
				'scope'         => $scope,
			)
		);
	}

	private static function parse_body(): array {
		// Some SAPIs expose the header only as HTTP_CONTENT_TYPE.
		$content_type = $_SERVER['CONTENT_TYPE'] ?? ( $_SERVER['HTTP_CONTENT_TYPE'] ?? '' );

		if ( false !== stripos( $content_type, 'application/json' ) ) {
			$decoded = json_decode( (string) file_get_contents( 'php://input' ), true );
			return is_array( $decoded ) ? $decoded : array();
		}

		// application/x-www-form-urlencoded (the RFC 6749-mandated content type).
		return $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- token endpoint is a machine-to-machine OAuth grant exchange, not a WP form submission.
	}
}
