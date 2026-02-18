<?php
/**
 * Transport context object for dependency injection.
 *
 * @package McpAdapter
 */

declare( strict_types=1 );

namespace WpMcpUltimate\Server\Transport\Infrastructure;

use WpMcpUltimate\Server\McpServer;
use WpMcpUltimate\Server\Handlers\Initialize\InitializeHandler;
use WpMcpUltimate\Server\Handlers\Prompts\PromptsHandler;
use WpMcpUltimate\Server\Handlers\Resources\ResourcesHandler;
use WpMcpUltimate\Server\Handlers\System\SystemHandler;
use WpMcpUltimate\Server\Handlers\Tools\ToolsHandler;
use WpMcpUltimate\Server\Infrastructure\ErrorHandling\Contracts\McpErrorHandlerInterface;
use WpMcpUltimate\Server\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface;

/**
 * Transport context object for dependency injection.
 *
 * Contains all dependencies needed by transport implementations,
 * promoting loose coupling and easier testing.
 *
 * Note: The request_router parameter is optional. If not provided,
 * a RequestRouter instance will be automatically created with this
 * context as its dependency.
 */
class McpTransportContext {

	/**
	 * Initialize the transport context.
	 *
	 * @param \WpMcpUltimate\Server\Core\McpServer             $mcp_server The MCP server instance.
	 * @param \WpMcpUltimate\Server\Handlers\Initialize\InitializeHandler     $initialize_handler The initialize handler.
	 * @param \WpMcpUltimate\Server\Handlers\Tools\ToolsHandler          $tools_handler The tools handler.
	 * @param \WpMcpUltimate\Server\Handlers\Resources\ResourcesHandler      $resources_handler The resources handler.
	 * @param \WpMcpUltimate\Server\Handlers\Prompts\PromptsHandler        $prompts_handler The prompts handler.
	 * @param \WpMcpUltimate\Server\Handlers\System\SystemHandler         $system_handler The system handler.
	 * @param string                $observability_handler The observability handler class name.
	 * @param \WpMcpUltimate\Server\Transport\Infrastructure\RequestRouter|null $request_router The request router service.
	 * @param callable|null         $transport_permission_callback Optional custom permission callback for transport-level authentication.
	 */
	/**
	 * The MCP server instance.
	 *
	 * @var \WpMcpUltimate\Server\Core\McpServer
	 */
	public McpServer $mcp_server;

	/**
	 * The initialize handler.
	 *
	 * @var \WpMcpUltimate\Server\Handlers\Initialize\InitializeHandler
	 */
	public InitializeHandler $initialize_handler;

	/**
	 * The tools handler.
	 *
	 * @var \WpMcpUltimate\Server\Handlers\Tools\ToolsHandler
	 */
	public ToolsHandler $tools_handler;

	/**
	 * The resources handler.
	 *
	 * @var \WpMcpUltimate\Server\Handlers\Resources\ResourcesHandler
	 */
	public ResourcesHandler $resources_handler;

	/**
	 * The prompts handler.
	 *
	 * @var \WpMcpUltimate\Server\Handlers\Prompts\PromptsHandler
	 */
	public PromptsHandler $prompts_handler;

	/**
	 * The system handler.
	 *
	 * @var \WpMcpUltimate\Server\Handlers\System\SystemHandler
	 */
	public SystemHandler $system_handler;

	/**
	 * The observability handler instance.
	 *
	 * @var \WpMcpUltimate\Server\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface
	 */
	public McpObservabilityHandlerInterface $observability_handler;

	/**
	 * The error handler instance.
	 *
	 * @var \WpMcpUltimate\Server\Infrastructure\ErrorHandling\Contracts\McpErrorHandlerInterface
	 */
	public McpErrorHandlerInterface $error_handler;

	/**
	 * The request router service.
	 */
	public RequestRouter $request_router;

	/**
	 * Optional custom permission callback for transport-level authentication.
	 *
	 * @var callable|callable-string|null
	 */
	public $transport_permission_callback;

	/**
	 * Initialize the transport context.
	 *
	 * @param array{
	 *   mcp_server: \WpMcpUltimate\Server\Core\McpServer,
	 *   initialize_handler: \WpMcpUltimate\Server\Handlers\Initialize\InitializeHandler,
	 *   tools_handler: \WpMcpUltimate\Server\Handlers\Tools\ToolsHandler,
	 *   resources_handler: \WpMcpUltimate\Server\Handlers\Resources\ResourcesHandler,
	 *   prompts_handler: \WpMcpUltimate\Server\Handlers\Prompts\PromptsHandler,
	 *   system_handler: \WpMcpUltimate\Server\Handlers\System\SystemHandler,
	 *   observability_handler: \WpMcpUltimate\Server\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface,
	 *   request_router?: \WpMcpUltimate\Server\Transport\Infrastructure\RequestRouter,
	 *   transport_permission_callback?: callable|null,
	 *   error_handler?: \WpMcpUltimate\Server\Infrastructure\ErrorHandling\Contracts\McpErrorHandlerInterface
	 * } $properties Properties to set on the context.
	 * Note: request_router is optional and will be auto-created if not provided.
	 */
	public function __construct( array $properties ) {
		foreach ( $properties as $name => $value ) {
			$this->$name = $value;
		}

		// If request_router is provided, we're done
		if ( isset( $properties['request_router'] ) ) {
			return;
		}

		// Create request_router if not provided
		$this->request_router = new RequestRouter( $this );
	}
}
