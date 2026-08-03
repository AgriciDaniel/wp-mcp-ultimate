<?php
declare(strict_types=1);

namespace WpMcpUltimate\OAuth;

/**
 * Authorization endpoint: OAuth 2.1 authorization_code + PKCE(S256).
 *
 * GET  — validates the request, requires WP login, then shows a consent
 *        screen (or re-shows the same URL after redirecting through
 *        wp_login_url() if the visitor isn't logged in yet).
 * POST — processes the visitor's Allow/Deny decision from that screen.
 */
final class AuthorizationEndpoint {

	private const NONCE_ACTION = 'wp_mcp_oauth_authorize';

	public static function handle(): void {
		$params = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ? $_POST : $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce is verified explicitly below for POST.

		$client_id     = isset( $params['client_id'] ) ? sanitize_text_field( wp_unslash( $params['client_id'] ) ) : '';
		$redirect_uri  = isset( $params['redirect_uri'] ) ? esc_url_raw( wp_unslash( $params['redirect_uri'] ) ) : '';
		$response_type = isset( $params['response_type'] ) ? sanitize_text_field( wp_unslash( $params['response_type'] ) ) : '';
		$state         = isset( $params['state'] ) ? sanitize_text_field( wp_unslash( $params['state'] ) ) : '';
		$scope         = isset( $params['scope'] ) ? sanitize_text_field( wp_unslash( $params['scope'] ) ) : 'mcp';
		$code_challenge        = isset( $params['code_challenge'] ) ? sanitize_text_field( wp_unslash( $params['code_challenge'] ) ) : '';
		$code_challenge_method = isset( $params['code_challenge_method'] ) ? sanitize_text_field( wp_unslash( $params['code_challenge_method'] ) ) : '';

		// Unknown client or unregistered redirect_uri: never redirect, show a page instead.
		$client = '' !== $client_id ? TokenStore::get_client( $client_id ) : null;
		if ( ! $client ) {
			Responses::html_error( 'Unknown client', 'This application is not registered with this server.' );
		}
		if ( '' === $redirect_uri || ! in_array( $redirect_uri, $client['redirect_uris'], true ) ) {
			Responses::html_error( 'Invalid redirect_uri', 'The redirect_uri does not match this client\'s registered redirect URIs.' );
		}

		// From here on, validation failures can be safely reported back to the client via redirect.
		if ( 'code' !== $response_type ) {
			Responses::redirect_with_params( $redirect_uri, array( 'error' => 'unsupported_response_type', 'state' => $state ) );
		}
		if ( '' === $code_challenge || 'S256' !== $code_challenge_method ) {
			Responses::redirect_with_params( $redirect_uri, array( 'error' => 'invalid_request', 'error_description' => 'PKCE with S256 is required.', 'state' => $state ) );
		}

		if ( ! is_user_logged_in() ) {
			$current_url = ( is_ssl() ? 'https://' : 'http://' ) . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '' );
			wp_safe_redirect( wp_login_url( $current_url ) );
			exit;
		}

		$is_post = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' );

		if ( $is_post ) {
			self::handle_consent_decision( $client_id, $redirect_uri, $state, $scope, $code_challenge );
			return;
		}

		self::render_consent_page( $client, $client_id, $redirect_uri, $response_type, $state, $scope, $code_challenge, $code_challenge_method );
	}

	private static function handle_consent_decision( string $client_id, string $redirect_uri, string $state, string $scope, string $code_challenge ): void {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			Responses::html_error( 'Request expired', 'This authorization request has expired. Please try connecting again.', 400 );
		}

		$decision = isset( $_POST['wp_mcp_oauth_consent'] ) ? sanitize_text_field( wp_unslash( $_POST['wp_mcp_oauth_consent'] ) ) : '';

		if ( 'allow' !== $decision ) {
			Responses::redirect_with_params( $redirect_uri, array( 'error' => 'access_denied', 'state' => $state ) );
		}

		$code = TokenStore::generate_token();
		TokenStore::store_code(
			$code,
			array(
				'client_id'     => $client_id,
				'user_id'       => get_current_user_id(),
				'redirect_uri'  => $redirect_uri,
				'code_challenge' => $code_challenge,
				'scope'         => $scope,
			)
		);

		$redirect_params = array( 'code' => $code );
		if ( '' !== $state ) {
			$redirect_params['state'] = $state;
		}

		Responses::redirect_with_params( $redirect_uri, $redirect_params );
	}

	private static function render_consent_page( array $client, string $client_id, string $redirect_uri, string $response_type, string $state, string $scope, string $code_challenge, string $code_challenge_method ): void {
		$user       = wp_get_current_user();
		$site_name  = get_bloginfo( 'name' );
		$client_name = $client['client_name'] ?? 'This application';

		nocache_headers();
		Responses::send_frame_busting_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo esc_html__( 'Authorize application', 'wp-mcp-ultimate' ); ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
	body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f0f0f1;margin:0;padding:2.5rem 1rem;color:#1d2327}
	.card{max-width:26rem;margin:0 auto;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:2rem}
	h1{font-size:1.1rem;margin:0 0 .5rem}
	p{color:#50575e;line-height:1.5;font-size:.9rem}
	.actor{font-weight:600;color:#1d2327}
	.actions{display:flex;gap:.75rem;margin-top:1.5rem}
	button{flex:1;padding:.6rem 1rem;border-radius:4px;border:1px solid #c3c4c7;font-size:.9rem;cursor:pointer}
	button[name="wp_mcp_oauth_consent"][value="allow"]{background:#2271b1;border-color:#2271b1;color:#fff}
	button[name="wp_mcp_oauth_consent"][value="deny"]{background:#fff}
</style>
</head>
<body>
	<div class="card">
		<h1><?php echo esc_html( sprintf( /* translators: %s: client application name */ __( '%s wants to access your site', 'wp-mcp-ultimate' ), $client_name ) ); ?></h1>
		<p><?php
		printf(
			/* translators: 1: client application name, 2: site name, 3: WordPress username */
			esc_html__( '%1$s will be able to use the WP MCP Ultimate tools on %2$s as %3$s, with the same permissions as your account.', 'wp-mcp-ultimate' ),
			'<span class="actor">' . esc_html( $client_name ) . '</span>',
			'<span class="actor">' . esc_html( $site_name ) . '</span>',
			'<span class="actor">' . esc_html( $user->user_login ) . '</span>'
		);
		?></p>
		<p><?php echo esc_html__( 'The application name above is self-reported by the connecting application. Only continue if you initiated this connection.', 'wp-mcp-ultimate' ); ?></p>
		<form method="post" action="<?php echo esc_url( home_url( '/wp-mcp-oauth/authorize' ) ); ?>">
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<input type="hidden" name="client_id" value="<?php echo esc_attr( $client_id ); ?>">
			<input type="hidden" name="redirect_uri" value="<?php echo esc_attr( $redirect_uri ); ?>">
			<input type="hidden" name="response_type" value="<?php echo esc_attr( $response_type ); ?>">
			<input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>">
			<input type="hidden" name="scope" value="<?php echo esc_attr( $scope ); ?>">
			<input type="hidden" name="code_challenge" value="<?php echo esc_attr( $code_challenge ); ?>">
			<input type="hidden" name="code_challenge_method" value="<?php echo esc_attr( $code_challenge_method ); ?>">
			<div class="actions">
				<button type="submit" name="wp_mcp_oauth_consent" value="deny"><?php echo esc_html__( 'Deny', 'wp-mcp-ultimate' ); ?></button>
				<button type="submit" name="wp_mcp_oauth_consent" value="allow"><?php echo esc_html__( 'Allow', 'wp-mcp-ultimate' ); ?></button>
			</div>
		</form>
	</div>
</body>
</html>
		<?php
		exit;
	}
}
