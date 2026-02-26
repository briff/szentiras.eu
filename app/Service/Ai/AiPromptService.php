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

        foreach ($data as $key => $value) {
            $placeholder = '{' . $key . '}';
            $prompt = str_replace($placeholder, $value, $prompt);
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
