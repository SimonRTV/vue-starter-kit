<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Head\Facades\Head;

class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Activity::class);
        $filters = $request->validate([
            'type' => ['nullable', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:100'],
            'subject_id' => ['nullable', 'string', 'max:255'],
            'actor' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...(! empty($request->input('from')) ? ['after_or_equal:from'] : [])],
        ]);
        $query = Activity::query();
        foreach (['type' => 'subject_type', 'event' => 'event', 'subject_id' => 'subject_id', 'actor' => 'actor_name'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<', CarbonImmutable::parse($filters['to'])->addDay()->startOfDay());
        }

        Head::title('Journal d’activité');

        return Inertia::render('activity/Index', [
            'activities' => $query->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'types' => Activity::query()->distinct()->orderBy('subject_type')->pluck('subject_type'),
            'events' => Activity::query()->distinct()->orderBy('event')->pluck('event'),
            'retentionDays' => (int) config('activity.retention_days'),
            'timezone' => config('app.timezone'),
        ]);
    }
}
