<?php

use Livewire\Component;
use App\Services\CartService;
use Livewire\Attributes\On;

new class extends Component
{
    public $cartCount;
    public $cartItems;
    public $showCartModal = false;
    public int $visibleCartItems = 10;

    #[On('cart-updated')]
    public function refreshCart()
    {
        $this->getCartCount();
        $this->getCartItems();
    }

    public function getCartCount(){
        $this->cartCount = app(CartService::class)->count();
    }

    public function getCartItems(){
        $this->cartItems = app(CartService::class)->items();
        $this->visibleCartItems = min(max($this->visibleCartItems, 10), $this->cartItems->count());
    }

    public function loadMoreCartItems(): void
    {
        if ($this->visibleCartItems < $this->cartItems->count()) {
            $this->visibleCartItems = min($this->visibleCartItems + 5, $this->cartItems->count());
        }
    }

    public function increaseQuantity(int $productId, ?int $variantId = null){
        $item = $this->cartItems->first(function ($cartItem) use ($productId, $variantId) {
            return (int) $cartItem->product_id === $productId && (int) ($cartItem->variant_id ?? 0) === (int) ($variantId ?? 0);
        });

        $newQuantity = ($item->quantity ?? 1) + 1;
        app(CartService::class)->updateQuantity($productId, $newQuantity, $variantId);
        $this->dispatch('cart-updated');
    }

    public function decreaseQuantity(int $productId, ?int $variantId = null){
        $item = $this->cartItems->first(function ($cartItem) use ($productId, $variantId) {
            return (int) $cartItem->product_id === $productId && (int) ($cartItem->variant_id ?? 0) === (int) ($variantId ?? 0);
        });

        if (!$item || $item->quantity <= 1) {
            return;
        }

        app(CartService::class)->updateQuantity($productId, $item->quantity - 1, $variantId);
        $this->dispatch('cart-updated');
    }

    public function removeItem(int $productId, ?int $variantId = null){
        app(CartService::class)->remove($productId, $variantId);
        $this->dispatch('cart-updated');
    }

    public function mount(){
        $this->getCartCount();
        $this->getCartItems();
    }
};
?>

<div class="header-action-item">
    <a class="header-action-btn cartmini-open-btn" wire:click="$set('showCartModal', true)" aria-label="Open cart" title="Cart">
        <svg width="21" height="23" viewBox="0 0 21 23" fill="none"
        xmlns="http://www.w3.org/2000/svg">
        <path
            d="M14.0625 10.6C14.0625 12.5883 12.4676 14.2 10.5 14.2C8.53243 14.2 6.9375 12.5883 6.9375 10.6M1 5.8H20M1 5.8V13C1 20.6402 2.33946 22 10.5 22C18.6605 22 20 20.6402 20 13V5.8M1 5.8L2.71856 2.32668C3.12087 1.5136 3.94324 1 4.84283 1H16.1571C17.0568 1 17.8791 1.5136 18.2814 2.32668L20 5.8"
            stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span class="header-action-badge bg-furniture">{{ $cartCount }}</span>
    </a>

        <div class="offcanvas__info cart-items-modal {{ $showCartModal ? 'info-open' : 'd-none' }}" wire:transition>
            <div class="offcanvas__wrapper">
                <div class="offcanvas__content">
                    <div class="offcanvas__top mb-40 d-flex justify-content-between align-items-center">
                        <div class="offcanvas__logo">
                            <h4 class="text-white mb-0">Your Cart</h4>
                        </div>
                        <div class="offcanvas__close">
                            <button type="button" aria-label="Close cart" wire:click="$set('showCartModal', false)">
                                <i class="fal fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="cart-items-list"
                        x-data="{ loadingMore: false }"
                        x-on:scroll.throttle.150ms="
                            if (!loadingMore && $el.scrollTop + $el.clientHeight >= $el.scrollHeight - 24) {
                                loadingMore = true;
                                $wire.loadMoreCartItems().finally(() => loadingMore = false);
                            }
                        ">
                        @forelse($cartItems->take($visibleCartItems) as $cartItem)
                            <div class="cart-item" wire:key="cart-item-{{ $cartItem->id }}">
                                <img class="cart-item__image"
                                    src="{{ asset('storage/' . $cartItem->product->productImages[0]->image) }}"
                                    alt="{{ $cartItem->product->name }}">
                                <div class="cart-item__details">
                                    <h5 class="cart-item__name">{{ $cartItem->product->name }}</h5>
                                    @if($cartItem->variant_id)
                                        <div class="small text-light">
                                            Variant: {{ $cartItem->variant?->name ?? 'Selected variant' }}
                                        </div>
                                    @endif
                                    <span class="cart-item__price">₦{{ number_format(($cartItem->price * $cartItem->quantity), 2) }}</span>
                                </div>
                                <div class="cart-item__quantity" aria-label="Quantity for {{ $cartItem->product->name }}">
                                    <div class="product-quantity-form">
                                        <button type="button" class="cart-quantity-btn cart-minus" aria-label="Decrease quantity" wire:click="decreaseQuantity({{ $cartItem->product_id }}, {{ $cartItem->variant_id ?? 'null' }})" @disabled($cartItem->quantity === 1) wire:loading.attr="disabled">
                                            <i class="far fa-minus"></i>
                                        </button>
                                        <input class="cart-input" type="text" value="{{ $cartItem->quantity }}" aria-label="Quantity">
                                        <button type="button" class="cart-quantity-btn cart-plus" aria-label="Increase quantity" wire:click="increaseQuantity({{ $cartItem->product_id }}, {{ $cartItem->variant_id ?? 'null' }})" @disabled($cartItem->quantity >= $cartItem->product->quantity) wire:loading.attr="disabled">
                                            <i class="far fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="button" class="cart-item__remove" wire:click="removeItem({{ $cartItem->product_id }}, {{ $cartItem->variant_id ?? 'null' }})" aria-label="Remove {{ $cartItem->product->name }} from cart" title="Remove item" wire:loading.attr="disabled">
                                    <i class="fal fa-times"></i>
                                </button>
                            </div>
                        @empty
                            <p class="cart-empty">Your cart is empty.</p>
                        @endforelse

                        @if($visibleCartItems < $cartItems->count())
                            <div class="store-search-results-status text-center py-3">
                                <span wire:loading.remove wire:target="loadMoreCartItems">Scroll to load more</span>
                                <span wire:loading wire:target="loadMoreCartItems">
                                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                    Loading more items...
                                </span>
                            </div>
                        @endif
                    </div>

                    <div class="cart-total">
                        <span class="cart-total__label">Total</span>
                        <strong class="cart-total__amount">₦{{ number_format($cartItems->sum(fn ($cartItem) => $cartItem->price * $cartItem->quantity), 2) }}</strong>
                    </div>
                </div>
            </div>
        </div>
</div>
