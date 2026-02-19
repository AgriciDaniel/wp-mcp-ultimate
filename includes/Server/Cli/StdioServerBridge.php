<?php
/**
 * STDIO Server Bridge for MCP Adapter
 *
 * @package WpMcpUltimate\Server\Cli
 */

declare( strict_types=1 );

namespace WpMcpUltimate\Server\Cli;

use WpMcpUltimate\Server\McpServer;
use WpMcpUltimate\Server\Transport\Infrastructure\RequestRouter;

/**
 * STDIO Server Bridge - Exposes MCP servers via STDIO protocol
 *
 * Handles JSON-RPC communication over stdin/stdout and delegates
 * requests to the appropriate MCP server.
 */
class StdioServerBridge {

	/**
	 * The MCP server to expose via STDIO.
	 *
	 * @var \WpMcpUltimate\Server\McpServer
	 */
	private McpServer $server;

	/**
	 * Request router for handling MCP requests.
	 *
	 * @var \WpMcpUltimate\Server\Transport\Infrastructure\RequestRouter
	 */
	private RequestRouter $request_router;

	/**
	 * Whether the bridge is currently running.
	 *
	 * @var bool
	 */
	private bool $is_running = false;

	/**
	 * Initialize the STDIO server bridge.
	 *
	 * @param \WpMcpUltimate\Server\McpServer $server The MCP server to expose.
	 */
	public function __construct( McpServer $server ) {
		$this->server         = $server;
		$this->request_router = $this->create_request_router();
	}

	/**
	 * Start the STDIO server bridge.
	 *
	 * Reads JSON-RPC messages from stdin and writes responses to stdout.
	 * Runs in a loop until terminated or EOF.
	 *
	 * @throws \RuntimeException If STDIO transport is disabled.
	 */
	public function serve(): void {
		$enable_serve = apply_filters( 'mcp_adapter_enable_stdio_transport', true );

		if ( ! $enable_serve ) {
			throw new \RuntimeException(
				'The STDIO transport is disabled. Enable it by setting the "mcp_adapter_enable_stdio_transport" filter to true.'
			);
		}

		$this->is_running = true;

		$this->log_to_stderr( sprintf( 'MCP STDIO Bridge started for server: %s', $this->server->get_server_id() ) );

		while ( $this->is_running ) {
			try {
				$input = fgets( STDIN );

				if ( false === $input ) {
					break;
				}

				$input = rtrim( $input, "\r\n" );

				if ( empty( $input ) ) {
					continue;
				}

				$response = $this->handle_request( $input );

				if ( ! empty( $response ) ) {
					fwrite( STDOUT, $response . "\n" ); // phpcs:ignore
					fflush( STDOUT );
				}
			} catch ( \Throwable $e ) {
				$this->log_to_stderr( 'Error processing request: ' . $e->getMessage() );

				$error_response = wp_json_encode(
					array(
						'jsonrpc' => '2.0',
						'error'   => array(
							'code'    => -32603,
							'message' => 'Internal error',
							'data'    => array(
								'details' => $e->getMessage(),
							),
						),
						'id'      => null,
					)
				);

				fwrite( STDOUT, $error_response . "\n" ); // phpcs:ignore
				fflush( STDOUT );
			}
		}

		$this->log_to_stderr( 'MCP STDIO Bridge stopped' );
	}

	/**
	 * Stop the STDIO server bridge.
	 */
	public function stop(): void {
		$this->is_running = false;
	}

	/**
	 * Handle a JSON-RPC request string.
	 *
	 * @param string $json_input The JSON-RPC request string.
	 * @return string The JSON-RPC response string (empty for notifications).
	 */
	private function handle_request( string $json_input ): string {
		try {
			$request = json_decode( $json_input, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return $this->create_error_response( null, -32700, 'Parse error', 'Invalid JSON was received by the server.' );
			}

			if ( ! is_array( $request ) ) {
				return $this->create_error_response( null, -32600, 'Invalid Request', 'The JSON sent is not a valid Request object.' );
			}

			if ( ! isset( $request['jsonrpc'] ) || '2.0' !== $request['jsonrpc'] ) {
				return $this->create_error_response( $request['id'] ?? null, -32600, 'Invalid Request', 'The JSON-RPC version must be 2.0.' );
			}

			$method = $request['method'] ?? null;
			$params = $request['params'] ?? array();
			$id     = $request['id'] ?? null;

			if ( ! is_string( $method ) ) {
				return $this->create_error_response( $id, -32600, 'Invalid Request', 'Method must be a string.' );
			}

			if ( is_object( $params ) ) {
				$params = (array) $params;
			}

			if ( ! is_array( $params ) ) {
				$params = array();
			}

			$result = $this->request_router->route_request( $method, $params, $id, 'stdio' );

			if ( null === $id ) {
				return '';
			}

			return $this->format_response( $result, $id );
		} catch ( \Throwable $e ) {
			return $this->create_error_response( null, -32603, 'Internal error', $e->getMessage() );
		}
	}

	/**
	 * Format a handler result as a JSON-RPC response.
	 *
	 * @param array $result The handler result.
	 * @param mixed $id     The request ID.
	 * @return string The JSON-RPC response string.
	 */
	private function format_response( array $result, $id ): string {
		$response = array(
			'jsonrpc' => '2.0',
			'id'      => $id,
		);

		if ( isset( $result['error'] ) ) {
			$error             = $result['error'];
			$response['error'] = array(
				'code'    => $error['code'] ?? -32603,
				'message' => $error['message'] ?? 'Internal error',
			);

			if ( isset( $error['data'] ) ) {
				$response['error']['data'] = $error['data'];
			}
		} else {
			$response['result'] = (object) $result;
		}

		$json = wp_json_encode( $response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $json ) {
			return $this->create_error_response( $id, -32603, 'Internal error', 'Failed to encode response as JSON.' );
		}

		return $json;
	}

	/**
	 * Create a JSON-RPC error response.
	 *
	 * @param mixed  $id      The request ID.
	 * @param int    $code    The error code.
	 * @param string $message The error message.
	 * @param string $data    Optional error data.
	 * @return string The JSON error response string.
	 */
	private function create_error_response( $id, int $code, string $message, string $data = '' ): string {
		$response = array(
			'jsonrpc' => '2.0',
			'error'   => array(
				'code'    => $code,
				'message' => $message,
			),
			'id'      => $id,
		);

		if ( ! empty( $data ) ) {
			$response['error']['data'] = $data;
		}

		return wp_json_encode( $response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ?: '{"jsonrpc":"2.0","error":{"code":-32603,"message":"Internal error"},"id":null}';
	}

	/**
	 * Create a request router for the server.
	 *
	 * @return \WpMcpUltimate\Server\Transport\Infrastructure\RequestRouter
	 */
	private function create_request_router(): RequestRouter {
		$context = $this->server->create_transport_context();
		return $context->request_router;
	}

	/**
	 * Log a message to stderr.
	 *
	 * @param string $message The message to log.
	 */
	private function log_to_stderr( string $message ): void {
		fwrite( STDERR, "[MCP STDIO Bridge] $message\n" ); // phpcs:ignore
	}

	/**
	 * Get the server this bridge is exposing.
	 *
	 * @return \WpMcpUltimate\Server\McpServer
	 */
	public function get_server(): McpServer {
		return $this->server;
	}
}
