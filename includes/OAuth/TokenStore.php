<?php
declare(strict_types=1);

namespace WpMcpUltimate\OAuth;

/**
 * Storage for OAuth clients, authorization codes, access tokens, and
 * refresh tokens. Codes/tokens live in transients (auto-expiring);
 * registered clients live in a single option (few, long-lived).
 */
final class TokenStore {

	private const CODE_TTL       = 120;       // 2 minutes.
	private const ACCESS_TTL     = 3600;      // 1 hour.
	private const REFRESH_TTL    = 2592000;   // 30 days.
	private const CLIENTS_OPTION = 'wp_mcp_ultimate_oauth_clients';
	private const MAX_CLIENTS    = 100;

	public static function generate_token(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	// ---- Clients --------------------------------------------------------

	public static function get_client( string $client_id ): ?array {
		$clients = get_option( self::CLIENTS_OPTION, array() );
		return $clients[ $client_id ] ?? null;
	}

	public static function save_client( string $client_id, array $client ): void {
		$clients               = get_option( self::CLIENTS_OPTION, array() );
		$clients[ $client_id ] = $client;

		// Registration is unauthenticated, so bound the stored set: keep the
		// most recently created MAX_CLIENTS and drop the oldest beyond that.
		if ( count( $clients ) > self::MAX_CLIENTS ) {
			uasort(
				$clients,
				static function ( array $a, array $b ): int {
					return ( $b['created'] ?? 0 ) <=> ( $a['created'] ?? 0 );
				}
			);
			$clients = array_slice( $clients, 0, self::MAX_CLIENTS, true );
		}

		update_option( self::CLIENTS_OPTION, $clients, false );
	}

	// ---- Authorization codes ---------------------------------------------

	public static function store_code( string $code, array $payload ): void {
		set_transient( 'mcp_oauth_code_' . hash( 'sha256', $code ), $payload, self::CODE_TTL );
	}

	/**
	 * Fetch and immediately invalidate a code (single use).
	 */
	public static function consume_code( string $code ): ?array {
		$key     = 'mcp_oauth_code_' . hash( 'sha256', $code );
		$payload = get_transient( $key );
		if ( false === $payload ) {
			return null;
		}
		delete_transient( $key );
		return $payload;
	}

	// ---- Access tokens ----------------------------------------------------

	public static function store_access_token( string $token, array $payload ): void {
		set_transient( 'mcp_oauth_at_' . hash( 'sha256', $token ), $payload, self::ACCESS_TTL );
	}

	public static function get_access_token( string $token ): ?array {
		$payload = get_transient( 'mcp_oauth_at_' . hash( 'sha256', $token ) );
		return false === $payload ? null : $payload;
	}

	public static function access_token_ttl(): int {
		return self::ACCESS_TTL;
	}

	// ---- Refresh tokens ----------------------------------------------------

	public static function store_refresh_token( string $token, array $payload ): void {
		set_transient( 'mcp_oauth_rt_' . hash( 'sha256', $token ), $payload, self::REFRESH_TTL );
	}

	/**
	 * Fetch and immediately invalidate a refresh token (rotation on use).
	 */
	public static function consume_refresh_token( string $token ): ?array {
		$key     = 'mcp_oauth_rt_' . hash( 'sha256', $token );
		$payload = get_transient( $key );
		if ( false === $payload ) {
			return null;
		}
		delete_transient( $key );
		return $payload;
	}
}
