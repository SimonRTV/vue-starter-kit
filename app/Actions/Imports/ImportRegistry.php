<?php

namespace App\Actions\Imports;

class ImportRegistry
{
    public function get(string $resource): ImportResource
    {
        $handler = config('imports.resources.'.$resource);
        abort_unless(is_string($handler) && is_subclass_of($handler, ImportResource::class), 404);

        return app($handler);
    }
}
