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
            'model'       => env('AI_COMMENTARY_MODEL'),
            'prompt'      => env('AI_COMMENTARY_PROMPT', 'Generate commentary for the following verse: {verse_text}'),
            'temperature' => env('AI_COMMENTARY_TEMPERATURE'),
            'max_tokens'  => env('AI_COMMENTARY_MAX_TOKENS'),
            'timeout'     => env('AI_COMMENTARY_TIMEOUT'),
            'version'     => env('AI_COMMENTARY_VERSION', 'v1'),
        ],
        // Example: 'summarization', 'translation', 'question_answering'
    ],
];
