<?php

/**
 * Example: single-turn prompts
 *
 * Run:
 *   ANTHROPIC_API_KEY=sk-ant-... php examples/basic_prompt.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Akit\Client;
use Akit\Config;
use Akit\Model;

// ── Option 1: global helper (reads ANTHROPIC_API_KEY from env) ───────────────
$answer = akit_prompt('What is the capital of France?');
echo $answer . PHP_EOL;

// ── Option 2: client with env config ─────────────────────────────────────────
$client = new Client(Config::fromEnv());

$response = $client->prompt('What is 2 + 2?');
echo $response->text()    . PHP_EOL;
echo 'Tokens: ' . $response->totalTokens() . PHP_EOL;

// ── Option 3: inline API key + system prompt ──────────────────────────────────
$client = new Client(apiKey: getenv('ANTHROPIC_API_KEY'));

$response = $client->prompt(
    prompt: 'Give me a greeting.',
    system: 'You are a friendly pirate. Always respond in pirate speak.',
);
echo $response . PHP_EOL; // Response implements __toString

// ── Option 4: choose a specific model ────────────────────────────────────────
$response = $client->prompt(
    prompt: 'Name three cloud providers.',
    model:  Model::HAIKU_4_5->value,
);
echo $response->text()  . PHP_EOL;
echo 'Model: ' . $response->model() . PHP_EOL;
