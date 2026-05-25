<?php

declare(strict_types=1);

namespace Akit;

/**
 * Available Anthropic model identifiers.
 *
 * Use the ->value property when passing to Client methods:
 *   $client->prompt('Hello', model: Model::SONNET_4_6->value);
 */
enum Model: string
{
    case OPUS_4_7     = 'claude-opus-4-7';
    case SONNET_4_6   = 'claude-sonnet-4-6';
    case HAIKU_4_5    = 'claude-haiku-4-5-20251001';
}
