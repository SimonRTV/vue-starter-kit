<?php

use App\Models\ApplicationSetting;
use App\Models\Media;
use App\Models\Page;

return [
    'retention_days' => (int) env('ACTIVITY_RETENTION_DAYS', 0),
    'models' => [
        Page::class => ['type' => 'pages', 'fields' => ['title', 'slug', 'is_published', 'published_at', 'body', 'excerpt'], 'redacted' => ['body', 'excerpt']],
        Media::class => ['type' => 'media', 'fields' => ['title', 'visibility', 'alt_text'], 'redacted' => ['alt_text']],
        ApplicationSetting::class => ['type' => 'settings', 'fields' => ['key', 'value'], 'redacted' => ['value']],
    ],
];
