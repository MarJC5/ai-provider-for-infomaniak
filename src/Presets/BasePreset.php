<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiProvider\Presets;

use WordPress\InfomaniakAiProvider\Usage\UsageTracker;

/**
 * Abstract base class for AI prompt presets.
 *
 * Each preset defines a reusable prompt template that can be executed
 * via the WordPress AI Client and auto-registered as a WordPress Ability
 * for REST API and MCP discoverability.
 *
 * @since 1.0.0
 */
abstract class BasePreset
{
    /**
     * Returns the preset identifier (e.g. 'summarize').
     *
     * @since 1.0.0
     *
     * @return string
     */
    abstract public function name(): string;

    /**
     * Returns a human-readable label.
     *
     * @since 1.0.0
     *
     * @return string
     */
    abstract public function label(): string;

    /**
     * Returns a description of what this preset does.
     *
     * @since 1.0.0
     *
     * @return string
     */
    abstract public function description(): string;

    /**
     * Returns the JSON Schema for the preset's input parameters.
     *
     * @since 1.0.0
     *
     * @return array
     */
    abstract public function inputSchema(): array;

    /**
     * Returns the template file name (without extension) in templates/presets/.
     *
     * @since 1.0.0
     *
     * @return string
     */
    abstract protected function templateName(): string;

    /**
     * Prepares data to inject into the template.
     *
     * Override this to transform or enrich input data before rendering.
     *
     * @since 1.0.0
     *
     * @param array $input Raw input parameters.
     * @return array Data to extract into the template scope.
     */
    protected function templateData(array $input): array
    {
        return $input;
    }

    /**
     * Returns the system prompt template name, or null if none.
     *
     * @since 1.0.0
     *
     * @return string|null Template name in templates/presets/system/ (without extension).
     */
    protected function systemTemplateName(): ?string
    {
        return null;
    }

    /**
     * Returns the temperature for generation.
     *
     * @since 1.0.0
     *
     * @return float
     */
    protected function temperature(): float
    {
        return 0.7;
    }

    /**
     * Returns the max tokens for generation.
     *
     * @since 1.0.0
     *
     * @return int
     */
    protected function maxTokens(): int
    {
        return 1000;
    }

    /**
     * Model preference override set at runtime.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    private ?string $modelPreferenceOverride = null;

    /**
     * Sets a runtime model preference override.
     *
     * When set, this takes precedence over the value returned by modelPreference().
     * Pass null to clear the override.
     *
     * @since 1.0.0
     *
     * @param string|null $modelId The model ID to use, or null to clear.
     */
    public function setModelPreference(?string $modelId): void
    {
        $this->modelPreferenceOverride = $modelId;
    }

    /**
     * Returns the preferred model ID, or null to let the SDK pick.
     *
     * Override in child presets to target a specific model.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    protected function modelPreference(): ?string
    {
        return $this->modelPreferenceOverride;
    }

    /**
     * Returns a JSON Schema for structured output, or null for plain text.
     *
     * @since 1.0.0
     *
     * @return array|null
     */
    protected function outputSchema(): ?array
    {
        return null;
    }

    /**
     * Returns an array describing the output for the Ability registration.
     *
     * @since 1.0.0
     *
     * @return array JSON Schema for ability output.
     */
    protected function outputAbilitySchema(): array
    {
        return [
            'type' => 'string',
            'description' => __('The generated text.', 'ai-provider-for-infomaniak'),
        ];
    }

    /**
     * Returns the model type this preset requires ('llm' or 'image').
     *
     * Override in child presets that use a different model type.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function modelType(): string
    {
        return 'llm';
    }

    /**
     * Returns the ability category slug.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected function category(): string
    {
        return 'content';
    }

    /**
     * Returns the required WordPress capability.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected function permission(): string
    {
        return 'edit_posts';
    }

    /**
     * Returns MCP annotations for the ability.
     *
     * @since 1.0.0
     *
     * @return array
     */
    protected function annotations(): array
    {
        return [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ];
    }

    /**
     * Renders a PHP template file with the given data.
     *
     * @since 1.0.0
     *
     * @param string $template Path to the template file.
     * @param array  $data     Variables to extract into the template scope.
     * @return string Rendered template output.
     */
    protected function render(string $template, array $data): string
    {
        if (!file_exists($template)) {
            return '';
        }

        ob_start();
        // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
        extract($data, EXTR_SKIP);
        include $template;
        return trim((string) ob_get_clean());
    }

    /**
     * Returns the provider ID to use for prompt execution.
     *
     * Override this to use a different provider.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected function provider(): string
    {
        return 'infomaniak';
    }

    /**
     * Returns the base path to the templates directory.
     *
     * Auto-detects the plugin directory of the concrete child class,
     * so presets in any plugin will find their own templates.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected function templatesPath(): string
    {
        $reflector = new \ReflectionClass(static::class);
        $childFile = $reflector->getFileName();

        // Walk up from the child class file to find the plugin root
        // (the directory containing a PHP file with a "Plugin Name:" header).
        $dir = dirname($childFile);
        while ($dir !== dirname($dir)) {
            foreach (glob($dir . '/*.php') as $file) {
                // Quick check for plugin header without reading the full file.
                $header = file_get_contents($file, false, null, 0, 8192);
                if ($header !== false && str_contains($header, 'Plugin Name:')) {
                    return $dir . '/templates/presets';
                }
            }
            $dir = dirname($dir);
        }

        // Fallback: relative to the child class, go up to src/../templates/presets.
        return dirname($childFile, 3) . '/templates/presets';
    }

    /**
     * Sets the usage tracking context to the current preset.
     *
     * Call this before making AI calls in custom execute() overrides
     * to ensure usage is attributed to this preset.
     *
     * @since 1.0.0
     */
    protected function setTrackingContext(): void
    {
        if (class_exists(UsageTracker::class)) {
            UsageTracker::setCurrentPreset($this->name());
        }
    }

    /**
     * Clears the usage tracking context.
     *
     * Call this in a finally block after AI calls in custom execute() overrides.
     *
     * @since 1.0.0
     */
    protected function clearTrackingContext(): void
    {
        if (class_exists(UsageTracker::class)) {
            UsageTracker::clearCurrentPreset();
        }
    }

    /**
     * Executes the preset with the given input.
     *
     * Builds the prompt from templates, configures the AI client,
     * and returns the generated result. Sets tracking context so
     * usage is attributed to this preset.
     *
     * @since 1.0.0
     *
     * @param array $input Input parameters matching the inputSchema.
     * @return string|array|\WP_Error Generated text or structured data.
     */
    public function execute(array $input)
    {
        if (!function_exists('wp_ai_client_prompt')) {
            return new \WP_Error(
                'ai_unavailable',
                __('AI Client is not available.', 'ai-provider-for-infomaniak')
            );
        }

        $this->setTrackingContext();

        try {
            $data = $this->templateData($input);

            // Render user prompt template.
            $promptText = $this->render(
                $this->templatesPath() . '/' . $this->templateName() . '.php',
                $data
            );

            if (empty($promptText)) {
                return new \WP_Error(
                    'preset_template_error',
                    __('Failed to render prompt template.', 'ai-provider-for-infomaniak')
                );
            }

            // Build the prompt.
            $builder = wp_ai_client_prompt($promptText)
                ->using_provider($this->provider())
                ->using_temperature($this->temperature())
                ->using_max_tokens($this->maxTokens());

            $modelPref = $this->modelPreference();
            if ($modelPref !== null) {
                $builder->using_model_preference($modelPref);
            }

            // Add system instruction if defined.
            $systemTemplate = $this->systemTemplateName();
            if ($systemTemplate !== null) {
                $systemText = $this->render(
                    $this->templatesPath() . '/system/' . $systemTemplate . '.php',
                    $data
                );
                if (!empty($systemText)) {
                    $builder->using_system_instruction($systemText);
                }
            }

            // Configure JSON output if schema is defined.
            $outputSchema = $this->outputSchema();
            if ($outputSchema !== null) {
                $builder->as_json_response($outputSchema);
            }

            $result = $builder->generate_text();

            if (is_wp_error($result)) {
                return $result;
            }

            // Decode JSON responses.
            if ($outputSchema !== null) {
                $decoded = json_decode($result, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }

            return $result;
        } finally {
            $this->clearTrackingContext();
        }
    }

    /**
     * Registers this preset as a WordPress Ability.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function registerAsAbility(): void
    {
        if (!function_exists('wp_register_ability')) {
            return;
        }

        $preset = $this;

        wp_register_ability(
            $this->provider() . '/' . $this->name(),
            [
                'label' => $this->label(),
                'description' => $this->description(),
                'category' => $this->category(),
                'execute_callback' => function ($input) use ($preset) {
                    return $preset->execute($input ?? []);
                },
                'permission_callback' => function () use ($preset) {
                    return current_user_can($preset->permission());
                },
                'input_schema' => $this->inputSchema(),
                'output_schema' => $this->outputAbilitySchema(),
                'meta' => [
                    'annotations' => $this->annotations(),
                    'show_in_rest' => true,
                ],
            ]
        );
    }
}
