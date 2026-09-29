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
<div class="header-search-popup-content">
    <label class="visually-hidden" for="header-product-search">Search products</label>
    <div class="header-search-popup-input">
        <input id="header-product-search" type="search" placeholder="Search for a product..." inputmode="search" wire:model.live.debounce.500ms="searchTerm">
        <span class="store-search-results-status" wire:loading wire:target="searchTerm" aria-label="Searching">
            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
        </span>
    </div>

    @if(filled($searchTerm))
        <div class="store-search-results header-search-popup-results" aria-live="polite">
            <div class="store-search-results-body"
                x-data="{ loadingMore: false }"
                x-on:scroll.throttle.150ms="
                    if (!loadingMore && $el.scrollTop + $el.clientHeight >= $el.scrollHeight - 24) {
                        loadingMore = true;
                        $wire.loadMoreProducts().finally(() => loadingMore = false);
                    }
                ">
                @forelse($products as $product)
                    <a href="{{ route('product-details', $product->slug) }}" class="store-search-result" wire:key="product-search-result-{{ $product->id }}" wire:loading.attr="disabled" wire:navigate>
                        {{ $product->name }}
                    </a>
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
