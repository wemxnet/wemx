<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

new class extends Component
{
    public string $title = '';

    public string $description = '';

    #[Locked]
    public bool $searchable = true;

    #[Url]
    public string $search = '';

    public array $actionButton = [];

    #[Locked]
    public array $columns = [];

    #[Locked]
    public array $rows = [];

    // Pagination state
    public int $page = 1;

    public int $perPage = 10;

    #[Computed]
    public function filteredRows()
    {
        if (empty($this->search) || !$this->searchable) {
            return $this->rows;
        }

        return array_filter($this->rows, function ($row) {
            foreach ($row as $cell) {
                if (stripos(strip_tags($cell), $this->search) !== false) {
                    return true;
                }
            }
            return false;
        });
    }

    #[Computed]
    public function totalPages()
    {
        $count = count($this->filteredRows);
        if ($count === 0) {
            return 1;
        }
        return (int) ceil($count / $this->perPage);
    }

    #[Computed]
    public function paginatedRows()
    {
        $rows = array_values($this->filteredRows); // reindex after filter
        $start = ($this->page - 1) * $this->perPage;
        return array_slice($rows, $start, $this->perPage);
    }

    // Keep page valid when search changes
    public function updatedSearch()
    {
        $this->page = 1;
    }

    // Keep page valid when perPage changes
    public function updatedPerPage($value)
    {
        // ensure integer
        $this->perPage = (int) $value;
        $this->page = 1;
    }

    public function previousPage()
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage()
    {
        $this->page = min($this->totalPages, $this->page + 1);
    }

    // Optional: jump to page
    public function goToPage(int $page)
    {
        $this->page = max(1, min($page, $this->totalPages));
    }
}
?>


<div>
    <x-theme::table containerClass="shadow-md sm:rounded-t-lg" >
        <x-theme::table.caption :description="$description">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    {{ $title }}
                </div>

                @if($searchable)
                    <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                        @if(!empty($actionButton))
                            <x-theme::button.primary
                                href="{{ $actionButton['href'] }}"
                                text="{{ $actionButton['label'] }}"
                            />
                        @endif

                        <div class="flex w-full items-center sm:w-64">
                            <label for="order-search" class="sr-only">Search</label>
                            <div class="relative w-full">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                    <svg aria-hidden="true" class="w-5 h-5 text-gray-500 dark:text-gray-400" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <input
                                    type="text"
                                    wire:model.change="search"
                                    id="order-search"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block w-full pl-10 p-2 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500"
                                    placeholder="Search"
                                    required
                                >
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </x-theme::table.caption>

        <x-theme::table.head>
            @foreach($columns as $column)
                <x-theme::table.head-cell>{{ $column }}</x-theme::table.head-cell>
            @endforeach
        </x-theme::table.head>

        <x-theme::table.body>
            @if(count($this->filteredRows) === 0)
                <x-theme::table.row>
                    <x-theme::table.cell class="text-center" colspan="{{ count($columns) }}">
                        No data available
                    </x-theme::table.cell>
                </x-theme::table.row>
            @endif

            @foreach($this->paginatedRows as $row)
                <x-theme::table.row>
                    @foreach($row as $cell)
                        <x-theme::table.cell>{!! $cell !!}</x-theme::table.cell>
                    @endforeach
                </x-theme::table.row>
            @endforeach
        </x-theme::table.body>
    </x-theme::table>

    <!-- Pagination controls -->
    <div class="relative overflow-hidden bg-white rounded-b-lg shadow-md dark:bg-gray-800">
        <nav class="flex flex-col items-start justify-between p-4 space-y-3 md:flex-row md:items-center md:space-y-0"
             aria-label="Table navigation">
            <div class="flex flex-wrap items-center gap-3">
                <label for="rows" class="text-sm font-normal text-gray-500 dark:text-gray-400">
                    Rows per page
                </label>
                <select id="rows"
                        wire:model.change="perPage"
                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-primary-500 focus:border-primary-500 block py-1.5 pl-3.5 pr-6 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    <option value="5">5</option>
                    <option value="8">8</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>

                <div class="text-xs font-normal text-gray-500 dark:text-gray-400">
            <span class="font-semibold text-gray-900 dark:text-white">
              {{-- start --}}
                @php
                    $total = count($this->filteredRows);
                    $start = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
                    $end = min($page * $perPage, $total);
                @endphp
                {{ $start }}-{{ $end }}
            </span>
                    of
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $total }}</span>
                </div>
            </div>

            <ul class="inline-flex items-stretch -space-x-px">
                <li>
                    <a href="#"
                       wire:click.prevent="previousPage"
                       aria-disabled="{{ $page <= 1 ? 'true' : 'false' }}"
                       class="flex text-sm w-20 items-center justify-center h-full py-1.5 px-3 ml-0 rounded-l-lg border
                 {{ $page <= 1
                    ? 'text-gray-400 bg-gray-100 border-gray-200 cursor-not-allowed dark:bg-gray-800 dark:border-gray-700 dark:text-gray-600'
                    : 'text-gray-500 bg-white border-gray-300 hover:bg-primary-100 hover:text-primary-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white' }}">
                        Previous
                    </a>
                </li>
                <li>
                    <a href="#"
                       wire:click.prevent="nextPage"
                       aria-disabled="{{ $page >= $this->totalPages ? 'true' : 'false' }}"
                       class="flex text-sm w-20 items-center justify-center h-full py-1.5 px-3 leading-tight rounded-r-lg border
                 {{ $page >= $this->totalPages
                    ? 'text-gray-400 bg-gray-100 border-gray-200 cursor-not-allowed dark:bg-gray-800 dark:border-gray-700 dark:text-gray-600'
                    : 'text-gray-500 bg-white border-gray-300 hover:bg-primary-100 hover:text-primary-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white' }}">
                        Next
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</div>




