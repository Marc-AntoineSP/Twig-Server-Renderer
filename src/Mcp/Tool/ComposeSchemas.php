<?php
namespace App\Mcp\Tool;
/** Explicit bounded schemas; maintained by tools/mcp/generate-tool-schemas.py. */
final class ComposeSchemas
{
    public const INPUT = [
        'capabilities' => [
            'type' => 'object',
            'required' => [],
            'additionalProperties' => false,
        ],
        'search_catalogue' => [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'maxLength' => 120],
                'kind' => [
                    'type' => 'string',
                    'enum' => ['component', 'block', 'layout'],
                ],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
            'required' => [],
            'additionalProperties' => false,
        ],
        'list_preview_themes' => [
            'type' => 'object',
            'properties' => [
                'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
            'required' => [],
            'additionalProperties' => false,
        ],
        'get_reference' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$', 'maxLength' => 100],
                'version' => ['type' => 'string', 'pattern' => '^(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)$'],
            ],
            'required' => ['name', 'version'],
            'additionalProperties' => false,
        ],
        'get' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
            ],
            'required' => ['compositionId'],
            'additionalProperties' => false,
        ],
        'get_node' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'nodeId' => ['type' => 'string', 'pattern' => '^n_[a-f0-9]{32}$'],
                'depth' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                'maxNodes' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
            'required' => ['compositionId', 'nodeId'],
            'additionalProperties' => false,
        ],
        'create_draft' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$', 'maxLength' => 100],
                'kind' => [
                    'type' => 'string',
                    'enum' => ['block', 'layout'],
                ],
                'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                'description' => ['type' => 'string', 'maxLength' => 2000],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
            ],
            'required' => ['name', 'kind', 'title', 'operationId'],
            'additionalProperties' => false,
        ],
        'apply_batch' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'expectedRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'commands' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => 50,
                    'items' => [
                        'oneOf' => [
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'add_container'],
                                    'parentId' => ['type' => 'string', 'pattern' => '^(n_[a-f0-9]{32}|\\$[a-z][a-z0-9_]{0,31})$'],
                                    'position' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 500],
                                    'tag' => [
                                        'type' => 'string',
                                        'enum' => ['div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav'],
                                    ],
                                    'classes' => [
                                        'type' => 'array',
                                        'maxItems' => 100,
                                        'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                                    ],
                                    'alias' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]{0,31}$'],
                                ],
                                'required' => ['type', 'parentId', 'position'],
                                'additionalProperties' => false,
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'add_text'],
                                    'parentId' => ['type' => 'string', 'pattern' => '^(n_[a-f0-9]{32}|\\$[a-z][a-z0-9_]{0,31})$'],
                                    'position' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 500],
                                    'value' => ['type' => 'string', 'maxLength' => 10000],
                                    'alias' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]{0,31}$'],
                                ],
                                'required' => ['type', 'parentId', 'position'],
                                'additionalProperties' => false,
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'add_reference'],
                                    'parentId' => ['type' => 'string', 'pattern' => '^(n_[a-f0-9]{32}|\\$[a-z][a-z0-9_]{0,31})$'],
                                    'position' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 500],
                                    'name' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$', 'maxLength' => 100],
                                    'version' => ['type' => 'string', 'pattern' => '^(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)$'],
                                    'props' => ['type' => 'object'],
                                    'alias' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]{0,31}$'],
                                ],
                                'required' => ['type', 'parentId', 'position', 'name', 'version'],
                                'additionalProperties' => false,
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'update_node'],
                                    'nodeId' => ['type' => 'string', 'pattern' => '^(n_[a-f0-9]{32}|\\$[a-z][a-z0-9_]{0,31})$'],
                                    'changes' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'tag' => [
                                                'type' => 'string',
                                                'enum' => ['div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav'],
                                            ],
                                            'classes' => [
                                                'type' => 'array',
                                                'maxItems' => 100,
                                                'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                                            ],
                                            'value' => ['type' => 'string', 'maxLength' => 10000],
                                            'name' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$', 'maxLength' => 100],
                                            'version' => ['type' => 'string', 'pattern' => '^(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)$'],
                                            'props' => ['type' => 'object'],
                                        ],
                                        'required' => [],
                                        'additionalProperties' => false,
                                    ],
                                ],
                                'required' => ['type', 'nodeId', 'changes'],
                                'additionalProperties' => false,
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'move_node'],
                                    'nodeId' => ['type' => 'string', 'pattern' => '^(n_[a-f0-9]{32}|\\$[a-z][a-z0-9_]{0,31})$'],
                                    'parentId' => ['type' => 'string', 'pattern' => '^(n_[a-f0-9]{32}|\\$[a-z][a-z0-9_]{0,31})$'],
                                    'position' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 500],
                                ],
                                'required' => ['type', 'nodeId', 'parentId', 'position'],
                                'additionalProperties' => false,
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'indent_node'],
                                    'nodeId' => ['type' => 'string', 'pattern' => '^(n_[a-f0-9]{32}|\\$[a-z][a-z0-9_]{0,31})$'],
                                ],
                                'required' => ['type', 'nodeId'],
                                'additionalProperties' => false,
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'outdent_node'],
                                    'nodeId' => ['type' => 'string', 'pattern' => '^(n_[a-f0-9]{32}|\\$[a-z][a-z0-9_]{0,31})$'],
                                ],
                                'required' => ['type', 'nodeId'],
                                'additionalProperties' => false,
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'move_up'],
                                    'nodeId' => ['type' => 'string', 'pattern' => '^(n_[a-f0-9]{32}|\\$[a-z][a-z0-9_]{0,31})$'],
                                ],
                                'required' => ['type', 'nodeId'],
                                'additionalProperties' => false,
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'move_down'],
                                    'nodeId' => ['type' => 'string', 'pattern' => '^(n_[a-f0-9]{32}|\\$[a-z][a-z0-9_]{0,31})$'],
                                ],
                                'required' => ['type', 'nodeId'],
                                'additionalProperties' => false,
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'duplicate_node'],
                                    'nodeId' => ['type' => 'string', 'pattern' => '^(n_[a-f0-9]{32}|\\$[a-z][a-z0-9_]{0,31})$'],
                                    'alias' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]{0,31}$'],
                                ],
                                'required' => ['type', 'nodeId'],
                                'additionalProperties' => false,
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'remove_node'],
                                    'nodeId' => ['type' => 'string', 'pattern' => '^(n_[a-f0-9]{32}|\\$[a-z][a-z0-9_]{0,31})$'],
                                ],
                                'required' => ['type', 'nodeId'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                ],
            ],
            'required' => ['compositionId', 'expectedRevision', 'operationId', 'commands'],
            'additionalProperties' => false,
        ],
        'validate' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'revision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
            ],
            'required' => ['compositionId', 'revision'],
            'additionalProperties' => false,
        ],
        'update_metadata' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'expectedRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                'description' => ['type' => 'string', 'maxLength' => 2000],
            ],
            'required' => ['compositionId', 'expectedRevision', 'operationId', 'title'],
            'additionalProperties' => false,
        ],
        'get_history' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            ],
            'required' => ['compositionId'],
            'additionalProperties' => false,
        ],
        'restore_revision' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'expectedRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'restoreRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
            ],
            'required' => ['compositionId', 'expectedRevision', 'operationId', 'restoreRevision'],
            'additionalProperties' => false,
        ],
        'render_preview' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'revision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'themeReleaseId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'width' => ['type' => 'integer', 'minimum' => 320, 'maximum' => 1920],
                'scripts' => ['type' => 'boolean'],
            ],
            'required' => ['compositionId', 'revision', 'operationId'],
            'additionalProperties' => false,
        ],
        'get_preview' => [
            'type' => 'object',
            'properties' => [
                'jobId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'includeHtml' => ['type' => 'boolean'],
            ],
            'required' => ['jobId'],
            'additionalProperties' => false,
        ],
        'cancel_preview' => [
            'type' => 'object',
            'properties' => [
                'jobId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
            ],
            'required' => ['jobId', 'operationId'],
            'additionalProperties' => false,
        ],
        'add_container' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'expectedRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'parentId' => ['type' => 'string', 'pattern' => '^n_[a-f0-9]{32}$'],
                'position' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 500],
                'tag' => [
                    'type' => 'string',
                    'enum' => ['div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav'],
                ],
                'classes' => [
                    'type' => 'array',
                    'maxItems' => 100,
                    'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                ],
                'alias' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]{0,31}$'],
            ],
            'required' => ['compositionId', 'expectedRevision', 'operationId', 'parentId', 'position'],
            'additionalProperties' => false,
        ],
        'add_text' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'expectedRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'parentId' => ['type' => 'string', 'pattern' => '^n_[a-f0-9]{32}$'],
                'position' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 500],
                'value' => ['type' => 'string', 'maxLength' => 10000],
                'alias' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]{0,31}$'],
            ],
            'required' => ['compositionId', 'expectedRevision', 'operationId', 'parentId', 'position'],
            'additionalProperties' => false,
        ],
        'add_reference' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'expectedRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'parentId' => ['type' => 'string', 'pattern' => '^n_[a-f0-9]{32}$'],
                'position' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 500],
                'name' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$', 'maxLength' => 100],
                'version' => ['type' => 'string', 'pattern' => '^(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)$'],
                'props' => ['type' => 'object'],
                'alias' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]{0,31}$'],
            ],
            'required' => ['compositionId', 'expectedRevision', 'operationId', 'parentId', 'position', 'name', 'version'],
            'additionalProperties' => false,
        ],
        'update_node' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'expectedRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'nodeId' => ['type' => 'string', 'pattern' => '^n_[a-f0-9]{32}$'],
                'changes' => [
                    'type' => 'object',
                    'properties' => [
                        'tag' => [
                            'type' => 'string',
                            'enum' => ['div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'nav'],
                        ],
                        'classes' => [
                            'type' => 'array',
                            'maxItems' => 100,
                            'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 120],
                        ],
                        'value' => ['type' => 'string', 'maxLength' => 10000],
                        'name' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$', 'maxLength' => 100],
                        'version' => ['type' => 'string', 'pattern' => '^(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)$'],
                        'props' => ['type' => 'object'],
                    ],
                    'required' => [],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['compositionId', 'expectedRevision', 'operationId', 'nodeId', 'changes'],
            'additionalProperties' => false,
        ],
        'move_node' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'expectedRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'nodeId' => ['type' => 'string', 'pattern' => '^n_[a-f0-9]{32}$'],
                'parentId' => ['type' => 'string', 'pattern' => '^n_[a-f0-9]{32}$'],
                'position' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 500],
            ],
            'required' => ['compositionId', 'expectedRevision', 'operationId', 'nodeId', 'parentId', 'position'],
            'additionalProperties' => false,
        ],
        'indent_node' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'expectedRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'nodeId' => ['type' => 'string', 'pattern' => '^n_[a-f0-9]{32}$'],
            ],
            'required' => ['compositionId', 'expectedRevision', 'operationId', 'nodeId'],
            'additionalProperties' => false,
        ],
        'outdent_node' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'expectedRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'nodeId' => ['type' => 'string', 'pattern' => '^n_[a-f0-9]{32}$'],
            ],
            'required' => ['compositionId', 'expectedRevision', 'operationId', 'nodeId'],
            'additionalProperties' => false,
        ],
        'move_up' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'expectedRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'nodeId' => ['type' => 'string', 'pattern' => '^n_[a-f0-9]{32}$'],
            ],
            'required' => ['compositionId', 'expectedRevision', 'operationId', 'nodeId'],
            'additionalProperties' => false,
        ],
        'move_down' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'expectedRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'nodeId' => ['type' => 'string', 'pattern' => '^n_[a-f0-9]{32}$'],
            ],
            'required' => ['compositionId', 'expectedRevision', 'operationId', 'nodeId'],
            'additionalProperties' => false,
        ],
        'duplicate_node' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'expectedRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'nodeId' => ['type' => 'string', 'pattern' => '^n_[a-f0-9]{32}$'],
                'alias' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]{0,31}$'],
            ],
            'required' => ['compositionId', 'expectedRevision', 'operationId', 'nodeId'],
            'additionalProperties' => false,
        ],
        'remove_node' => [
            'type' => 'object',
            'properties' => [
                'compositionId' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                'expectedRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'operationId' => ['type' => 'string', 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$'],
                'nodeId' => ['type' => 'string', 'pattern' => '^n_[a-f0-9]{32}$'],
            ],
            'required' => ['compositionId', 'expectedRevision', 'operationId', 'nodeId'],
            'additionalProperties' => false,
        ],
    ];
    public const RESULT = [
        'type' => 'object',
        'properties' => [
            'ok' => ['type' => 'boolean'],
            'compositionId' => [
                'type' => ['string', 'null'],
            ],
            'revision' => [
                'type' => ['integer', 'null'],
            ],
            'composerUrl' => [
                'type' => ['string', 'null'],
            ],
            'summary' => ['type' => 'string'],
            'warnings' => ['type' => 'array'],
            'code' => ['type' => 'string'],
            'message' => ['type' => 'string'],
            'details' => [
                'type' => ['object', 'array'],
            ],
            'document' => ['type' => 'object'],
            'affectedNodeIds' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'aliases' => ['type' => 'object'],
            'replayed' => ['type' => 'boolean'],
            'items' => ['type' => 'array'],
            'history' => ['type' => 'array'],
            'jobId' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'previewRevision' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
            'html' => ['type' => 'string'],
        ],
        'required' => ['ok', 'summary', 'warnings'],
        'additionalProperties' => true,
    ];
}
