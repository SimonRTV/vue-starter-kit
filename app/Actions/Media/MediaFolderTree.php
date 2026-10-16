<?php

namespace App\Actions\Media;

use App\Models\MediaFolder;
use App\Models\User;
use App\Policies\MediaPolicy;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MediaFolderTree
{
    /** @var Collection<int, MediaFolder> */
    private Collection $folders;

    /** @var array<int, list<MediaFolder>> */
    private array $children = [];

    /** @param Collection<int, MediaFolder>|null $folders */
    public function __construct(?Collection $folders = null)
    {
        $this->folders = ($folders ?? MediaFolder::query()->get(['id', 'parent_id', 'name']))->keyBy('id');
        foreach ($this->folders->sortBy(fn (MediaFolder $folder): string => mb_strtolower($folder->name), SORT_NATURAL) as $folder) {
            $this->children[$folder->parent_id ?? 0][] = $folder;
        }
    }

    public function depth(int $id): int
    {
        $depth = 0;
        for ($folder = $this->folders->get($id); $folder !== null && $depth <= MediaFolder::MAX_DEPTH + 1; $folder = $this->folders->get($folder->parent_id ?? 0)) {
            $depth++;
        }

        return $depth;
    }

    /**
     * Number of levels in the folder's subtree, counting the folder itself.
     */
    public function height(int $id): int
    {
        $heights = array_map(fn (MediaFolder $child): int => $this->height($child->id), $this->children[$id] ?? []);

        return 1 + ($heights === [] ? 0 : max($heights));
    }

    /** @return list<int> */
    public function descendantIds(int $id): array
    {
        $ids = [];
        foreach ($this->children[$id] ?? [] as $child) {
            $ids = [$child->id, ...$ids, ...$this->descendantIds($child->id)];
        }

        return $ids;
    }

    /** @return list<string> */
    public function siblingNames(?int $parentId, ?int $exceptId = null): array
    {
        return array_values(array_map(
            fn (MediaFolder $folder): string => mb_strtolower($folder->name),
            array_filter($this->children[$parentId ?? 0] ?? [], fn (MediaFolder $folder): bool => $folder->id !== $exceptId),
        ));
    }

    /**
     * Flatten the tree depth-first with per-folder file counts scoped to what the user can see.
     *
     * @return list<array{id: int, name: string, parent_id: int|null, depth: int, path: string, files_count: int}>
     */
    public function flatten(User $user): array
    {
        $counts = DB::table('media')->whereNotNull('folder_id')
            ->when(! $user->can(MediaPolicy::MANAGE_ALL), fn (QueryBuilder $query) => $query->where('uploaded_by', $user->id))
            ->groupBy('folder_id')->pluck(DB::raw('count(*)'), 'folder_id');

        return $this->walk(0, 1, [], $counts->map(fn (mixed $count): int => (int) $count)->all());
    }

    /**
     * @param  list<string>  $ancestors
     * @param  array<int|string, int>  $counts
     * @return list<array{id: int, name: string, parent_id: int|null, depth: int, path: string, files_count: int}>
     */
    private function walk(int $parentId, int $depth, array $ancestors, array $counts): array
    {
        $rows = [];
        foreach ($this->children[$parentId] ?? [] as $folder) {
            $path = [...$ancestors, $folder->name];
            $rows[] = [
                'id' => $folder->id, 'name' => $folder->name, 'parent_id' => $folder->parent_id,
                'depth' => $depth, 'path' => implode(' › ', $path), 'files_count' => $counts[$folder->id] ?? 0,
            ];
            array_push($rows, ...$this->walk($folder->id, $depth + 1, $path, $counts));
        }

        return $rows;
    }
}
