<?php

use App\Actions\Pages\ImportPages;
use App\Actions\Users\ImportUsers;

return [
    'resources' => [
        'pages' => ImportPages::class,
        'users' => ImportUsers::class,
    ],
];
