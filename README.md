# AI Provider for Infomaniak (Unofficial)

An unofficial AI Provider for [Infomaniak AI Tools](https://www.infomaniak.com/en/hosting/ai-tools) for the [PHP AI Client](https://github.com/WordPress/php-ai-client) SDK. Works as both a Composer package and a WordPress plugin.

> **Note:** This plugin is not officially maintained by Infomaniak. It is developed independently by a partner developer.

Provides access to open-source models hosted in Switzerland via Infomaniak's OpenAI-compatible API. Supports text generation, image generation, async batch operations, and usage tracking with per-preset cost attribution.

## Requirements

- PHP 7.4 or higher
- When using with WordPress, requires WordPress 7.0 or higher
    - If using an older WordPress release, the [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) package must be installed
- An Infomaniak account with an AI Tools product

## Installation

### As a WordPress Plugin

1. Download the plugin files
2. Upload to `/wp-content/plugins/ai-provider-for-infomaniak/`
3. Activate the plugin through the WordPress admin
4. Configure your API key in **Settings > Connectors**
5. Configure your Product ID in **Settings > Infomaniak AI**

### As a Composer Package

```bash
composer require wordpress/ai-provider-for-infomaniak
```

## Configuration

### Product ID

The Infomaniak AI product ID can be configured in three ways (checked in this order):

1. **Filter**: Use the `infomaniak_ai_product_id` filter
2. **Constant**: Define `INFOMANIAK_AI_PRODUCT_ID` in `wp-config.php`
3. **Option**: Set it via **Settings > Infomaniak AI** in the WordPress admin

```php
// Via wp-config.php constant
define( 'INFOMANIAK_AI_PRODUCT_ID', '123456' );

// Via filter
add_filter( 'infomaniak_ai_product_id', function() {
    return '123456';
});
```

### API Key

The API key is managed via the WordPress Connectors system at **Settings > Connectors**. The key is stored as `connectors_ai_infomaniak_api_key`.

You can obtain your API key from the [Infomaniak Manager](https://manager.infomaniak.com/v3/ng/products/cloud/ai-tools).

## Usage

### With WordPress

The provider automatically registers itself with the PHP AI Client on the `init` hook. Simply ensure both plugins are active and configure your credentials:

```php
// Use the provider (auto-detected)
$result = wp_ai_client_prompt( 'Hello, world!' )
    ->using_temperature( 0.7 )
    ->generate_text();

// Force the Infomaniak provider
$result = wp_ai_client_prompt( 'Explain quantum computing' )
    ->using_provider( 'infomaniak' )
    ->generate_text();

// Use a specific model
$result = wp_ai_client_prompt( 'Write a haiku' )
    ->using_provider( 'infomaniak' )
    ->using_model_preference( 'llama3' )
    ->generate_text();

// Generate an image
$file = wp_ai_client_prompt( 'A mountain landscape at sunset' )
    ->using_provider( 'infomaniak' )
    ->generate_image();

if ( ! is_wp_error( $file ) ) {
    $dataUri  = $file->getDataUri();   // data:image/png;base64,...
    $mimeType = $file->getMimeType();  // image/png
}
```

### As a Standalone Package

```php
use WordPress\AiClient\AiClient;
use WordPress\InfomaniakAiProvider\Provider\InfomaniakProvider;

// Register the provider
$registry = AiClient::defaultRegistry();
$registry->registerProvider( InfomaniakProvider::class );

// Generate text
$result = AiClient::prompt( 'Explain quantum computing' )
    ->usingProvider( 'infomaniak' )
    ->generateTextResult();

echo $result->toText();
```

## AI Presets

This plugin provides `BasePreset`, an abstract class for building reusable AI commands. Each preset is a self-contained unit combining a prompt template, system instruction, AI configuration, and input validation -- all auto-registered as a [WordPress Ability](https://developer.wordpress.org/abilities/) discoverable via REST API and MCP.

**Why presets?** Without presets, every AI feature requires writing the same boilerplate: build a prompt string, configure the AI client, handle errors, register an ability. With `BasePreset`, you declare *what* the AI should do, and the framework handles *how*.

### Creating a preset

1. Extend `BasePreset` in your own plugin:

```php
namespace MyPlugin\Presets;

use WordPress\InfomaniakAiProvider\Presets\BasePreset;

class SummarizePreset extends BasePreset
{
    public function name(): string { return 'summarize'; }
    public function label(): string { return 'Summarize Content'; }
    public function description(): string { return 'Generates a concise summary.'; }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'content' => ['type' => 'string', 'description' => 'Text to summarize.'],
                'max_sentences' => ['type' => 'integer', 'default' => 3],
            ],
            'required' => ['content'],
        ];
    }

    protected function templateName(): string { return 'summarize'; }
    protected function systemTemplateName(): ?string { return 'content-editor'; }
    protected function maxTokens(): int { return 500; }
}
```

2. Create a PHP template at `your-plugin/templates/presets/summarize.php`:

```php
Summarize the following content:

<?= $content ?>

Requirements:
- Maximum <?= (int) $max_sentences ?> sentences.
- Focus on the main points.
```

3. Register it on `wp_abilities_api_init`:

```php
add_action( 'wp_abilities_api_init', function() {
    $preset = new \MyPlugin\Presets\SummarizePreset();
    $preset->registerAsAbility();
});
```

The preset is now available as:
- **REST API**: `POST /wp-abilities/v1/abilities/infomaniak/summarize/run`
- **MCP tool**: automatically exposed to AI agents
- **PHP**: `$preset->execute(['content' => '...'])`

### How it works

- **PHP templates** -- Prompts are `.php` files rendered with `extract()`. Variables come from `templateData()`, which you can override to transform or enrich input.
- **System prompts** -- Optional `.php` files in a `system/` subdirectory set the AI persona (content editor, SEO expert, etc.).
- **Auto-detection** -- `BasePreset` finds templates relative to your plugin root automatically via `ReflectionClass`. No path configuration needed.
- **Structured output** -- Override `outputSchema()` to return a JSON Schema and the preset will use `as_json_response()` and decode the result automatically.
- **Image generation** -- Override `execute()` to call `generate_image()` instead of `generate_text()`. Use `ModelConfig` to set orientation and other image options. Override `modelType()` to return `'image'`.
- **Model preference** -- Call `setModelPreference()` at runtime to override the model, or override `modelPreference()` for a default.
- **Provider override** -- Override `provider()` to use a different AI provider (defaults to `'infomaniak'`).
- **Extensible** -- Use the `infomaniak_ai_presets` filter to add or remove presets from any plugin.

### Overridable methods

| Method | Default | Description |
|---|---|---|
| `temperature()` | `0.7` | Controls response randomness |
| `maxTokens()` | `1000` | Maximum response length |
| `outputSchema()` | `null` | JSON Schema for structured output |
| `provider()` | `'infomaniak'` | AI provider ID |
| `category()` | `'content'` | Ability category slug |
| `permission()` | `'edit_posts'` | Required WordPress capability |
| `annotations()` | `readonly, non-destructive` | MCP behavioral annotations |
| `templateData($input)` | passthrough | Transform input before rendering |
| `modelPreference()` | `null` | Preferred model ID (SDK picks if null) |
| `modelType()` | `'llm'` | Model type: `'llm'` or `'image'` |

### Examples

See the [`examples/`](examples/) directory for complete, copy-paste-ready presets:

- **[basic-preset.php](examples/basic-preset.php)** -- Minimal preset with a prompt template and system instruction
- **[json-output-preset.php](examples/json-output-preset.php)** -- Structured JSON output with `outputSchema()`
- **[post-aware-preset.php](examples/post-aware-preset.php)** -- Fetches WordPress post data via `templateData()` and validates with a custom `execute()` override
- **[image-preset.php](examples/image-preset.php)** -- Image generation with a custom `execute()` override using `generate_image()` and `ModelConfig`

## Supported Models

Available models are dynamically discovered from the Infomaniak API. The provider supports two model types:

- **Text generation (LLM)** -- Open-source models such as Llama (Meta), Mistral / Mixtral (Mistral AI), DeepSeek, Qwen (Alibaba)
- **Image generation** -- Models available through Infomaniak's image generation API

All models are hosted in Switzerland by Infomaniak. See the [Infomaniak AI Tools documentation](https://www.infomaniak.com/en/hosting/ai-tools) for the full list of available models.

## License

GPL-2.0-or-later
