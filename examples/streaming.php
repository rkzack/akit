<?php

/**
 * Example: streaming responses
 *
 * Chunks are printed as they arrive rather than waiting for the full response.
 * Useful for long generations or interactive CLI tools.
 *
 * Run:
 *   ANTHROPIC_API_KEY=sk-ant-... php examples/streaming.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Akit\Client;
use Akit\Config;
use Akit\Message;

$client = new Client(Config::fromEnv());

// ── Basic streaming via helper ────────────────────────────────────────────────
echo "--- Streaming a haiku ---" . PHP_EOL;

akit_stream(
    prompt:  'Write a haiku about open-source software.',
    onChunk: function (string $chunk): void {
        echo $chunk;
        // In a web context, also call ob_flush() and flush() here.
    },
);

echo PHP_EOL . PHP_EOL;

// ── Streaming a conversation (manual message array) ───────────────────────────
echo "--- Streaming with prior context ---" . PHP_EOL;

$messages = [
    Message::user('My favourite number is 42.'),
    Message::assistant('Got it — your favourite number is 42!'),
    Message::user('What is my favourite number doubled?'),
];

$client->stream(
    prompt:  $messages,
    onChunk: fn(string $chunk) => print($chunk),
    system:  'Be brief.',
);

echo PHP_EOL;
