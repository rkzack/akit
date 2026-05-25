<?php

declare(strict_types=1);

namespace Akit;

use Akit\Exceptions\ApiException;
use Akit\Exceptions\AuthException;

/**
 * The primary Akit client for the Anthropic Messages API.
 *
 * Construct with an API key string or a Config object:
 *
 *   $client = new Client('sk-ant-...');
 *   $client = new Client(Config::fromEnv());
 *   $client = new Client(new Config(apiKey: '...', model: Model::OPUS_4_7->value));
 */
final class Client
{
    private Config $config;

    public function __construct(Config|string $config)
    {
        $this->config = is_string($config)
            ? new Config(apiKey: $config)
            : $config;
    }

    /**
     * Send a single prompt and receive a response.
     *
     * @param string      $prompt    The user message to send
     * @param string|null $system    Optional system prompt
     * @param string|null $model     Override the configured model (or use Model enum ->value)
     * @param int|null    $maxTokens Override the configured max_tokens
     *
     * @throws ApiException
     * @throws AuthException
     */
    public function prompt(
        string  $prompt,
        ?string $system    = null,
        ?string $model     = null,
        ?int    $maxTokens = null,
    ): Response {
        return $this->chat(
            messages:  [Message::user($prompt)],
            system:    $system,
            model:     $model,
            maxTokens: $maxTokens,
        );
    }

    /**
     * Send a full message array and receive a response.
     *
     * Messages must alternate user/assistant, starting with user.
     * Use Message::user() and Message::assistant() to build them.
     *
     * @param Message[] $messages
     *
     * @throws ApiException
     * @throws AuthException
     */
    public function chat(
        array   $messages,
        ?string $system    = null,
        ?string $model     = null,
        ?int    $maxTokens = null,
    ): Response {
        $body = $this->buildBody($messages, $system, $model, $maxTokens);
        return new Response($this->post('/messages', $body));
    }

    /**
     * Stream a response, invoking $onChunk with each incremental text delta.
     *
     * For real-time output in a web context, call flush() inside $onChunk.
     * For CLI use, echo works directly.
     *
     * @param string|Message[] $prompt    A string prompt or pre-built message array
     * @param callable(string): void $onChunk  Receives each text chunk as it arrives
     * @param string|null $system    Optional system prompt
     * @param string|null $model     Override the configured model
     * @param int|null    $maxTokens Override the configured max_tokens
     *
     * @throws ApiException
     * @throws AuthException
     */
    public function stream(
        string|array $prompt,
        callable     $onChunk,
        ?string      $system    = null,
        ?string      $model     = null,
        ?int         $maxTokens = null,
    ): void {
        $messages = is_string($prompt) ? [Message::user($prompt)] : $prompt;
        $body     = $this->buildBody($messages, $system, $model, $maxTokens);
        $body['stream'] = true;

        $this->postStream('/messages', $body, $onChunk);
    }

    /**
     * Create a new stateful Conversation.
     *
     * The conversation tracks message history automatically so you only need
     * to call say() without managing context yourself.
     */
    public function conversation(?string $system = null): Conversation
    {
        return new Conversation(client: $this, system: $system);
    }

    /**
     * The active configuration for this client.
     */
    public function config(): Config
    {
        return $this->config;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /** @param Message[] $messages */
    private function buildBody(
        array   $messages,
        ?string $system,
        ?string $model,
        ?int    $maxTokens,
    ): array {
        $body = [
            'model'      => $model ?? $this->config->model,
            'max_tokens' => $maxTokens ?? $this->config->maxTokens,
            'messages'   => array_map(fn(Message $m) => $m->toArray(), $messages),
        ];

        if ($system !== null) {
            $body['system'] = $system;
        }

        return $body;
    }

    /** @return array<string, mixed> */
    private function post(string $path, array $body): array
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->config->baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->config->timeout,
            CURLOPT_HTTPHEADER     => $this->headers(),
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_THROW_ON_ERROR),
        ]);

        $raw       = curl_exec($ch);
        $status    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new ApiException("cURL error: {$curlError}");
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);

        $this->assertSuccess($status, $data);

        return $data;
    }

    private function postStream(string $path, array $body, callable $onChunk): void
    {
        $ch     = curl_init();
        $buffer = '';

        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->config->baseUrl . $path,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => $this->config->timeout,
            CURLOPT_HTTPHEADER     => $this->headers(),
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body, JSON_THROW_ON_ERROR),
            CURLOPT_WRITEFUNCTION  => function ($ch, string $chunk) use ($onChunk, &$buffer): int {
                $buffer .= $chunk;
                $lines   = explode("\n", $buffer);
                $buffer  = array_pop($lines); // retain any incomplete trailing line

                foreach ($lines as $line) {
                    $line = trim($line);

                    if (!str_starts_with($line, 'data: ')) {
                        continue;
                    }

                    $payload = substr($line, 6);

                    if ($payload === '[DONE]') {
                        continue;
                    }

                    $event = json_decode($payload, associative: true);

                    if (
                        isset($event['type']) &&
                        $event['type'] === 'content_block_delta' &&
                        ($event['delta']['type'] ?? '') === 'text_delta'
                    ) {
                        $onChunk($event['delta']['text']);
                    }
                }

                return strlen($chunk);
            },
        ]);

        curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            throw new ApiException("cURL error: {$curlError}");
        }
    }

    /** @return string[] */
    private function headers(): array
    {
        return [
            'x-api-key: '         . $this->config->apiKey,
            'anthropic-version: ' . $this->config->apiVersion,
            'content-type: application/json',
            'accept: application/json',
        ];
    }

    /** @throws ApiException|AuthException */
    private function assertSuccess(int $status, array $data): void
    {
        if ($status === 200) {
            return;
        }

        $message = $data['error']['message'] ?? 'Unknown API error';
        $type    = $data['error']['type']    ?? 'api_error';

        if ($status === 401) {
            throw new AuthException($message);
        }

        throw new ApiException(message: $message, type: $type, statusCode: $status);
    }
}
