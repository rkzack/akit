# CLAUDE.md — akit Development Guide

akit is a **lightweight, zero-production-dependency PHP 8.4 toolkit** for the Anthropic Messages API.
Its goal is to let a developer drop it into any vanilla PHP project and start sending prompts in
under five minutes.

---

## Repository layout

```
akit/
├── src/
│   ├── Client.php          # Primary public surface — all API calls go through here
│   ├── Config.php          # Readonly config value object; Config::fromEnv() factory
│   ├── Conversation.php    # Stateful multi-turn wrapper around Client::chat()
│   ├── Message.php         # Readonly value object for user/assistant messages
│   ├── Model.php           # Backed enum of known model IDs
│   ├── Response.php        # Wraps the decoded API JSON; array_find for text block
│   └── Exceptions/
│       ├── AkitException.php   # Base exception
│       ├── ApiException.php    # HTTP 4xx/5xx from Anthropic (has statusCode + type)
│       └── AuthException.php   # HTTP 401 specialisation
├── examples/
│   ├── basic_prompt.php    # Single-turn prompt examples
│   ├── conversation.php    # Multi-turn Conversation walkthrough
│   └── streaming.php       # Streaming via CURL WRITEFUNCTION
├── tests/
│   └── ClientTest.php      # Unit tests + @group integration live tests
├── helpers.php             # Global helper functions (akit_prompt, akit_stream, etc.)
├── composer.json           # PSR-4 autoload; helpers.php in "files"
├── phpunit.xml.dist        # Excludes @group integration by default
└── .env.example            # Copy to .env; never commit .env
```

---

## Design principles

**Zero production dependencies.**
The only extensions required are `ext-curl` and `ext-json`, both bundled with standard PHP.
Do not add Guzzle, Symfony HttpClient, or any other HTTP library as a production dependency.
If HTTP behaviour needs to change (e.g. retry logic), implement it in `Client` directly.

**Immutable value objects.**
`Config`, `Message`, and `Response` are `readonly` classes (PHP 8.2+, available in 8.4).
They should never be mutated after construction. `Conversation` is the only stateful class,
intentionally so — it is a thin session manager, not a god object.

**Flat, discoverable API.**
The public surface is small: `Client`, `Config`, `Conversation`, `Message`, `Response`,
`Model`, and four global helpers. Resist adding new public classes or methods unless they
address a clearly recurring use case.

**PHP 8.4 features in use.**
- `readonly class` — Config, Message (immutable value objects)
- Named arguments — all public method signatures use named params for readability
- Constructor promotion — every class uses it; no manual property assignments
- Backed enums — `Model` provides autocomplete for model IDs
- `array_find()` — Response::text() uses it (PHP 8.4 stdlib addition)
- `JSON_THROW_ON_ERROR` — all json_encode/decode calls throw on failure

Do not use PHP 8.4 features gratuitously (e.g. property hooks) where simpler patterns work.
The goal is clarity, not a feature showcase.

---

## Adding a new feature

Before adding anything, ask: *does this belong in the toolkit, or in the user's application?*

Things that belong here:
- Core Anthropic API primitives (Messages, streaming, vision, tool use when Anthropic adds them)
- Config ergonomics that affect every user
- Exception types that map to API error categories

Things that do NOT belong here:
- Retry logic with backoff (users have different retry policies)
- Prompt templating / rendering
- Conversation persistence (database, Redis, filesystem)
- Rate-limit budgeting
- Logging / observability

If adding a new API capability (e.g. vision, tool use):
1. Add a method to `Client` — keep the signature consistent with `prompt()` and `chat()`.
2. Add a corresponding global helper in `helpers.php` if it has a common one-liner use case.
3. Update `README.md` — the API reference table and a usage example.
4. Add unit tests (mock the HTTP response) and an `@group integration` live test.

---

## The HTTP layer

All HTTP is done with cURL in two private methods in `Client`:

- `post()` — synchronous, returns decoded array
- `postStream()` — uses `CURLOPT_WRITEFUNCTION` to parse SSE events line-by-line

The SSE parser in `postStream` is intentionally simple: it buffers partial chunks,
splits on newlines, looks for `data: ` prefixed lines, and decodes `content_block_delta`
events with `text_delta` type. If Anthropic adds new event types you want to expose,
extend the write function — do not add a new cURL handle.

The `CURLOPT_RETURNTRANSFER => false` + write function pattern means curl writes nothing
to the return buffer; the response is consumed entirely via the callback. This avoids
buffering the whole stream in memory.

---

## Testing strategy

**Unit tests** (`tests/ClientTest.php`, no API key needed):
- Test Config validation, Message construction, Model enum values
- Test Client accepts both string and Config constructor args
- Do NOT mock the HTTP layer in unit tests — instead test everything that does not require
  a live API call (construction, value objects, serialisation)

**Integration tests** (`@group integration`):
- Excluded from the default PHPUnit run (`phpunit.xml.dist` excludes the group)
- Require `ANTHROPIC_API_KEY` in the environment
- Test the full round trip: real HTTP request, real response, real token counts
- Use the cheapest model available (`Model::HAIKU_4_5`) to keep costs low
- Keep assertions loose (e.g. `assertStringContainsStringIgnoringCase`) — model responses vary

Run integration tests explicitly:
```bash
ANTHROPIC_API_KEY=sk-ant-... ./vendor/bin/phpunit --group integration
```

---

## Common patterns

### Checking the stop reason before using a response

```php
$response = $client->prompt('Summarise this document...', maxTokens: 256);

if ($response->stopReason() === 'max_tokens') {
    // The response was cut off — consider increasing maxTokens or chunking input
}
```

### Reusing a conversation system prompt with different histories

```php
$systemPrompt = 'You are an expert PHP developer. Be concise.';

// Start fresh for each user session
$conv = $client->conversation(system: $systemPrompt);
```

### Passing the full API version override

```php
$config = new Config(
    apiKey:     getenv('ANTHROPIC_API_KEY'),
    apiVersion: '2023-06-01', // pin to a specific version for reproducibility
);
```

### Streaming to a browser (SSE / chunked transfer)

```php
header('Content-Type: text/event-stream');
header('X-Accel-Buffering: no'); // disable nginx buffering

$client->stream('Tell me a story', function (string $chunk): void {
    echo "data: " . json_encode(['text' => $chunk]) . "\n\n";
    ob_flush();
    flush();
});
```

---

## Release checklist

- [ ] Bump `version` if added to `composer.json`
- [ ] All unit tests pass: `./vendor/bin/phpunit`
- [ ] Static analysis clean: `./vendor/bin/phpstan analyse --level=8`
- [ ] Integration tests pass against live API
- [ ] README API reference table matches current method signatures
- [ ] `Model` enum contains the latest model IDs from [Anthropic docs](https://docs.anthropic.com/en/docs/about-claude/models)
- [ ] `.env.example` updated if new env vars were added
- [ ] `CHANGELOG.md` updated (create if it doesn't exist)
