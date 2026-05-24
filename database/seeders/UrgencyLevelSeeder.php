<?php

namespace Database\Seeders;

use App\Models\Settings\UrgencyLevel;
use Illuminate\Database\Seeder;

class UrgencyLevelSeeder extends Seeder
{
    public function run(): void
    {
        if (UrgencyLevel::count() > 0) {
            return;
        }

        $levels = [
            ['label' => 'Critical', 'emoji' => '🚨', 'color_bg' => '#fee2e2', 'color_text' => '#dc2626', 'subtitle' => 'Today',      'sort_order' => 1, 'show_date_picker' => false],
            ['label' => 'Urgent',   'emoji' => '⚡', 'color_bg' => '#ffedd5', 'color_text' => '#ea580c', 'subtitle' => '1–3 days',   'sort_order' => 2, 'show_date_picker' => false],
            ['label' => 'Normal',   'emoji' => '📋', 'color_bg' => '#fef9c3', 'color_text' => '#ca8a04', 'subtitle' => 'This week',  'sort_order' => 3, 'show_date_picker' => false],
            ['label' => 'Planned',  'emoji' => '🗓️', 'color_bg' => '#f0fdf4', 'color_text' => '#16a34a', 'subtitle' => 'Pick date', 'sort_order' => 4, 'show_date_picker' => true],
        ];

        foreach ($levels as $level) {
            UrgencyLevel::create(array_merge($level, ['is_active' => true]));
        }
    }
}
