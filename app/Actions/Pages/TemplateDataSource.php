<?php

namespace App\Actions\Pages;

use App\Models\Page;

/** @phpstan-type TemplateItem array{title: string, url: string, image: string|null, excerpt: string|null} */
abstract class TemplateDataSource
{
    /** @return array<string, string> */
    abstract public function orders(): array;

    /** @return list<TemplateItem> */
    abstract public function items(Page $page, int $limit, string $orderBy, string $direction): array;
}
