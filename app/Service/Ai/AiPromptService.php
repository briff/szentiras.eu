<?php

namespace SzentirasHu\Service\Ai;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Arr;
use OpenAI\Client as OpenAIClient;
use OpenAI\Factory as OpenAIFactory;
use InvalidArgumentException;
use RuntimeException;

class AiPromptService
{
    public function __construct(
        protected ConfigRepository $config,
    ) {}

    /**
     * Resolve a named configuration, merging provider defaults.
     *
     * @param string $name Configuration name (e.g., 'commentary')
     * @return array<string, mixed>
     * @throws InvalidArgumentException If the configuration does not exist.
     */
    public function resolveConfiguration(string $name): array
    {
        $configurations = $this->config->get('ai.configurations', []);
        if (!isset($configurations[$name])) {
            throw new InvalidArgumentException("AI configuration '{$name}' not found.");
        }

        $config = $configurations[$name];
        $providerName = $config['provider'] ?? 'openai';

        $providers = $this->config->get('ai.providers', []);
        if (!isset($providers[$providerName])) {
            throw new InvalidArgumentException("Provider '{$providerName}' not defined.");
        }

        $provider = $providers[$providerName];

        // Merge provider defaults, but configuration-specific values take precedence
        $merged = array_merge($provider, $config);

        // Ensure required keys exist
        $merged['provider'] = $providerName;
        $merged['api_key'] ??= null;
        $merged['endpoint'] ??= null;
        $merged['model'] ??= null;
        $merged['prompt'] ??= '';
        $merged['temperature'] ??= 0.7;
        $merged['max_tokens'] ??= 2048;
        $merged['timeout'] ??= 30;
        $merged['version'] ??= 'v1';
        
        // GPT-5 specific defaults
        $merged['verbosity'] ??= 'low';
        $merged['reasoning_effort'] ??= 'none';
        $merged['text_format'] ??= 'json_object';
        $merged['summary'] ??= null;
        $merged['store'] ??= false;

        return $merged;
    }

    /**
     * Replace placeholders in a prompt template.
     *
     * @param array<string, mixed> $config Configuration array (must contain 'prompt')
     * @param array<string, string> $data Placeholder replacements
     * @return string Prompt with placeholders replaced.
     */
    public function replacePlaceholders(array $config, array $data): string
    {
        $prompt = $config['prompt'] ?? '';
        if (empty($prompt)) {
            return '';
        }

        // Check if prompt is a file path and load content if it exists
        $prompt = $this->loadPromptFromFile($prompt);

        foreach ($data as $key => $value) {
            $placeholder = '{' . $key . '}';
            $prompt = str_replace($placeholder, $value, $prompt);
        }

        return $prompt;
    }

    /**
     * Load prompt content from file if the prompt string is a valid file path.
     *
     * @param string $prompt Prompt string or file path
     * @return string Prompt content
     */
    protected function loadPromptFromFile(string $prompt): string
    {
        // Check if the prompt looks like a file path (contains .md, .txt, or starts with resource_path)
        if (str_contains($prompt, '.md') || str_contains($prompt, '.txt') || str_starts_with($prompt, 'resource_path(')) {
            // Handle resource_path() helper syntax
            if (str_starts_with($prompt, 'resource_path(') && str_ends_with($prompt, ')')) {
                $path = substr($prompt, 15, -1); // Remove 'resource_path(' and ')'
                $path = trim($path, "'\"");
                $filePath = resource_path($path);
            } else {
                $filePath = $prompt;
            }

            // Check if file exists and is readable
            if (file_exists($filePath) && is_readable($filePath)) {
                return file_get_contents($filePath) ?: $prompt;
            }
        }

        return $prompt;
    }

    /**
     * Get an authenticated client for the given provider.
     *
     * @param string $provider Provider name (e.g., 'openai', 'anthropic')
     * @param array<string, mixed> $overrides Optional overrides for api_key, endpoint, timeout
     * @return OpenAIClient|mixed
     * @throws RuntimeException If the provider is not supported.
     */
    public function client(string $provider, array $overrides = []): mixed
    {
        $providers = $this->config->get('ai.providers', []);
        if (!isset($providers[$provider])) {
            throw new RuntimeException("Provider '{$provider}' is not configured.");
        }

        $settings = array_merge($providers[$provider], $overrides);

        return match ($provider) {
            'openai' => $this->createOpenAIClient($settings),
            // 'anthropic' => $this->createAnthropicClient($settings),
            default => throw new RuntimeException("Provider '{$provider}' is not supported."),
        };
    }

    /**
     * Create an OpenAI client with the given settings.
     *
     * @param array<string, mixed> $settings
     */
    protected function createOpenAIClient(array $settings): OpenAIClient
    {
        $factory = new OpenAIFactory();

        if ($apiKey = $settings['api_key'] ?? null) {
            $factory->withApiKey($apiKey);
        }

        if ($endpoint = $settings['endpoint'] ?? null) {
            $factory->withBaseUri($endpoint);
        }

        if ($timeout = $settings['timeout'] ?? null) {
            $factory->withHttpClient(new \GuzzleHttp\Client(['timeout' => $timeout]));
        }

        return $factory->make();
    }

    /**
     * Generate a completion using a named configuration.
     *
     * @param string $configurationName
     * @param array<string, string> $placeholders
     * @param string|null $userMessage Optional user message to append to the prompt.
     * @return mixed Raw API response (depends on provider).
     */
    public function generate(string $configurationName, array $placeholders = [], ?string $userMessage = null): mixed
    {
        $config = $this->resolveConfiguration($configurationName);
        $prompt = $this->replacePlaceholders($config, $placeholders);

        if ($userMessage !== null) {
            $prompt .= "\n\n" . $userMessage;
        }

        $client = $this->client($config['provider'], [
            'api_key' => $config['api_key'] ?? null,
            'endpoint' => $config['endpoint'] ?? null,
            'timeout' => $config['timeout'] ?? null,
        ]);

        $params = [
            'model' => $config['model'],
            'temperature' => (float) ($config['temperature'] ?? 0.7),
            'max_tokens' => (int) ($config['max_tokens'] ?? 2048),
        ];

        // Add GPT-5 specific parameters if they exist
        $textParams = [];
        if (isset($config['verbosity'])) {
            $textParams['verbosity'] = $config['verbosity'];
        }
        if (isset($config['text_format'])) {
            $textParams['format'] = $config['text_format'];
        }
        if (!empty($textParams)) {
            $params['text'] = $textParams;
        }

        if (isset($config['reasoning_effort'])) {
            $params['reasoning'] = ['effort' => $config['reasoning_effort']];
        }
        
        if (isset($config['summary'])) {
            $params['summary'] = (bool) $config['summary'];
        }
        
        if (isset($config['store'])) {
            $params['store'] = (bool) $config['store'];
        }

        // Provider-specific request structure
        return match ($config['provider']) {
            'openai' => $client->chat()->create([
                ...$params,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]),
            // 'anthropic' => $client->messages()->create([ ... ]),
            default => throw new RuntimeException("Provider '{$config['provider']}' not supported for generation."),
        };
    }
}
