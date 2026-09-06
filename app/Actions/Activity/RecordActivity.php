<?php

namespace App\Actions\Activity;

use App\Models\Activity;
use App\Models\User;

class RecordActivity
{
    /** @param array<string, array{before: mixed, after: mixed}> $changes */
    public function handle(string $type, string $id, string $event, array $changes, ?User $actor): ?Activity
    {
        if (! config('starter.features.activity')) {
            return null;
        }

        return Activity::query()->create([
            'actor_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'subject_type' => $type,
            'subject_id' => $id,
            'event' => $event,
            'changes' => $changes,
        ]);
    }
}
