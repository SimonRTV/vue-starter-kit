<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'actor_name', 'subject_type', 'subject_id', 'event', 'changes'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        $days = (int) config('activity.retention_days', 0);

        return static::query()->when(
            config('starter.features.activity') && $days > 0,
            fn (Builder $query): Builder => $query->where('created_at', '<', now()->subDays($days)),
            fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
        );
    }
}
