<?php

return [
    // Global configuration version (optional)
    'config_version' => env('AI_CONFIG_VERSION', '1.0'),

    // Provider definitions
    'providers' => [
        'openai' => [
            'endpoint'    => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1'),
            'api_key'     => env('OPENAI_API_KEY'),
            'model'       => env('OPENAI_DEFAULT_MODEL', 'gpt-4'),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
            'max_tokens'  => env('OPENAI_MAX_TOKENS', 2048),
            'timeout'     => env('OPENAI_TIMEOUT', 30),
            // GPT-5 specific parameters
            'verbosity'   => env('OPENAI_VERBOSITY', 'medium'),
            'reasoning_effort' => env('OPENAI_REASONING_EFFORT', 'medium'),
            'text_format' => env('OPENAI_TEXT_FORMAT', 'text'),
            'summary'     => env('OPENAI_SUMMARY', false),
            'store'       => env('OPENAI_STORE', false),
        ],
        'anthropic' => [
            'endpoint'    => env('ANTHROPIC_ENDPOINT', 'https://api.anthropic.com'),
            'api_key'     => env('ANTHROPIC_API_KEY'),
            'model'       => env('ANTHROPIC_DEFAULT_MODEL', 'claude-3-opus-20240229'),
            'temperature' => env('ANTHROPIC_TEMPERATURE', 0.7),
            'max_tokens'  => env('ANTHROPIC_MAX_TOKENS', 4096),
            'timeout'     => env('ANTHROPIC_TIMEOUT', 30),
        ],
        // Additional providers can be added later (e.g., local LLM, Azure OpenAI)
    ],

    // Named configurations for specific use cases
    'configurations' => [
        'commentary' => [
            'provider'    => env('AI_COMMENTARY_PROVIDER', 'openai'),
            // Override provider defaults if needed
            'api_key'     => env('AI_COMMENTARY_API_KEY'),
            'endpoint'    => env('AI_COMMENTARY_ENDPOINT'),
            'model'       => env('AI_COMMENTARY_MODEL', 'gpt-5.2'),
            'prompt'      => env('AI_COMMENTARY_PROMPT', resource_path('prompts/hungarian_biblical_commentary.md')),
            'temperature' => env('AI_COMMENTARY_TEMPERATURE'),
            'max_tokens'  => env('AI_COMMENTARY_MAX_TOKENS', '6000'),
            'timeout'     => env('AI_COMMENTARY_TIMEOUT'),
            'version'     => env('AI_COMMENTARY_VERSION', 'v1'),
            // GPT-5 specific overrides
            'verbosity'   => env('AI_COMMENTARY_VERBOSITY', 'low'),
            'reasoning_effort' => env('AI_COMMENTARY_REASONING_EFFORT', 'none'),
            'text_format' => env('AI_COMMENTARY_TEXT_FORMAT', 'json_object'),
            'summary'     => env('AI_COMMENTARY_SUMMARY', null),
            'store'       => env('AI_COMMENTARY_STORE', null),
        ],
        // Example: 'summarization', 'translation', 'question_answering'
    ],
];
