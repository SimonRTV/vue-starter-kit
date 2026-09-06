<?php

$presets = [
    'website' => ['pages' => true, 'public_site' => true, 'media' => true, 'activity' => true, 'notifications' => true],
    'portal' => ['pages' => true, 'public_site' => false, 'media' => true, 'activity' => true, 'notifications' => true],
    'business' => ['pages' => false, 'public_site' => false, 'media' => true, 'activity' => true, 'notifications' => true],
];

$preset = env('STARTER_PRESET', 'website');

if (! is_string($preset) || ! isset($presets[$preset])) {
    throw new InvalidArgumentException('STARTER_PRESET must be website, portal, or business.');
}

return [
    'preset' => $preset,
    'presets' => $presets,
    'modules' => [
        'pages' => ['customize' => 'resources/js/components/pages/PageForm.vue', 'requires' => []],
        'public_site' => ['customize' => 'resources/js/pages/content', 'requires' => ['pages']],
        'media' => ['customize' => 'config/media.php', 'requires' => []],
        'activity' => ['customize' => 'config/activity.php', 'requires' => []],
        'notifications' => ['customize' => 'config/notifications.php', 'requires' => []],
    ],
    'features' => [
        'notifications' => (bool) env('STARTER_NOTIFICATIONS', $presets[$preset]['notifications']),
        'activity' => (bool) env('STARTER_ACTIVITY', $presets[$preset]['activity']),
        'media' => (bool) env('STARTER_MEDIA', $presets[$preset]['media']),
        'pages' => (bool) env('STARTER_PAGES', $presets[$preset]['pages']),
        'public_site' => (bool) env('STARTER_PUBLIC_SITE', $presets[$preset]['public_site']),
    ],
];
