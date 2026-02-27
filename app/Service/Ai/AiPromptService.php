<?php

namespace SzentirasHu\Service\Ai;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Log;
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
        // temperature only exists for gpt-5.2, so for that we set a default of 0.7, but for older models it will be ignored
        $merged['max_output_tokens'] ??= 2048;
        $merged['timeout'] ??= 30;
        $merged['reasoning_effort'] ??= 'none';
        $merged['verbosity'] ??= 'low';
        $merged['response_format'] ??= null;

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

        $prompt = $this->loadPromptFromFile($prompt);

        foreach ($data as $key => $value) {
            $placeholder = '{' . $key . '}';
            $prompt = str_replace($placeholder, $value, $prompt);
        }

        return $prompt;
    }

    /**
     * Load and prepare system and user prompts from configuration.
     *
     * @param array<string, mixed> $config Configuration array
     * @param array<string, string> $placeholders Placeholder replacements
     * @return array{system: string, user: string} Prepared prompts
     */
    public function preparePrompts(array $config, array $placeholders): array
    {
        // Load system prompt (optional)
        $systemPrompt = '';
        if (isset($config['system_prompt'])) {
            $systemPrompt = $this->loadPromptFromFile($config['system_prompt']);
        }

        // Load user prompt (required)
        $userPrompt = $config['user_prompt'] ?? $config['prompt'] ?? '';
        if (empty($userPrompt)) {
            throw new InvalidArgumentException('Configuration must contain either "user_prompt" or "prompt"');
        }

        $userPrompt = $this->loadPromptFromFile($userPrompt);

        // Replace placeholders in user prompt
        foreach ($placeholders as $key => $value) {
            $placeholder = '{' . $key . '}';
            $userPrompt = str_replace($placeholder, $value, $userPrompt);
        }

        return [
            'system' => $systemPrompt,
            'user' => $userPrompt,
        ];
    }

    /**
     * Load prompt content from file if the prompt string is a valid file path.
     *
     * @param string $prompt Prompt string or file path
     * @return string Prompt content
     */
    protected function loadPromptFromFile(string $prompt): string
    {
        if (str_contains($prompt, '.md') || str_contains($prompt, '.txt') || str_starts_with($prompt, 'resource_path(')) {
            if (str_starts_with($prompt, 'resource_path(') && str_ends_with($prompt, ')')) {
                $path = substr($prompt, 15, -1);
                $path = trim($path, "'\"");
                $filePath = resource_path($path);
            } else {
                $filePath = $prompt;
            }

            if (file_exists($filePath) && is_readable($filePath)) {
                return file_get_contents($filePath) ?: $prompt;
            }
        }

        return $prompt;
    }

    /**
     * Get an authenticated client for the given provider.
     *
     * @param string $provider Provider name (e.g., 'openai')
     * @param array<string, mixed> $overrides Optional overrides for api_key, endpoint, timeout
     * @return OpenAIClient
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
     * @param array<string, string|int> $placeholders
     * @param bool $isBatch Whether to submit as a batch job
     * @param int|null $sourceId Optional source ID (e.g., commentary ID) to associate with batch item
     * @return mixed Raw API response, or null if batch job was submitted
     */
    public function generate(string $configurationName, bool $isBatch = false, array $placeholders = [], ?int $sourceId = null): mixed
    {
        $config = $this->resolveConfiguration($configurationName);

        // Prepare system and user prompts
        $prompts = $this->preparePrompts($config, $placeholders);
        $systemPrompt = $prompts['system'];
        $userPrompt = $prompts['user'];

        $client = $this->client($config['provider'], [
            'api_key' => $config['api_key'],
            'endpoint' => $config['endpoint'],
            'timeout' => $config['timeout'],
        ]);

        // Prepare input for OpenAI API
        $input = $this->prepareInput($systemPrompt, $userPrompt);

        $params = [
            'model' => $config['model'],
            'input' => $input,
            'max_output_tokens' => (int) $config['max_output_tokens'],
            'store' => false,
            'parallel_tool_calls' => false,
        ];

        // Only include temperature for OpenAI gpt-5.2 models
        if ($config['provider'] === 'openai' && str_starts_with($config['model'] ?? '', 'gpt-5.2')) {
            $params['temperature'] = (float) $config['temperature'];
        }

        // Configure reasoning if effort is not 'none'
        if ($config['reasoning_effort'] !== 'none') {
            $params['reasoning'] = [
                'effort' => $config['reasoning_effort'],
            ];
        }

        // Configure text output format
        $textParams = [
            'verbosity' => $config['verbosity'],
        ];

        if ($config['response_format'] !== null) {
            // Extract json_schema from response_format if it's a full schema object
            $format = $config['response_format'];
            $textParams['format'] = $format;
        } else {
            // Default to plain text format
            $textParams['format'] = ['type' => 'text'];
        }

        $params['text'] = $textParams;

        Log::debug("Generating AI response with configuration '{$configurationName}'", [
            'provider' => $config['provider'],
            'model' => $config['model'],
            'reasoning_effort' => $config['reasoning_effort'],
            'verbosity' => $config['verbosity'],
            'response_format' => $config['response_format'] ?? 'default',
            'has_system_prompt' => !empty($systemPrompt),
        ]);

        if ($isBatch) {
            $batch = \SzentirasHu\Models\OpenAIBatch::create([
                'endpoint' => '/v1/responses',
                'status' => 'queued',
            ]);

            $batch->items()->create([
                'custom_id' => "gen_{$batch->id}_1",
                'source_id' => $sourceId, // Use the provided source ID (e.g., commentary ID)
                'payload' => $params,
                'status' => 'queued',
            ]);

            SubmitOpenAIBatch::dispatch($batch->id)->onQueue('openai-batch');
            return null;
        }
        else {
            return match ($config['provider']) {
                'openai' => $client->responses()->create($params),
                default => throw new RuntimeException("Provider '{$config['provider']}' not supported for generation."),
            };
            }
    }

    /**
     * Prepare input for OpenAI API based on system and user prompts.
     *
     * @param string $systemPrompt
     * @param string $userPrompt
     * @return string|array Input for OpenAI API
     */
    protected function prepareInput(string $systemPrompt, string $userPrompt): string|array
    {
        // If there's no system prompt, return user prompt as string (backward compatibility)
        if (empty($systemPrompt)) {
            return $userPrompt;
        }

        // Otherwise, return array of messages
        return [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $userPrompt,
            ],
        ];
    }
}
