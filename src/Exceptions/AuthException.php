<?php

declare(strict_types=1);

namespace Akit\Exceptions;

/**
 * Thrown when authentication fails (401 from the API).
 *
 * This almost always means the API key is invalid, missing, or expired.
 */
class AuthException extends ApiException
{
    public function __construct(
        string      $message  = 'Authentication failed. Verify your Anthropic API key.',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message:    $message,
            type:       'authentication_error',
            statusCode: 401,
            previous:   $previous,
        );
    }
}
