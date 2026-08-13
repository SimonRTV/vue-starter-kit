# Reusing the server-side DataTable

This starter kit includes a reusable DataTable for Laravel, Inertia, and Vue. The Page CRUD is the reference implementation.

The pattern is intentionally split into two layers:

- The shared layer renders rows and columns, controls sorting and pagination, and synchronizes table state through Inertia.
- The resource layer defines the model's columns, filters, labels, route, query parameters, authorization, and database query.

This keeps the DataTable reusable without teaching it about specific models such as `Page` or `Product`.

## Shared files

| File                                                                                                                       | Responsibility                                                                                             |
| -------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| [`resources/js/components/data-table/DataTable.vue`](resources/js/components/data-table/DataTable.vue)                     | Renders configurable TanStack columns, rows, sortable headers, the toolbar slot, and the empty-state slot. |
| [`resources/js/components/data-table/DataTablePagination.vue`](resources/js/components/data-table/DataTablePagination.vue) | Renders the result count, page-size selector, loading state, and previous/next controls.                   |
| [`resources/js/components/data-table/dataTableColumns.ts`](resources/js/components/data-table/dataTableColumns.ts)         | Provides `renderDataTableSortHeader()` for consistent sortable column headers.                             |
| [`resources/js/components/data-table/dataTableFeatures.ts`](resources/js/components/data-table/dataTableFeatures.ts)       | Defines the shared TanStack sorting and pagination features.                                               |
| [`resources/js/composables/useServerDataTable.ts`](resources/js/composables/useServerDataTable.ts)                         | Converts Laravel pagination and sorting props into TanStack state and performs partial Inertia visits.     |
| [`resources/js/types/pagination.ts`](resources/js/types/pagination.ts)                                                     | Defines the shared `Pagination<TData>` response type.                                                      |

Application-level components compose the table into a consistent resource screen:

| File                                                                                                                       | Responsibility                                                                                    |
| -------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| [`resources/js/components/application/PageHeader.vue`](resources/js/components/application/PageHeader.vue)                 | Standard page title, description, badge, metadata, and action layout.                             |
| [`resources/js/components/application/ResourceTable.vue`](resources/js/components/application/ResourceTable.vue)           | Card shell that composes `DataTable`, its toolbar, filtered empty state, and initial empty state. |
| [`resources/js/components/application/EmptyState.vue`](resources/js/components/application/EmptyState.vue)                 | Standard icon, title, description, and actions for an empty resource.                             |
| [`resources/js/components/application/ConfirmationAction.vue`](resources/js/components/application/ConfirmationAction.vue) | Pending-safe confirmation dialog for consequential resource actions.                              |
| [`resources/js/components/application/FormLayout.vue`](resources/js/components/application/FormLayout.vue)                 | Standard form Card with heading, fields, and responsive actions.                                  |

The Page implementation shows the resource-owned parts:

- [`resources/js/components/pages/pageColumns.ts`](resources/js/components/pages/pageColumns.ts)
- [`resources/js/components/pages/PageDataTable.vue`](resources/js/components/pages/PageDataTable.vue)
- [`app/Http/Requests/IndexPageRequest.php`](app/Http/Requests/IndexPageRequest.php)
- [`app/Actions/Pages/ListPages.php`](app/Actions/Pages/ListPages.php)
- [`app/Http/Controllers/PageController.php`](app/Http/Controllers/PageController.php)

## Data contract

`DataTable` expects every row to have a string or numeric `id`. The server must return a Laravel length-aware paginator with this shape:

```ts
type Pagination<TData> = {
    data: TData[];
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
};
```

The resource must also return its normalized filters. At minimum, server-driven sorting and pagination need:

```ts
type IndexFilters<TSort extends string> = {
    sort: TSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50;
};
```

Search, status, category, ownership, or other filters can be added by the resource.

## Worked example: Product listing

The following example adds the DataTable to a hypothetical `Product` model.

### 1. Define the frontend types

Create `resources/js/types/products.ts`:

```ts
import type { Pagination } from './pagination';

export type ProductStatus = 'active' | 'inactive';

export type ProductSort = 'name' | 'price' | 'is_active' | 'updated_at';

export type ProductIndexFilters = {
    search: string | null;
    status: ProductStatus | null;
    sort: ProductSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50;
};

export type ProductSummary = {
    id: number;
    name: string;
    sku: string;
    price: string;
    is_active: boolean;
    updated_at: string | null;
};

export type ProductPagination = Pagination<ProductSummary>;
```

Export the new types from `resources/js/types/index.ts`:

```ts
export * from './products';
```

Only include fields required by the table in `ProductSummary`. Large descriptions, JSON payloads, or other detail-only fields should remain on the show/edit response.

### 2. Validate and normalize the index query

Create an `IndexProductRequest` Form Request. Use the Page request as the complete reference and customize its filter and sort allowlists:

```php
<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Product::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['nullable', Rule::in([
                'name',
                'price',
                'is_active',
                'updated_at',
            ])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{search: string|null, status: 'active'|'inactive'|null, sort: 'name'|'price'|'is_active'|'updated_at', direction: 'asc'|'desc', per_page: 10|25|50}
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $search = $validated['search'] ?? null;

        return [
            'search' => is_string($search) && $search !== '' ? $search : null,
            'status' => match ($validated['status'] ?? null) {
                'active' => 'active',
                'inactive' => 'inactive',
                default => null,
            },
            'sort' => match ($validated['sort'] ?? null) {
                'name' => 'name',
                'price' => 'price',
                'is_active' => 'is_active',
                default => 'updated_at',
            },
            'direction' => ($validated['direction'] ?? null) === 'asc'
                ? 'asc'
                : 'desc',
            'per_page' => match ($validated['per_page'] ?? null) {
                25, '25' => 25,
                50, '50' => 50,
                default => 10,
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('search')) {
            $this->merge([
                'search' => Str::squish($this->string('search')->toString()),
            ]);
        }
    }
}
```

Never pass a user-provided sort value directly to `orderBy()`. The Form Request allowlist and normalized `match` expression make the server authoritative.

If you want out-of-range pages to redirect to the last valid page, also copy and customize `canonicalQuery()` from `IndexPageRequest`.

### 3. Build the server-side query

Create `app/Actions/Products/ListProducts.php`:

```php
<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ListProducts
{
    /**
     * @param  array{search: string|null, status: 'active'|'inactive'|null, sort: 'name'|'price'|'is_active'|'updated_at', direction: 'asc'|'desc', per_page: 10|25|50}  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function handle(array $filters): LengthAwarePaginator
    {
        $query = Product::query()
            ->select(['id', 'name', 'sku', 'price', 'is_active', 'updated_at']);

        if ($filters['search'] !== null) {
            $query->where(function (Builder $searchQuery) use ($filters): void {
                $search = '%'.$filters['search'].'%';

                $searchQuery
                    ->whereLike('name', $search)
                    ->orWhereLike('sku', $search);
            });
        }

        if ($filters['status'] !== null) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        return $query
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('id', $filters['direction'])
            ->paginate($filters['per_page'])
            ->withQueryString();
    }
}
```

Important query rules:

- Select only the fields used by the table.
- Eager-load any relationship used by a cell to prevent N+1 queries.
- Add database indexes for frequently filtered or sorted fields.
- Always add a deterministic secondary sort such as `id`.
- Keep filtering, sorting, and pagination on the server. Client-side sorting would only reorder the current page.

### 4. Return the paginator and filters from the controller

The index action should transform every model into the documented summary shape. Inject the listing action through the controller constructor, adding it to the existing constructor when the resource has other CRUD actions:

```php
public function __construct(
    private ListProducts $listProducts,
) {}

public function index(
    IndexProductRequest $request,
): Response
{
    $filters = $request->filters();
    $products = $this->listProducts
        ->handle($filters)
        ->through(fn (Product $product): array => [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'price' => (string) $product->price,
            'is_active' => $product->is_active,
            'updated_at' => $product->updated_at?->toISOString(),
        ]);

    return Inertia::render('products/Index', [
        'products' => $products,
        'filters' => $filters,
    ]);
}
```

The prop names must match the `only` array used by the frontend wrapper. In this example they are `products` and `filters`.

Protect the resource route with authentication middleware and a policy, just like the Page CRUD:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('products', ProductController::class);
});
```

After adding or changing routes, regenerate Wayfinder if the Vite plugin is not already doing it:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

### 5. Define the Product columns

Create `resources/js/components/products/productColumns.ts`:

```ts
import { Link } from '@inertiajs/vue3';
import { createColumnHelper } from '@tanstack/vue-table';
import { h } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import {
    renderDataTableSortHeader,
    type DataTableFeatures,
} from '@/components/data-table';
import ProductTableActions from '@/components/products/ProductTableActions.vue';
import { Badge } from '@/components/ui/badge';
import type { ProductSummary } from '@/types';

const columnHelper = createColumnHelper<DataTableFeatures, ProductSummary>();

const currencyFormatter = new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: 'USD',
});

export const productColumns = columnHelper.columns([
    columnHelper.accessor('name', {
        header: ({ column }) => renderDataTableSortHeader('Product', column),
        cell: ({ row }) =>
            h(
                Link,
                {
                    href: ProductController.show(row.original.id),
                    class: 'font-medium hover:underline',
                },
                () => row.original.name,
            ),
    }),
    columnHelper.accessor('price', {
        header: ({ column }) => renderDataTableSortHeader('Price', column),
        cell: ({ row }) => currencyFormatter.format(Number(row.original.price)),
    }),
    columnHelper.accessor('is_active', {
        header: ({ column }) => renderDataTableSortHeader('Status', column),
        cell: ({ row }) =>
            h(
                Badge,
                { variant: row.original.is_active ? 'default' : 'secondary' },
                () => (row.original.is_active ? 'Active' : 'Inactive'),
            ),
    }),
    columnHelper.accessor('updated_at', {
        header: ({ column }) =>
            renderDataTableSortHeader('Last updated', column),
        cell: ({ row }) => row.original.updated_at ?? '—',
    }),
    columnHelper.display({
        id: 'actions',
        header: () => h('span', { class: 'sr-only' }, 'Product actions'),
        cell: ({ row }) => h(ProductTableActions, { product: row.original }),
        enableSorting: false,
    }),
]);
```

Column rules:

- A sortable column's `id` or accessor key must exactly match a sort value accepted by the backend.
- Use `renderDataTableSortHeader()` for sortable headers.
- Use a display column for buttons or menus and set `enableSorting: false`.
- Cells can render text, links, badges, or resource-specific Vue components through `h()`.
- Column order is controlled by the order of this array.
- Remove a column from this array to hide it for the whole resource.

### 6. Create the resource wrapper

Create `resources/js/components/products/ProductDataTable.vue`. This component owns Product filters and connects them to the shared table:

```vue
<script setup lang="ts">
import { watchDebounced } from '@vueuse/core';
import { ref, watch } from 'vue';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import { ResourceTable } from '@/components/application';
import { productColumns } from '@/components/products/productColumns';
import { Field, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useServerDataTable } from '@/composables/useServerDataTable';
import type {
    ProductIndexFilters,
    ProductPagination,
    ProductSort,
    ProductStatus,
    ProductSummary,
} from '@/types';

type ProductIndexQuery = {
    search?: string;
    status?: ProductStatus;
    sort: ProductSort;
    direction: 'asc' | 'desc';
    per_page: number;
    page: number;
};

const props = defineProps<{
    products: ProductPagination;
    filters: ProductIndexFilters;
}>();

const search = ref(props.filters.search ?? '');
const status = ref<ProductStatus | 'all'>(props.filters.status ?? 'all');

const {
    pagination,
    processing,
    sorting,
    updatePagination,
    updateSorting,
    visit,
} = useServerDataTable<ProductSummary, ProductSort, ProductIndexQuery>({
    pagination: () => props.products,
    sorting: () => props.filters,
    query: () => ({
        search: search.value.trim() || undefined,
        status: status.value === 'all' ? undefined : status.value,
        sort: props.filters.sort,
        direction: props.filters.direction,
        per_page: props.filters.per_page,
        page: props.products.current_page,
    }),
    url: (query) => ProductController.index.url({ query }),
    resetUrl: () => ProductController.index.url(),
    only: ['products', 'filters'],
});

function updateStatus(value: unknown): void {
    if (value !== 'all' && value !== 'active' && value !== 'inactive') {
        return;
    }

    status.value = value;
    visit({
        status: value === 'all' ? undefined : value,
        page: 1,
    });
}

watchDebounced(
    search,
    (value) => {
        const normalizedSearch = value.trim();

        if (normalizedSearch === (props.filters.search ?? '')) {
            return;
        }

        visit({
            search: normalizedSearch || undefined,
            page: 1,
        });
    },
    { debounce: 300, maxWait: 1000 },
);

watch(
    () => props.filters.search,
    (value) => {
        search.value = value ?? '';
    },
);

watch(
    () => props.filters.status,
    (value) => {
        status.value = value ?? 'all';
    },
);
</script>

<template>
    <ResourceTable
        title="All products"
        description="Search, filter, sort, and manage every product."
        :columns="productColumns"
        :data="products.data"
        :pagination="pagination"
        :sorting="sorting"
        :row-count="products.total"
        :processing="processing"
        :page-size-options="[10, 25, 50]"
        item-label="product"
        items-label="products"
        empty-label="No products found"
        empty-message="No products match your filters."
        @update:pagination="updatePagination"
        @update:sorting="updateSorting"
    >
        <template #toolbar>
            <FieldGroup class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_11rem]">
                <Field>
                    <FieldLabel for="product-search" class="sr-only">
                        Search products
                    </FieldLabel>
                    <Input
                        id="product-search"
                        v-model="search"
                        placeholder="Search products…"
                        autocomplete="off"
                        maxlength="100"
                    />
                </Field>

                <Field>
                    <FieldLabel for="product-status" class="sr-only">
                        Filter by status
                    </FieldLabel>
                    <Select
                        :model-value="status"
                        @update:model-value="updateStatus"
                    >
                        <SelectTrigger id="product-status">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                <SelectItem value="active">Active</SelectItem>
                                <SelectItem value="inactive">
                                    Inactive
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                </Field>
            </FieldGroup>
        </template>
    </ResourceTable>
</template>
```

`ResourceTable` composes the lower-level `DataTable` with the standard Card layout. Behavior provided by `useServerDataTable()`:

- Pagination and sorting use the Laravel paginator and normalized filters as the source of truth.
- Inertia only reloads the props named in `only`.
- Scroll and local component state are preserved during table visits.
- Loading remains visible while one or more visits are active.
- Pagination creates browser history entries; filters and sorting replace the current entry.

Resource filters remain in the wrapper because different models need different controls and query parameters.

### 7. Render the wrapper on the index page

The Inertia page receives the server props and passes them through:

```vue
<script setup lang="ts">
import ProductDataTable from '@/components/products/ProductDataTable.vue';
import type { ProductIndexFilters, ProductPagination } from '@/types';

defineProps<{
    products: ProductPagination;
    filters: ProductIndexFilters;
}>();
</script>

<template>
    <ProductDataTable :products="products" :filters="filters" />
</template>
```

## ResourceTable customization API

### Props

| Prop                         | Purpose                                                                                  |
| ---------------------------- | ---------------------------------------------------------------------------------------- |
| `columns`                    | Resource-specific TanStack column definitions.                                           |
| `data`                       | Current page of rows. Each row needs an `id`.                                            |
| `pagination`                 | Controlled TanStack `PaginationState`. Use the value returned by `useServerDataTable()`. |
| `sorting`                    | Controlled TanStack `SortingState`. Use the value returned by `useServerDataTable()`.    |
| `row-count`                  | Total number of filtered server rows, not only the number on the current page.           |
| `processing`                 | Disables pagination and shows the updating indicator during visits.                      |
| `page-size-options`          | Allowed page sizes. Keep this synchronized with the Form Request allowlist.              |
| `item-label` / `items-label` | Singular and plural labels used in the result count.                                     |
| `empty-label`                | Footer text when the filtered result count is zero.                                      |
| `empty-message`              | Message rendered inside the empty table body.                                            |
| `show-table`                 | Set to `false` to render the initial `empty` slot instead of the table.                  |

### Events

| Event               | Connect to                                      |
| ------------------- | ----------------------------------------------- |
| `update:pagination` | `updatePagination` from `useServerDataTable()`. |
| `update:sorting`    | `updateSorting` from `useServerDataTable()`.    |

### Slots

| Slot            | Purpose                                                                |
| --------------- | ---------------------------------------------------------------------- |
| `toolbar`       | Resource-specific search, filters, bulk controls, or reset button.     |
| `filteredEmpty` | Optional replacement for the empty table-body message after filtering. |
| `empty`         | Initial empty state rendered when `show-table` is `false`.             |

## Adding common customizations

### Non-sortable column

Use a display column or disable sorting explicitly:

```ts
columnHelper.display({
    id: 'actions',
    cell: ({ row }) => h(ProductTableActions, { product: row.original }),
    enableSorting: false,
});
```

### Relationship column

Add the relationship data to the summary type, eager-load it in the listing action, and then define the accessor:

```ts
columnHelper.accessor((product) => product.category.name, {
    id: 'category',
    header: ({ column }) => renderDataTableSortHeader('Category', column),
});
```

If the relationship column is sortable, the backend must translate the `category` sort key into an explicit safe query. Do not pass arbitrary column names or join expressions from the browser to SQL.

### Custom empty state

```vue
<DataTable v-bind="tableProps">
    <template #empty>
        No products are available for the selected warehouse.
    </template>
</DataTable>
```

### Multiple DataTables on one page

Laravel paginators use `page` by default. If two server-driven tables share one page, give each paginator a unique page query parameter and extend the wrapper query types accordingly. Otherwise, changing one table's page will also affect the other table.

## Backend tests to add

Every resource listing should cover at least:

1. Guests or unauthorized users cannot access the listing.
2. The default sort and paginator metadata are returned.
3. Search and every filter are applied on the server.
4. Every allowed sort field works in both directions where relevant.
5. Unsupported status, sort, direction, page size, and page values return validation errors.
6. The response contains summary fields and excludes large or sensitive detail fields.
7. An out-of-range page is handled consistently if canonical redirects are enabled.

Use [`tests/Feature/PageManagementTest.php`](tests/Feature/PageManagementTest.php) as the reference.

## Verification

Run the focused resource test followed by the existing frontend and static checks:

```bash
php artisan test --compact tests/Feature/ProductManagementTest.php
npm run types:check
npm run lint:check
npm run format:check
composer types:check
npm run build
```

If PHP files were changed, format them before finishing:

```bash
vendor/bin/pint --dirty --format agent
```

## Reuse checklist

- [ ] The resource returns `Pagination<ResourceSummary>` and normalized filters.
- [ ] The Form Request authorizes `viewAny` and validates every query parameter.
- [ ] Sort fields and page sizes are allowlisted on the server.
- [ ] The query selects only required fields and uses a deterministic secondary sort.
- [ ] Filtered and sorted database fields have appropriate indexes.
- [ ] The TypeScript sort union matches the Laravel sort allowlist.
- [ ] The column accessor IDs match the server sort keys.
- [ ] The wrapper uses a Wayfinder URL instead of a hardcoded path.
- [ ] The composable's `only` names match the controller's Inertia prop names.
- [ ] Search and filter changes reset the page to `1`.
- [ ] Resource actions remain authorized by Laravel policies.
- [ ] Focused feature tests cover success, validation, authorization, filtering, sorting, and pagination.

## Related documentation

- [Inertia partial reloads](https://inertiajs.com/docs/v3/data-props/partial-reloads)
- [Laravel pagination](https://laravel.com/docs/13.x/pagination)
- [Laravel Form Requests](https://laravel.com/docs/13.x/validation#form-request-validation)
- [Laravel authorization policies](https://laravel.com/docs/13.x/authorization#creating-policies)
- [Laravel Wayfinder](https://github.com/laravel/wayfinder)
- [shadcn-vue Table](https://shadcn-vue.com/docs/components/table)
- [TanStack Vue Table](https://tanstack.com/table/latest/docs/framework/vue/vue-table)
