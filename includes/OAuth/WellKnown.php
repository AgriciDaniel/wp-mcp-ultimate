<?php
declare(strict_types=1);

namespace WpMcpUltimate\OAuth;

/**
 * Serves OAuth Authorization Server Metadata (RFC 8414) and Protected
 * Resource Metadata (RFC 9728).
 *
 * Both documents are, per spec, expected at the true origin root. This
 * site's WordPress install lives under a subdirectory (e.g. /blog) with
 * an unrelated application at the domain root, so the plugin cannot own
 * the true root — it serves both documents relative to home_url()
 * instead, which is the common fallback most MCP/OAuth clients (and
 * WordPress plugins in the same situation) accept.
 */
final class WellKnown {

	public static function handle( string $which ): void {
		if ( 'authorization-server' === $which ) {
			self::send_authorization_server_metadata();
			return;
		}

		self::send_protected_resource_metadata();
	}

	private static function send_authorization_server_metadata(): void {
		$origin = untrailingslashit( home_url() );

		Responses::json(
			array(
				'issuer'                                 => $origin,
				'authorization_endpoint'                 => $origin . '/authorize',
				'token_endpoint'                          => $origin . '/oauth/token',
				'registration_endpoint'                   => $origin . '/oauth/register',
				'response_types_supported'                => array( 'code' ),
				'grant_types_supported'                   => array( 'authorization_code', 'refresh_token' ),
				'code_challenge_methods_supported'         => array( 'S256' ),
				'token_endpoint_auth_methods_supported'    => array( 'none' ),
				'scopes_supported'                         => array( 'mcp' ),
			)
		);
	}

	private static function send_protected_resource_metadata(): void {
		$origin = untrailingslashit( home_url() );

		Responses::json(
			array(
				'resource'               => rest_url( 'mcp/wp-mcp-ultimate' ),
				'authorization_servers'  => array( $origin ),
			)
		);
	}
}
