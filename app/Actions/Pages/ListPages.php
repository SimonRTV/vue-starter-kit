<?php

namespace App\Actions\Pages;

use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListPages
{
    /**
     * List pages using validated server-side filters.
     *
     * @param  array{search: string|null, status: 'draft'|'published'|null, sort: 'title'|'is_published'|'published_at'|'updated_at', direction: 'asc'|'desc', per_page: 10|25|50}  $filters
     * @return LengthAwarePaginator<int, Page>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $query = Page::query()
            ->select(['id', 'title', 'slug', 'is_published', 'published_at', 'updated_at']);

        if ($filters['search'] !== null) {
            $query->where(function (Builder $searchQuery) use ($filters): void {
                $search = '%'.$filters['search'].'%';

                $searchQuery
                    ->whereLike('title', $search)
                    ->orWhereLike('slug', $search);
            });
        }

        if ($filters['status'] !== null) {
            $query->where('is_published', $filters['status'] === 'published');
        }

        return $query
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('id', $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();
    }
}
