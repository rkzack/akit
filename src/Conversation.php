<?php

declare(strict_types=1);

namespace Akit;

/**
 * A stateful multi-turn conversation with automatic message history.
 *
 * Create via Client::conversation() rather than instantiating directly.
 *
 *   $conv = $client->conversation(system: 'You are a helpful assistant.');
 *   $conv->say('Hello!');
 *   $conv->say('What did I just say?'); // Claude remembers
 */
final class Conversation
{
    /** @var Message[] */
    private array $messages = [];

    public function __construct(
        private readonly Client  $client,
        private readonly ?string $system = null,
    ) {}

    /**
     * Send a user message and get the assistant's reply.
     *
     * The message and reply are automatically appended to the history so
     * subsequent calls to say() have full context.
     */
    public function say(
        string  $message,
        ?string $model     = null,
        ?int    $maxTokens = null,
    ): Response {
        $this->messages[] = Message::user($message);

        $response = $this->client->chat(
            messages:  $this->messages,
            system:    $this->system,
            model:     $model,
            maxTokens: $maxTokens,
        );

        $this->messages[] = $response->asMessage();

        return $response;
    }

    /**
     * All messages exchanged so far (user and assistant turns interleaved).
     *
     * @return Message[]
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * Number of user turns sent so far.
     */
    public function turns(): int
    {
        return count(array_filter($this->messages, fn(Message $m) => $m->role === 'user'));
    }

    /**
     * Clear the message history (system prompt is retained).
     */
    public function reset(): void
    {
        $this->messages = [];
    }
}
