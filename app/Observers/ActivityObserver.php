<?php

namespace App\Observers;

use App\Actions\Activity\RecordActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    private function record(Model $model, string $event): void
    {
        /** @var array{type: string, fields: list<string>, redacted: list<string>}|null $definition */
        $definition = config('activity.models')[$model::class] ?? null;
        if ($definition === null || ! config('starter.features.activity')) {
            return;
        }

        $changes = [];
        foreach ($definition['fields'] as $field) {
            if ($event === 'updated' && ! $model->wasChanged($field)) {
                continue;
            }

            $redacted = in_array($field, $definition['redacted'], true);
            $changes[$field] = [
                'before' => $event === 'created' ? null : ($redacted ? '[redacted]' : $model->getRawOriginal($field)),
                'after' => $event === 'deleted' ? null : ($redacted ? '[redacted]' : $model->getAttributes()[$field] ?? null),
            ];
        }

        if ($event === 'updated' && $changes === []) {
            return;
        }

        $actor = Auth::user();
        app(RecordActivity::class)->handle($definition['type'], (string) $model->getKey(), $event, $changes, $actor instanceof User ? $actor : null);
    }
}
