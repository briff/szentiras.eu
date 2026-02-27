<?php

return [
    // Provider definitions
    'providers' => [
        'openai' => [
            'endpoint'         => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1'),
            'api_key'          => env('OPENAI_API_KEY'),
            'model'            => env('OPENAI_DEFAULT_MODEL', 'gpt-4.1'),
            'temperature'      => env('OPENAI_TEMPERATURE', 0.7),
            'max_output_tokens' => env('OPENAI_MAX_OUTPUT_TOKENS', 2048),
            'timeout'          => env('OPENAI_TIMEOUT', 30),
            'verbosity'        => env('OPENAI_VERBOSITY', 'medium'),
            'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'medium'),
        ],
    ],

    // Named configurations for specific use cases
    'configurations' => [
        'commentary' => [
            'max_input_length' => env('AI_COMMENTARY_MAX_INPUT_LENGTH', 8000),
            'provider'         => env('AI_COMMENTARY_PROVIDER', 'openai'),
            'api_key'          => env('AI_COMMENTARY_API_KEY'),
            'endpoint'         => env('AI_COMMENTARY_ENDPOINT'),
            'model'            => env('AI_COMMENTARY_MODEL', 'gpt-4.1'),
            'temperature'      => env('AI_COMMENTARY_TEMPERATURE', 0.7),
            'max_output_tokens' => env('AI_COMMENTARY_MAX_OUTPUT_TOKENS', 4096),
            'timeout'          => env('AI_COMMENTARY_TIMEOUT', 60),
            'verbosity'        => env('AI_COMMENTARY_VERBOSITY', 'low'),
            'reasoning_effort' => env('AI_COMMENTARY_REASONING_EFFORT', 'none'),
            'system_prompt'    => resource_path('prompts/hungarian_biblical_commentary_system.md'),
            'user_prompt'      => resource_path('prompts/hungarian_biblical_commentary_user.md'),
            'response_format' => [
                'type' => 'json_schema',
                'name' => 'commentary',
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'commentary_text' => [
                            'type' => 'string',
                        ],
                        'references' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'properties' => [
                                    'ref' => [
                                        'type' => 'string',
                                    ],
                                    'reason' => [
                                        'type' => 'string',
                                    ],
                                ],
                                'required' => ['ref', 'reason'],
                            ],
                        ],
                    ],
                    'required' => ['commentary_text', 'references'],
                ],
            ],
        ],
    ],
];
