<?php

namespace SzentirasHu\Test;

use SzentirasHu\Service\Ai\AiPromptService;
use Illuminate\Contracts\Config\Repository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AiPromptServiceTest extends TestCase
{
    private Repository|MockInterface $config;
    private AiPromptService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = Mockery::mock(Repository::class);
        $this->service = new AiPromptService($this->config);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_resolves_configuration_with_provider_defaults(): void
    {
        $this->config->shouldReceive('get')
            ->with('ai.configurations', [])
            ->andReturn([
                'commentary' => [
                    'provider' => 'openai',
                    'prompt' => 'Test {verse_text}',
                    'version' => 'v2',
                ],
            ]);

        $this->config->shouldReceive('get')
            ->with('ai.providers', [])
            ->andReturn([
                'openai' => [
                    'endpoint' => 'https://api.openai.com/v1',
                    'api_key' => 'default-key',
                    'model' => 'gpt-4',
                    'temperature' => 0.7,
                    'max_output_tokens' => 2048,
                    'timeout' => 30,
                ],
            ]);

        $result = $this->service->resolveConfiguration('commentary');

        $this->assertSame('openai', $result['provider']);
        $this->assertSame('Test {verse_text}', $result['prompt']);
        $this->assertSame('v2', $result['version']);
        $this->assertSame('https://api.openai.com/v1', $result['endpoint']);
        $this->assertSame('default-key', $result['api_key']);
        $this->assertSame('gpt-4', $result['model']);
        $this->assertSame(0.7, $result['temperature']);
        $this->assertSame(2048, $result['max_output_tokens']);
        $this->assertSame(30, $result['timeout']);
    }

    #[Test]
    public function it_overrides_provider_defaults_with_configuration_values(): void
    {
        $this->config->shouldReceive('get')
            ->with('ai.configurations', [])
            ->andReturn([
                'commentary' => [
                    'provider' => 'openai',
                    'api_key' => 'custom-key',
                    'model' => 'gpt-3.5-turbo',
                    'temperature' => 0.9,
                    'prompt' => 'Custom',
                ],
            ]);

        $this->config->shouldReceive('get')
            ->with('ai.providers', [])
            ->andReturn([
                'openai' => [
                    'endpoint' => 'https://api.openai.com/v1',
                    'api_key' => 'default-key',
                    'model' => 'gpt-4',
                    'temperature' => 0.7,
                    'max_output_tokens' => 2048,
                    'timeout' => 30,
                ],
            ]);

        $result = $this->service->resolveConfiguration('commentary');

        $this->assertSame('custom-key', $result['api_key']);
        $this->assertSame('gpt-3.5-turbo', $result['model']);
        $this->assertSame(0.9, $result['temperature']);
        // Should keep provider defaults for missing keys
        $this->assertSame(2048, $result['max_output_tokens']);
        $this->assertSame(30, $result['timeout']);
    }

    #[Test]
    public function it_throws_exception_for_missing_configuration(): void
    {
        $this->config->shouldReceive('get')
            ->with('ai.configurations', [])
            ->andReturn([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("AI configuration 'unknown' not found.");

        $this->service->resolveConfiguration('unknown');
    }

    #[Test]
    public function it_throws_exception_for_missing_provider(): void
    {
        $this->config->shouldReceive('get')
            ->with('ai.configurations', [])
            ->andReturn([
                'commentary' => ['provider' => 'nonexistent'],
            ]);

        $this->config->shouldReceive('get')
            ->with('ai.providers', [])
            ->andReturn([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Provider 'nonexistent' not defined.");

        $this->service->resolveConfiguration('commentary');
    }

    #[Test]
    public function it_replaces_placeholders_in_prompt(): void
    {
        $config = ['prompt' => 'Verse {verse_text} from {book} {chapter}:{verse}'];
        $data = [
            'verse_text' => 'In the beginning',
            'book' => 'Genesis',
            'chapter' => '1',
            'verse' => '1',
        ];

        $result = $this->service->replacePlaceholders($config, $data);

        $this->assertSame('Verse In the beginning from Genesis 1:1', $result);
    }

    #[Test]
    public function it_returns_empty_string_if_prompt_is_empty(): void
    {
        $config = ['prompt' => ''];
        $result = $this->service->replacePlaceholders($config, ['key' => 'value']);
        $this->assertSame('', $result);
    }

    #[Test]
    public function it_ignores_placeholders_without_data(): void
    {
        $config = ['prompt' => 'Verse {verse_text} {unknown}'];
        $data = ['verse_text' => 'Hello'];
        $result = $this->service->replacePlaceholders($config, $data);
        $this->assertSame('Verse Hello {unknown}', $result);
    }

    #[Test]
    public function it_creates_openai_client_with_overrides(): void
    {
        $this->config->shouldReceive('get')
            ->with('ai.providers', [])
            ->andReturn([
                'openai' => [
                    'endpoint' => 'https://api.openai.com/v1',
                    'api_key' => 'default-key',
                    'timeout' => 30,
                ],
            ]);

        $client = $this->service->client('openai', [
            'api_key' => 'custom-key',
            'endpoint' => 'https://custom.endpoint',
        ]);

        $this->assertInstanceOf(\OpenAI\Client::class, $client);
    }

    #[Test]
    public function it_throws_exception_for_unsupported_provider(): void
    {
        $this->config->shouldReceive('get')
            ->with('ai.providers', [])
            ->andReturn([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Provider 'unknown' is not configured.");

        $this->service->client('unknown');
    }

    #[Test]
    public function it_resolves_configuration_with_reasoning_effort(): void
    {
        $this->config->shouldReceive('get')
            ->with('ai.configurations', [])
            ->andReturn([
                'commentary' => [
                    'provider' => 'openai',
                    'prompt' => 'Test prompt',
                    'reasoning_effort' => 'medium',
                ],
            ]);

        $this->config->shouldReceive('get')
            ->with('ai.providers', [])
            ->andReturn([
                'openai' => [
                    'endpoint' => 'https://api.openai.com/v1',
                    'api_key' => 'test-key',
                    'model' => 'gpt-4.1',
                    'temperature' => 0.7,
                    'max_output_tokens' => 2048,
                    'timeout' => 30,
                    'reasoning_effort' => 'none',
                    'verbosity' => 'medium',
                ],
            ]);

        $result = $this->service->resolveConfiguration('commentary');

        // Configuration-specific reasoning_effort should override provider default
        $this->assertSame('medium', $result['reasoning_effort']);
        $this->assertSame('medium', $result['verbosity']);
    }

    #[Test]
    public function it_resolves_configuration_with_response_format(): void
    {
        $responseFormat = [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'test',
                'schema' => ['type' => 'object'],
            ],
        ];

        $this->config->shouldReceive('get')
            ->with('ai.configurations', [])
            ->andReturn([
                'commentary' => [
                    'provider' => 'openai',
                    'prompt' => 'Test prompt',
                    'response_format' => $responseFormat,
                ],
            ]);

        $this->config->shouldReceive('get')
            ->with('ai.providers', [])
            ->andReturn([
                'openai' => [
                    'endpoint' => 'https://api.openai.com/v1',
                    'api_key' => 'test-key',
                    'model' => 'gpt-4.1',
                    'temperature' => 0.7,
                    'max_output_tokens' => 2048,
                    'timeout' => 30,
                ],
            ]);

        $result = $this->service->resolveConfiguration('commentary');

        $this->assertSame($responseFormat, $result['response_format']);
    }
}
