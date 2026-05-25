<?php

declare(strict_types=1);

use Akit\Client;
use Akit\Config;
use Akit\Message;
use Akit\Response;

/**
 * Global helper functions for quick, script-style usage of akit.
 *
 * All helpers read ANTHROPIC_API_KEY from the environment and share a single
 * lazily-created Client instance. For production or multi-key scenarios,
 * instantiate Client directly instead.
 */

if (!function_exists('akit_client')) {
    /**
     * Return (or create) the shared singleton Akit client.
     *
     * Pass $apiKey to override the ANTHROPIC_API_KEY env var or to force a
     * fresh client with a different key. Subsequent calls without $apiKey
     * reuse the same instance.
     */
    function akit_client(?string $apiKey = null, ?string $model = null): Client
    {
        static $client = null;

        if ($client === null || $apiKey !== null) {
            $config = $apiKey !== null
                ? new Config(apiKey: $apiKey, model: $model ?? Config::DEFAULT_MODEL)
                : Config::fromEnv(model: $model ?? Config::DEFAULT_MODEL);

            $client = new Client($config);
        }

        return $client;
    }
}

if (!function_exists('akit_prompt')) {
    /**
     * Send a single prompt and return the response text.
     *
     *   echo akit_prompt('What is the capital of France?');
     *   echo akit_prompt('Write a limerick.', system: 'You are a poet.');
     */
    function akit_prompt(
        string  $prompt,
        ?string $system    = null,
        ?string $model     = null,
        ?int    $maxTokens = null,
    ): string {
        return akit_client(model: $model)
            ->prompt($prompt, system: $system, maxTokens: $maxTokens)
            ->text();
    }
}

if (!function_exists('akit_stream')) {
    /**
     * Stream a response and invoke $onChunk with each text delta.
     *
     *   akit_stream('Tell me a joke', function(string $chunk) {
     *       echo $chunk;
     *       flush();
     *   });
     */
    function akit_stream(
        string   $prompt,
        callable $onChunk,
        ?string  $system = null,
        ?string  $model  = null,
    ): void {
        akit_client(model: $model)->stream($prompt, $onChunk, system: $system);
    }
}

if (!function_exists('akit_chat')) {
    /**
     * Send a plain array of messages and return a Response.
     *
     * Each element must be an array with 'role' (user|assistant) and 'content'.
     *
     *   $response = akit_chat([
     *       ['role' => 'user',      'content' => 'Hello!'],
     *       ['role' => 'assistant', 'content' => 'Hi there!'],
     *       ['role' => 'user',      'content' => 'What did I say first?'],
     *   ]);
     *   echo $response->text();
     */
    function akit_chat(
        array   $messages,
        ?string $system    = null,
        ?string $model     = null,
        ?int    $maxTokens = null,
    ): Response {
        $objects = array_map(
            fn(array $m) => new Message($m['role'], $m['content']),
            $messages,
        );

        return akit_client(model: $model)
            ->chat($objects, system: $system, maxTokens: $maxTokens);
    }
}
