<?php

/**
 * Example: multi-turn conversation with persistent context
 *
 * Run:
 *   ANTHROPIC_API_KEY=sk-ant-... php examples/conversation.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Akit\Client;
use Akit\Config;

$client = new Client(Config::fromEnv());

// Create a conversation — the system prompt applies to all turns
$conv = $client->conversation(
    system: 'You are a concise math tutor. Keep answers to 2-3 sentences.',
);

$r1 = $conv->say('What is the Pythagorean theorem?');
echo "User:      What is the Pythagorean theorem?" . PHP_EOL;
echo "Assistant: " . $r1->text() . PHP_EOL . PHP_EOL;

$r2 = $conv->say('Can you give me a concrete example?');
echo "User:      Can you give me a concrete example?" . PHP_EOL;
echo "Assistant: " . $r2->text() . PHP_EOL . PHP_EOL;

// Claude has context from turn 1, so this question is meaningful
$r3 = $conv->say('What theorem were we just discussing?');
echo "User:      What theorem were we just discussing?" . PHP_EOL;
echo "Assistant: " . $r3->text() . PHP_EOL . PHP_EOL;

echo sprintf(
    "Conversation: %d turns, %d total tokens\n",
    $conv->turns(),
    $r3->totalTokens(),
);
