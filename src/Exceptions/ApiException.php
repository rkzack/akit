<?php

declare(strict_types=1);

namespace Akit\Exceptions;

/**
 * Thrown when the Anthropic API returns a non-200 response.
 *
 * Check $e->statusCode and $e->type for structured error handling:
 *
 *   try {
 *       $client->prompt('...');
 *   } catch (ApiException $e) {
 *       match ($e->statusCode) {
 *           429 => handleRateLimit(),
 *           529 => handleOverload(),
 *           default => throw $e,
 *       };
 *   }
 */
class ApiException extends AkitException
{
    public function __construct(
        string              $message,
        public readonly string $type       = 'api_error',
        public readonly int    $statusCode = 0,
        ?\Throwable         $previous      = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
