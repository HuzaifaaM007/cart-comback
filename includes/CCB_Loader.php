<?php

declare(strict_types=1);

namespace CartComback\includes;

if (!defined('ABSPATH')) {
    exit();
}

class CCB_Loader
{

    private $actions    = [];
    private $filters    = [];
    private $shortcodes = [];

    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1): void
    {
        $this->actions[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }

    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1): void
    {
        $this->filters[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        ];
    }

    /**
     * Register a shortcode via the loader.
     * Previously stored in $this->short_codes (note the underscore) but the
     * run() method iterated $this->shortcodes — so shortcodes were NEVER
     * actually registered. Fixed: one property name, always $this->shortcodes.
     */
    public function add_shortcode($tag, $component, $callback): void
    {
        $this->shortcodes[] = [
            'hook'      => $tag,
            'component' => $component,
            'callback'  => $callback,
        ];
    }

    public function run(): void
    {
        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        // were silently dropped and never added to WordPress.
        foreach ($this->shortcodes as $sc) {
            add_shortcode(
                $sc['hook'],
                [$sc['component'], $sc['callback']]
            );
        }
    }
}
