<?php

namespace App\Support;

/**
 * The permission catalogue, read from config/access.php.
 *
 * Every permission in the system is `<tab>.<action>`, so the Users page can
 * render a grid of tabs against actions and an Admin can grant any square of it
 * to anyone — whatever profile they hold.
 */
class AccessCatalog
{
    /** Every permission name the system knows about. */
    public static function permissions(): array
    {
        $names = [];

        foreach (config('access.tabs') as $tab => $definition) {
            foreach ($definition['actions'] as $action) {
                $names[] = "{$tab}.{$action}";
            }

            foreach (array_keys($definition['extra'] ?? []) as $action) {
                $names[] = "{$tab}.{$action}";
            }
        }

        return $names;
    }

    /**
     * The grid the access form draws: tabs in sidebar order, grouped, each
     * carrying the squares it offers.
     */
    public static function grid(): array
    {
        $labels = config('access.action_labels');

        return collect(config('access.tabs'))
            ->map(fn ($definition, $tab) => [
                'tab' => $tab,
                'group' => $definition['group'],
                'label' => $definition['label'],
                'actions' => collect($definition['actions'])
                    ->map(fn ($action) => [
                        'name' => "{$tab}.{$action}",
                        'action' => $action,
                        'label' => $labels[$action] ?? ucfirst($action),
                    ])->values()->all(),
                'extra' => collect($definition['extra'] ?? [])
                    ->map(fn ($label, $action) => [
                        'name' => "{$tab}.{$action}",
                        'action' => $action,
                        'label' => $label,
                    ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** The profiles, in config order, with what each one starts a person with. */
    public static function profiles(): array
    {
        return collect(config('access.profiles'))
            ->map(fn ($definition, $name) => [
                'name' => $name,
                'description' => $definition['description'] ?? '',
                'permissions' => $definition['permissions'],
            ])
            ->values()
            ->all();
    }

    public static function profileNames(): array
    {
        return array_keys(config('access.profiles'));
    }
}
