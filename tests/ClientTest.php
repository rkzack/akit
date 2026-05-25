<?php

declare(strict_types=1);

namespace Akit\Tests;

use Akit\Client;
use Akit\Config;
use Akit\Message;
use Akit\Model;
use Akit\Response;
use Akit\Exceptions\AuthException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Akit client.
 *
 * Integration tests that call the live API require ANTHROPIC_API_KEY to be set
 * and are skipped otherwise. Mark them with @group integration to separate runs.
 */
class ClientTest extends TestCase
{
    // ── Config ────────────────────────────────────────────────────────────────

    public function testConfigRejectsEmptyApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Config(apiKey: '');
    }

    public function testConfigDefaults(): void
    {
        $config = new Config(apiKey: 'sk-test');
        $this->assertSame(Config::DEFAULT_MODEL, $config->model);
        $this->assertSame(Config::DEFAULT_MAX_TOKENS, $config->maxTokens);
        $this->assertSame(Config::API_BASE_URL, $config->baseUrl);
    }

    public function testConfigFromEnvThrowsWhenMissing(): void
    {
        $original = getenv('ANTHROPIC_API_KEY');
        putenv('ANTHROPIC_API_KEY=');
        unset($_ENV['ANTHROPIC_API_KEY']);

        try {
            $this->expectException(\RuntimeException::class);
            Config::fromEnv();
        } finally {
            if ($original !== false) {
                putenv("ANTHROPIC_API_KEY={$original}");
            }
        }
    }

    // ── Message ───────────────────────────────────────────────────────────────

    public function testMessageNamedConstructors(): void
    {
        $user = Message::user('Hello');
        $this->assertSame('user', $user->role);
        $this->assertSame('Hello', $user->content);

        $asst = Message::assistant('Hi');
        $this->assertSame('assistant', $asst->role);
    }

    public function testMessageToArray(): void
    {
        $message = Message::user('test');
        $this->assertSame(['role' => 'user', 'content' => 'test'], $message->toArray());
    }

    // ── Model enum ────────────────────────────────────────────────────────────

    public function testModelEnumValues(): void
    {
        $this->assertSame('claude-sonnet-4-6', Model::SONNET_4_6->value);
        $this->assertSame('claude-opus-4-7',  Model::OPUS_4_7->value);
    }

    // ── Client construction ───────────────────────────────────────────────────

    public function testClientAcceptsStringKey(): void
    {
        $client = new Client('sk-ant-test');
        $this->assertSame('sk-ant-test', $client->config()->apiKey);
    }

    public function testClientAcceptsConfigObject(): void
    {
        $config = new Config(apiKey: 'sk-ant-test', model: Model::HAIKU_4_5->value);
        $client = new Client($config);
        $this->assertSame(Model::HAIKU_4_5->value, $client->config()->model);
    }

    // ── Live integration tests ────────────────────────────────────────────────

    /** @group integration */
    public function testLivePrompt(): void
    {
        $apiKey = getenv('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            $this->markTestSkipped('ANTHROPIC_API_KEY not set.');
        }

        $client   = new Client($apiKey);
        $response = $client->prompt('Reply with only the word "pong".');

        $this->assertStringContainsStringIgnoringCase('pong', $response->text());
        $this->assertGreaterThan(0, $response->totalTokens());
        $this->assertNotEmpty($response->id());
    }

    /** @group integration */
    public function testLiveAuthException(): void
    {
        $this->expectException(AuthException::class);
        $client = new Client('sk-ant-invalid-key');
        $client->prompt('Hello');
    }
}
