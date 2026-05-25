<?php

declare(strict_types=1);

namespace Akit;

/**
 * Wraps a successful response from the Anthropic Messages API.
 *
 * Cast to string or call ->text() to get the reply content.
 */
final class Response
{
    public function __construct(
        private readonly array $data,
    ) {}

    /**
     * The primary text content from the first text block in the response.
     */
    public function text(): string
    {
        $block = array_find(
            $this->data['content'],
            fn(array $b) => $b['type'] === 'text',
        );

        return $block['text'] ?? '';
    }

    /**
     * All raw content blocks (text, tool_use, etc.).
     */
    public function content(): array
    {
        return $this->data['content'];
    }

    /**
     * The model ID that generated this response (e.g. "claude-sonnet-4-6").
     */
    public function model(): string
    {
        return $this->data['model'];
    }

    /**
     * Why generation stopped: "end_turn", "max_tokens", "stop_sequence", or "tool_use".
     */
    public function stopReason(): string
    {
        return $this->data['stop_reason'];
    }

    /**
     * Token usage for this request.
     *
     * @return array{input_tokens: int, output_tokens: int}
     */
    public function usage(): array
    {
        return $this->data['usage'];
    }

    /**
     * Combined input and output token count.
     */
    public function totalTokens(): int
    {
        return $this->data['usage']['input_tokens'] + $this->data['usage']['output_tokens'];
    }

    /**
     * The unique message ID assigned by Anthropic (e.g. "msg_01XFDUDYJgAACzvnptvVoYEL").
     */
    public function id(): string
    {
        return $this->data['id'];
    }

    /**
     * Build an assistant Message from this response (for conversation history).
     */
    public function asMessage(): Message
    {
        return Message::assistant($this->text());
    }

    /**
     * Access the raw decoded API response payload.
     */
    public function raw(): array
    {
        return $this->data;
    }

    public function __toString(): string
    {
        return $this->text();
    }
}
