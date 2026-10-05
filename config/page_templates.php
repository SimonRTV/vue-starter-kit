<?php

use App\Actions\Pages\PublishedPageSource;

return [
    /* Register custom Vue pages here; components live under resources/js/pages/content. */
    'renderers' => [
        'feature' => ['label' => 'Page spéciale', 'component' => 'content/Feature'],
        'collection' => ['label' => 'Collection de contenus', 'component' => 'content/Collection'],
    ],

    /* Sources extend TemplateDataSource and own their public scope, ordering, and serialization. */
    'sources' => [
        'pages' => ['label' => 'Pages publiées', 'resolver' => PublishedPageSource::class],
    ],
];
