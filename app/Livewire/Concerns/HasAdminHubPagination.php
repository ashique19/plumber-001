<?php

namespace App\Livewire\Concerns;

use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

trait HasAdminHubPagination
{
    use WithPagination;

    #[Url(as: 'per_page', except: 20)]
    public int $perPage = 20;

    /**
     * @return list<int>
     */
    public function adminPerPageOptions(): array
    {
        return [10, 20, 50, 100];
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, $this->adminPerPageOptions(), true)) {
            $this->perPage = 20;
        }

        $this->resetPage();
    }

    protected function adminPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * @template T
     *
     * @param  iterable<int, T>  $items
     * @return LengthAwarePaginator<int, T>
     */
    protected function paginateArray(iterable $items, string $pageName = 'page'): LengthAwarePaginator
    {
        $collection = collect($items);
        $page = max(1, (int) $this->getPage($pageName));
        $perPage = max(1, $this->adminPerPage());
        $total = $collection->count();
        $slice = $collection->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
            ]
        );
    }
}