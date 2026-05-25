<?php

declare(strict_types=1);

namespace Akit;

/**
 * Immutable configuration for the Akit client.
 *
 * Construct directly or use Config::fromEnv() to read from the
 * ANTHROPIC_API_KEY environment variable.
 */
readonly class Config
{
    public const string API_BASE_URL   = 'https://api.anthropic.com/v1';
    public const string API_VERSION    = '2023-06-01';
    public const string DEFAULT_MODEL  = 'claude-sonnet-4-6';
    public const int    DEFAULT_MAX_TOKENS = 1024;

    public function __construct(
        public string $apiKey,
        public string $model      = self::DEFAULT_MODEL,
        public int    $maxTokens  = self::DEFAULT_MAX_TOKENS,
        public string $baseUrl    = self::API_BASE_URL,
        public string $apiVersion = self::API_VERSION,
        public int    $timeout    = 30,
    ) {
        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('Anthropic API key cannot be empty.');
        }
    }

    /**
     * Create a Config by reading ANTHROPIC_API_KEY from the environment.
     *
     * @throws \RuntimeException if the env var is not set
     */
    public static function fromEnv(
        string $model     = self::DEFAULT_MODEL,
        int    $maxTokens = self::DEFAULT_MAX_TOKENS,
    ): self {
        $apiKey = getenv('ANTHROPIC_API_KEY') ?: ($_ENV['ANTHROPIC_API_KEY'] ?? '');

        if (empty($apiKey)) {
            throw new \RuntimeException(
                'The ANTHROPIC_API_KEY environment variable is not set. '
                . 'Set it in your shell or .env file before using akit.'
            );
        }

        return new self(apiKey: $apiKey, model: $model, maxTokens: $maxTokens);
    }
}
