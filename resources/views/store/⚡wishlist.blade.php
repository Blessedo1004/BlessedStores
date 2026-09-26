<?php

use Livewire\Component;
use App\Services\WishlistService;
use Livewire\Attributes\On;

new class extends Component
{
    public $wishlistItems;
    public int $wishlistCount = 0;
    public bool $showWishlistModal = false;
    public int $visibleWishlistItems = 10;

    public function mount(): void
    {
        $this->refreshWishlist();
    }

    #[On('wishlist-updated')]
    public function refreshWishlist(): void
    {
        $this->wishlistItems = app(WishlistService::class)->items();
        $this->wishlistCount = $this->wishlistItems->count();
        $this->visibleWishlistItems = min(max($this->visibleWishlistItems, 10), $this->wishlistCount);
    }

    public function loadMoreWishlistItems(): void
    {
        $this->visibleWishlistItems = min($this->visibleWishlistItems + 5, $this->wishlistItems->count());
    }

    public function removeItem(int $productId, ?int $variantId = null): void
    {
        app(WishlistService::class)->remove($productId, $variantId);
        $this->refreshWishlist();
    }
};
?>

<div class="header-action-item">
    <button type="button" class="header-action-btn cartmini-open-btn" wire:click="$set('showWishlistModal', true)" aria-label="Open wishlist" title="Wishlist">
        <svg width="23" height="21" viewBox="0 0 23 21" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M21.2743 2.33413C20.6448 1.60193 19.8543 1.01306 18.9596 0.609951C18.0649 0.206838 17.0883 -0.0004864 16.1002 0.00291444C14.4096 -0.0462975 12.7637 0.529279 11.5011 1.61122C10.2385 0.529279 8.59252 -0.0462975 6.90191 0.00291444C5.91383 -0.0004864 4.93727 0.206838 4.04257 0.609951C3.14788 1.01306 2.35732 1.60193 1.72785 2.33413C0.632101 3.61193 -0.514239 5.92547 0.245772 9.69587C1.4588 15.7168 10.5548 20.6578 10.9388 20.8601C11.11 20.9518 11.3028 21 11.4988 21C11.6948 21 11.8875 20.9518 12.0587 20.8601C12.445 20.6534 21.541 15.7124 22.7518 9.69587C23.5164 5.92547 22.37 3.61193 21.2743 2.33413ZM20.4993 9.27583C19.6416 13.5326 13.4074 17.492 11.5011 18.6173C8.81516 17.0587 3.28927 13.1457 2.50856 9.27583C1.91872 6.35103 2.72587 4.65208 3.50773 3.74126C3.9212 3.26166 4.43995 2.87596 5.02678 2.61185C5.6136 2.34774 6.25396 2.21175 6.90191 2.21365C7.59396 2.16375 8.28765 2.2871 8.91534 2.57168C9.54304 2.85626 10.0833 3.29235 10.4835 3.83743C10.5822 4.012 10.7278 4.15794 10.9051 4.26003C11.0824 4.36212 11.2849 4.41662 11.4916 4.41787C11.6983 4.41911 11.9015 4.36704 12.0801 4.26709C12.2587 2.87596 12.4062 2.0 12.5071 3.84959C12.9065 3.30026 13.448 2.86048 14.0781 2.57361C14.7081 2.28674 15.4051 2.16267 16.1002 2.21365C16.7495 2.21061 17.3915 2.34604 17.9798 2.6102C18.5681 2.87435 19.0881 3.26065 19.5025 3.74126C20.282 4.65208 21.0892 6.35103 20.4993 9.27583Z" fill="black" />
        </svg>
        <span class="header-action-badge bg-furniture">{{ $wishlistCount }}</span>
    </button>

    <div class="offcanvas__info cart-items-modal {{ $showWishlistModal ? 'info-open' : 'd-none' }}" wire:transition>
        <div class="offcanvas__wrapper">
            <div class="offcanvas__content">
                <div class="offcanvas__top mb-40 d-flex justify-content-between align-items-center">
                    <div class="offcanvas__logo"><h4 class="text-white mb-0">Your Wishlist</h4></div>
                    <div class="offcanvas__close"><button type="button" aria-label="Close wishlist" wire:click="$set('showWishlistModal', false)"><i class="fal fa-times"></i></button></div>
                </div>

                <div class="cart-items-list"
                    x-data="{ loadingMore: false }"
                    x-on:scroll.throttle.150ms="if (!loadingMore && $el.scrollTop + $el.clientHeight >= $el.scrollHeight - 24) { loadingMore = true; $wire.loadMoreWishlistItems().finally(() => loadingMore = false); }">
                    @forelse($wishlistItems->take($visibleWishlistItems) as $wishlistItem)
                        <div class="cart-item" wire:key="wishlist-item-{{ $wishlistItem->id }}">
                            <img class="cart-item__image" src="{{ asset('storage/' . $wishlistItem->product->productImages->first()?->image) }}" alt="{{ $wishlistItem->product->name }}">
                            <div class="cart-item__details">
                                <h5 class="cart-item__name">{{ $wishlistItem->product->name }}</h5>
                                @if($wishlistItem->variant_id)
                                    <div class="small text-light">Variant: {{ $wishlistItem->variant?->name ?? 'Selected variant' }}</div>
                                @endif
                            </div>
                            <button type="button" class="cart-item__remove" wire:click="removeItem({{ $wishlistItem->product_id }}, {{ $wishlistItem->variant_id ?? 'null' }})" aria-label="Remove {{ $wishlistItem->product->name }} from wishlist" title="Remove item" wire:loading.attr="disabled"><i class="fal fa-times"></i></button>
                        </div>
                    @empty
                        <p class="cart-empty">Your wishlist is empty.</p>
                    @endforelse

                    @if($visibleWishlistItems < $wishlistItems->count())
                        <div class="store-search-results-status text-center py-3">
                            <span wire:loading.remove wire:target="loadMoreWishlistItems">Scroll to load more</span>
                            <span wire:loading wire:target="loadMoreWishlistItems"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Loading more items...</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
