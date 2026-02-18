<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities;

/**
 * Reusable JSON Schema definitions for ability input/output schemas.
 */
final class SchemaDefinitions {

    public const PAGINATION = [
        'per_page' => [
            'description' => 'Number of items per page',
            'type'        => 'integer',
            'minimum'     => 1,
            'maximum'     => 100,
            'default'     => 20,
        ],
        'page' => [
            'description' => 'Page number',
            'type'        => 'integer',
            'minimum'     => 1,
            'default'     => 1,
        ],
    ];

    public const ORDER = [
        'orderby' => [
            'description' => 'Sort field',
            'type'        => 'string',
            'enum'        => ['date', 'title', 'name', 'ID', 'modified', 'menu_order'],
            'default'     => 'date',
        ],
        'order' => [
            'description' => 'Sort order',
            'type'        => 'string',
            'enum'        => ['ASC', 'DESC'],
            'default'     => 'DESC',
        ],
    ];

    public const STATUS = [
        'status' => [
            'description' => 'Post status filter',
            'type'        => 'string',
            'enum'        => ['publish', 'draft', 'pending', 'private', 'trash', 'any'],
            'default'     => 'publish',
        ],
    ];

    public const POST_OUTPUT = [
        'id'       => ['type' => 'integer'],
        'title'    => ['type' => 'string'],
        'slug'     => ['type' => 'string'],
        'status'   => ['type' => 'string'],
        'date'     => ['type' => 'string', 'format' => 'date-time'],
        'modified' => ['type' => 'string', 'format' => 'date-time'],
        'link'     => ['type' => 'string', 'format' => 'uri'],
    ];

    public const POST_TYPE = [
        'post_type' => [
            'description' => 'Post type to query',
            'type'        => 'string',
            'default'     => 'post',
        ],
    ];

    public const AUTHOR = [
        'author_id' => [
            'description' => 'Filter by author ID',
            'type'        => 'integer',
        ],
    ];

    public const SEARCH = [
        'search' => [
            'description' => 'Search keyword',
            'type'        => 'string',
        ],
    ];

    public const SUCCESS_MESSAGE = [
        'success' => ['type' => 'boolean'],
        'message' => ['type' => 'string'],
    ];

    public const PLUGIN_FILE = [
        'plugin' => [
            'description' => 'Plugin file (directory/main-file.php)',
            'type'        => 'string',
        ],
    ];

    public const USER_ID = [
        'id' => [
            'description' => 'User ID',
            'type'        => 'integer',
        ],
    ];

    public const MENU_ID = [
        'menu_id' => [
            'description' => 'Menu ID',
            'type'        => 'integer',
        ],
    ];

    public const MEDIA_ID = [
        'id' => [
            'description' => 'Media/Attachment ID',
            'type'        => 'integer',
        ],
    ];

    public const TITLE = [
        'title' => [
            'description' => 'Title',
            'type'        => 'string',
        ],
    ];

    public const CONTENT = [
        'content' => [
            'description' => 'Content',
            'type'        => 'string',
        ],
    ];
}
