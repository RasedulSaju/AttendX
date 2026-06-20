<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Simple layout engine.
 * Views call $this->layout('layouts/main', ['title' => '...'])
 * and $this->start('content') / $this->stop() to define sections.
 *
 * Usage in view files:
 *   <?php $this->layout('layouts/main', ['title' => 'Page Title']) ?>
 *   <?php $this->start('content') ?>
 *     <p>Page content here</p>
 *   <?php $this->stop() ?>
 */
class View
{
    private string $layoutFile = '';
    private array  $layoutData  = [];
    private array  $sections    = [];
    private string $currentSection = '';
    private array  $data        = [];

    public function render(string $viewPath, array $data = []): string
    {
        $this->data = $data;

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: $viewPath");
        }

        // Bind view methods to $this so views can call $this->layout(), etc.
        $renderer = \Closure::bind(function () use ($viewPath) {
            extract($this->data, EXTR_SKIP);
            ob_start();
            include $viewPath;
            return ob_get_clean();
        }, $this, static::class);

        $viewOutput = $renderer();

        // If a layout was set, render it
        if ($this->layoutFile) {
            $layoutPath = base_path("app/Views/{$this->layoutFile}.php");
            if (!file_exists($layoutPath)) {
                throw new \RuntimeException("Layout not found: {$this->layoutFile}");
            }

            $layoutData = array_merge($this->data, $this->layoutData, [
                '_sections' => $this->sections,
                '_view'     => $this,
            ]);
            // 'content' section defaults to view output if not explicitly defined
            if (!isset($this->sections['content'])) {
                $this->sections['content'] = $viewOutput;
                $layoutData['_sections']['content'] = $viewOutput;
            }

            $layoutRenderer = \Closure::bind(function () use ($layoutPath, $layoutData) {
                extract($layoutData, EXTR_SKIP);
                ob_start();
                include $layoutPath;
                return ob_get_clean();
            }, $this, static::class);

            return $layoutRenderer();
        }

        return $viewOutput;
    }

    // ─── Called from view files ───────────────────────────────────────────────

    /** Set the layout for this view. */
    protected function layout(string $layout, array $data = []): void
    {
        $this->layoutFile = $layout;
        $this->layoutData = $data;
    }

    /** Start capturing a named section. */
    protected function start(string $name): void
    {
        $this->currentSection = $name;
        ob_start();
    }

    /** Stop capturing the current section. */
    protected function stop(): void
    {
        $this->sections[$this->currentSection] = ob_get_clean();
        $this->currentSection = '';
    }

    /** Render a named section in the layout. */
    protected function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /** Include a partial view. */
    protected function partial(string $partial, array $data = []): void
    {
        $path = base_path("app/Views/{$partial}.php");
        if (!file_exists($path)) return;
        $merged = array_merge($this->data, $data);
        extract($merged, EXTR_SKIP);
        include $path;
    }

    // ─── Static factory ───────────────────────────────────────────────────────

    public static function make(string $view, array $data = []): string
    {
        $path = base_path("app/Views/{$view}.php");
        return (new static())->render($path, $data);
    }
}
