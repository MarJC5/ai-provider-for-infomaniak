<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiProvider\Commands;

/**
 * Discovers and registers markdown command files as WordPress Abilities.
 *
 * Scans configured directories for .md files, parses their frontmatter,
 * creates MarkdownCommand instances, and registers them via the Abilities API.
 *
 * Default scan directories (in priority order):
 * 1. Directories added via the `infomaniak_ai_commands_dirs` filter
 * 2. Active plugins: {plugin}/ai-commands/
 * 3. Active theme: {theme}/ai-commands/
 *
 * When multiple files share the same name, the first one found wins.
 *
 * @since 1.0.0
 */
class CommandLoader
{
    /**
     * Cached discovered commands.
     *
     * @var MarkdownCommand[]|null
     */
    private static ?array $cache = null;

    /**
     * Discovers all markdown command files and returns MarkdownCommand instances.
     *
     * Results are cached for the duration of the request.
     *
     * @since 1.0.0
     *
     * @return MarkdownCommand[] Keyed by command name.
     */
    public static function discover(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $commands = [];
        $dirs = self::getCommandDirs();

        foreach ($dirs as $dir) {
            $files = glob($dir . '/*.md');
            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                $name = basename($file, '.md');

                // Sanitize: only allow lowercase alphanumerics and hyphens.
                $name = preg_replace('/[^a-z0-9-]/', '-', strtolower($name));
                $name = trim($name, '-');

                if ($name === '' || isset($commands[$name])) {
                    continue;
                }

                $content = file_get_contents($file);
                if ($content === false || trim($content) === '') {
                    continue;
                }

                [$config, $body] = FrontmatterParser::parse($content);

                // Description is required.
                if (empty($config['description'])) {
                    continue;
                }

                // Body (prompt template) is required.
                if (trim($body) === '') {
                    continue;
                }

                $config['name'] = $name;

                $commands[$name] = new MarkdownCommand($config, $body);
            }
        }

        self::$cache = $commands;

        return $commands;
    }

    /**
     * Returns the directories to scan for command files, in priority order.
     *
     * @since 1.0.0
     *
     * @return string[] Existing directory paths.
     */
    public static function getCommandDirs(): array
    {
        /**
         * Filters the directories to scan for markdown command files.
         *
         * Add custom directories to make AI commands available from
         * any location on the filesystem.
         *
         * @since 1.0.0
         *
         * @param string[] $dirs Array of directory paths.
         */
        $dirs = (array) apply_filters('infomaniak_ai_commands_dirs', []);

        // Active plugins.
        if (function_exists('wp_get_active_and_valid_plugins')) {
            foreach (wp_get_active_and_valid_plugins() as $pluginFile) {
                $pluginDir = dirname($pluginFile) . '/ai-commands';
                if (!in_array($pluginDir, $dirs, true)) {
                    $dirs[] = $pluginDir;
                }
            }
        }

        // Active theme.
        $themeDir = get_stylesheet_directory() . '/ai-commands';
        if (!in_array($themeDir, $dirs, true)) {
            $dirs[] = $themeDir;
        }

        // Only return directories that exist.
        return array_values(array_filter($dirs, 'is_dir'));
    }

    /**
     * Discovers all commands and registers them as WordPress Abilities.
     *
     * Should be called on the `wp_abilities_api_init` action.
     *
     * @since 1.0.0
     */
    public static function registerAll(): void
    {
        $commands = self::discover();

        foreach ($commands as $command) {
            $command->registerAsAbility();
        }
    }

    /**
     * Clears the discovery cache.
     *
     * Useful for testing or when command files change during the request.
     *
     * @since 1.0.0
     */
    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
