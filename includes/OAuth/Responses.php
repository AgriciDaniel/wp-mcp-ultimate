<?php
declare(strict_types=1);

namespace WpMcpUltimate\OAuth;

/**
 * Shared raw HTTP response helpers for the OAuth endpoints, which run
 * outside the REST API so they can fully control status/headers/body.
 */
final class Responses {

	/**
	 * Answers a CORS preflight request and exits, if this is one.
	 * A no-op (returns without exiting) for any other method.
	 */
	public static function maybe_handle_preflight( string $allowed_methods = 'POST, OPTIONS' ): void {
		if ( 'OPTIONS' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		nocache_headers();
		status_header( 204 );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: ' . $allowed_methods );
		header( 'Access-Control-Allow-Headers: Content-Type, Authorization' );
		header( 'Access-Control-Max-Age: 86400' );
		exit;
	}

	public static function json( array $data, int $status = 200 ): void {
		nocache_headers();
		status_header( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		echo wp_json_encode( $data );
		exit;
	}

	/**
	 * OAuth-shaped error body per RFC 6749 section 5.2 / 7591 section 3.2.2.
	 */
	public static function oauth_error( string $error, string $description = '', int $status = 400 ): void {
		$body = array( 'error' => $error );
		if ( '' !== $description ) {
			$body['error_description'] = $description;
		}
		self::json( $body, $status );
	}

	/**
	 * Plain HTML error page for cases where redirecting back to an
	 * unvalidated/unregistered redirect_uri would be unsafe.
	 */
	/**
	 * Emit anti-framing headers. These OAuth HTML pages (consent, errors)
	 * render outside WordPress's admin/login screens, so core's
	 * send_frame_options_header() never runs for them. Without this an
	 * attacker could iframe the consent screen and clickjack an admin into
	 * approving a client.
	 */
	public static function send_frame_busting_headers(): void {
		header( 'X-Frame-Options: DENY' );
		header( "Content-Security-Policy: frame-ancestors 'none'" );
	}

	public static function html_error( string $title, string $message, int $status = 400 ): void {
		nocache_headers();
		self::send_frame_busting_headers();
		status_header( $status );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html( $title ) . '</title>'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<style>body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;max-width:32rem;margin:4rem auto;padding:0 1.5rem;color:#1d2327}'
			. 'h1{font-size:1.25rem}p{color:#50575e;line-height:1.5}</style></head><body>'
			. '<h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $message ) . '</p></body></html>';
		exit;
	}

	/**
	 * Redirects to an OAuth client's redirect_uri. Uses wp_redirect()
	 * rather than wp_safe_redirect() on purpose: the target is an
	 * arbitrary third-party host (e.g. claude.ai) by design, and by the
	 * time this is called the caller has already validated the URI
	 * against the client's registered redirect_uris — it isn't raw,
	 * unvalidated user input.
	 */
	public static function redirect_with_params( string $uri, array $params ): void {
		wp_redirect( add_query_arg( $params, $uri ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- see docblock: destination is pre-validated against the OAuth client's registered redirect_uris, not attacker-controlled.
		exit;
	}
}
