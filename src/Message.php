<?php

declare(strict_types=1);

namespace Akit;

/**
 * A single message in a conversation (either user or assistant).
 *
 * Use the named constructors for clarity:
 *   Message::user('Hello!')
 *   Message::assistant('Hi there!')
 */
readonly class Message
{
    public function __construct(
        public string $role,
        public string $content,
    ) {}

    public static function user(string $content): self
    {
        return new self(role: 'user', content: $content);
    }

    public static function assistant(string $content): self
    {
        return new self(role: 'assistant', content: $content);
    }

    /** @return array{role: string, content: string} */
    public function toArray(): array
    {
        return ['role' => $this->role, 'content' => $this->content];
    }
}
