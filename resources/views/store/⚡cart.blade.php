<?php

use Livewire\Component;
use App\Models\Cart;
use Livewire\Attributes\On;

new class extends Component
{
    public $cartCount;
    public $cartItems;
    public $showCartModal = false;

    #[On('cart-updated')]
    public function refreshCart()
    {
        $this->getCartCount();
        $this->getCartItems();
    }

    public function getCartCount(){
        $this->cartCount = Cart::sum('quantity');
    }

    public function getCartItems(){
        $this->cartItems = Cart::
            with('product.productImages')
            ->latest()
            ->get();
    }

    public function increaseQuantity(Cart $cart){
        $cart->quantity++;
        $cart->save();
        $this->dispatch('cart-updated');
    }

    public function decreaseQuantity(Cart $cart){
        $cart->quantity--;
        $cart->save();
        $this->dispatch('cart-updated');
    }

    public function mount(){
        $this->getCartCount();
        $this->getCartItems();
    }
};
?>

<div class="header-action-item">
    <a class="header-action-btn cartmini-open-btn" wire:click="$set('showCartModal', true)" aria-label="Open cart">
        <svg width="21" height="23" viewBox="0 0 21 23" fill="none"
        xmlns="http://www.w3.org/2000/svg">
        <path
            d="M14.0625 10.6C14.0625 12.5883 12.4676 14.2 10.5 14.2C8.53243 14.2 6.9375 12.5883 6.9375 10.6M1 5.8H20M1 5.8V13C1 20.6402 2.33946 22 10.5 22C18.6605 22 20 20.6402 20 13V5.8M1 5.8L2.71856 2.32668C3.12087 1.5136 3.94324 1 4.84283 1H16.1571C17.0568 1 17.8791 1.5136 18.2814 2.32668L20 5.8"
            stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span class="header-action-badge bg-furniture">{{  $cartCount }}</span>
    </a>

        <div class="offcanvas__info cart-items-modal {{ $showCartModal ? 'info-open' : 'd-none' }}" wire:transition>
            <div class="offcanvas__wrapper">
                <div class="offcanvas__content">
                    <div class="offcanvas__top mb-40 d-flex justify-content-between align-items-center">
                        <div class="offcanvas__logo">
                            <h4 class="text-white mb-0">Your Cart</h4>
                        </div>
                        <div class="offcanvas__close">
                            <button type="button" wire:click="$set('showCartModal', false)" aria-label="Close cart">
                                <i class="fal fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="cart-items-list">
                        @forelse($cartItems as $cartItem)
                            <div class="cart-item" wire:key="cart-item-{{ $cartItem->id }}">
                                <img class="cart-item__image"
                                    src="{{ asset('storage/' . $cartItem->product->productImages[0]->image) }}"
                                    alt="{{ $cartItem->product->name }}">
                                <div class="cart-item__details">
                                    <h5 class="cart-item__name">{{ $cartItem->product->name }}</h5>
                                    <span class="cart-item__price">₦{{ number_format(($cartItem->product->price * $cartItem->quantity) , 2)  }}</span>
                                </div>
                                <div class="cart-item__quantity" aria-label="Quantity for {{ $cartItem->product->name }}">
                                    <div class="product-quantity-form">
                                        <button type="button" class="cart-quantity-btn cart-minus" aria-label="Decrease quantity" wire:click="decreaseQuantity({{ $cartItem->id }})" @disabled($cartItem->quantity === 1)>
                                            <i class="far fa-minus"></i>
                                        </button>
                                        <input class="cart-input" type="text" value="{{ $cartItem->quantity }}" aria-label="Quantity">
                                        <button type="button" class="cart-quantity-btn cart-plus" aria-label="Increase quantity" wire:click="increaseQuantity({{ $cartItem->id }})" @disabled($cartItem->quantity === $cartItem->product->quantity)>
                                            <i class="far fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="cart-empty">Your cart is empty.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
</div>
