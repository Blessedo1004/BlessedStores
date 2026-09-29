<?php

use Livewire\Component;
use App\Models\Product;

new class extends Component
{
    public string $searchTerm = '';
    public int $loadAmount = 5;
    public int $totalProducts = 0;

    public function with(){
        if(filled($this->searchTerm)){
            $baseQuery = Product::where('name' , 'LIKE' , '%' . trim($this->searchTerm) . '%')
                ->orWhere('slug' , 'LIKE' , '%' . trim($this->searchTerm) . '%')
                ->inStock()
                ->visible();

            $this->totalProducts = $baseQuery->count();

            $products = (clone $baseQuery)
                ->take($this->loadAmount)
                ->latest()
                ->get(['id', 'name', 'slug']);

            return compact('products');
        }

        $this->totalProducts = 0;
        return ['products' => collect()];
    }

    public function loadMoreProducts(){
        $this->loadAmount += 3;
    }
};
?>
<div class="header-search  d-xxl-block">
        <input type="search" placeholder="Search for a product..." inputmode="search" wire:model.live.debounce.500ms="searchTerm">
        <button type="button">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none"
            xmlns="http://www.w3.org/2000/svg">
            <path d="M13.4443 13.4445L16.9999 17" stroke="white" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" />
            <path
                d="M15.2222 8.11111C15.2222 12.0385 12.0385 15.2222 8.11111 15.2222C4.18375 15.2222 1 12.0385 1 8.11111C1 4.18375 4.18375 1 8.11111 1C12.0385 1 15.2222 4.18375 15.2222 8.11111Z"
                stroke="white" stroke-width="2" />
            </svg>
        </button>
        <span class="store-search-results-status" wire:loading wire:target="searchTerm"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span></span>
        @if(filled($searchTerm))
            <div class="store-search-results position-fixed" aria-live="polite">
                <div class="store-search-results-body"
                    x-data="{ loadingMore: false }"
                    x-on:scroll.throttle.150ms="
                        if (!loadingMore && $el.scrollTop + $el.clientHeight >= $el.scrollHeight - 24) {
                            loadingMore = true;
                            $wire.loadMoreProducts().finally(() => loadingMore = false);
                        }
                    ">
                        @forelse ($products as $product)
                            <a href="{{ route('product-details' , $product->slug) }}" class="store-search-result" wire:key="product-search-result-{{ $product->id }}" wire:loading.attr="disabled" wire:navigate>{{ $product->name }}</a>
                            @empty
                            <p class="text-muted text-center py-4 px-4">{{ "No results found for '{$searchTerm}'" }}</p>
                        @endforelse
                        @if($totalProducts > $loadAmount)
                            <div class="store-search-results-status text-center py-3">
                                <span wire:loading.remove wire:target="loadMoreProducts">Scroll to load more</span>
                                <span wire:loading wire:target="loadMoreProducts">
                                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                    Loading more products...
                                </span>
                            </div>
                        @endif
                </div>
            </div>
        @endif
</div>
