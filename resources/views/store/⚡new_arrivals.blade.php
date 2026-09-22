<?php

use Livewire\Component;
use App\Models\Product;
use App\Services\CartService;

new class extends Component
{
    public  $newArrivals;
    public $quickViewProduct;
    public bool $removeAlert = false;

    public function mount(){
        $query = Product::
            with('productImages', 'brand', 'categories')
            ->inRandomOrder()
            ->visible()
            ->inStock();

        if(auth()->check() && auth()->user()->categories->isNotEmpty()){
            $categories = auth()->user()->categories()->with('subCategories')->get();
            $categoryIds = [];
            foreach($categories as $category){
                array_push($categoryIds , $category->id);
                if ($category->subCategories->isNotEmpty()){
                    $subCategoryIds = $category->subCategories->pluck('id')->toArray();
                    $categoryIds = array_merge($categoryIds , $subCategoryIds);
                }
            }

            $this->newArrivals = (clone $query)
            ->whereHas('categories', function ($query) use ($categoryIds) {
                $query->whereIn('category_id', $categoryIds);
            })
            ->where('created_at', '>=', now()->startOfWeek())->take(5)->get();
        }

        else{
            $this->newArrivals = (clone $query)
            ->where('created_at', '>=', now()->startOfWeek())
            ->take(5)
            ->get();
        }
    }

    public function addToCart($slug){
        $product = Product::with('productImages')->where('slug' , $slug)->firstOrFail();
        if (app(CartService::class)->add($product)) {
            $this->dispatch('cart-updated');
        }
    }

    public function showQuickView($slug){
        $this->quickViewProduct = Product::with('productImages', 'brand', 'categories')
            ->where('slug', $slug)
            ->firstOrFail();
    }
};
?>

    


<div class="container">
    @if($newArrivals->isNotEmpty())
    <div class="section-title-wrapper-4 mb-40 text-center">
        <span class="section-subtitle-4 mb-10">This week</span>
        <h2 class="section-title-4">New Arrivals</h2>
    </div>
    <div class="discount-main p-relative">
        <div class="discount-slider-navigation furniture__navigation">
            <button type="button" class="discount-slider-button-prev"><i class="fa-regular fa-angle-left"></i>
            </button>
            <button type="button" class="discount-slider-button-next"><i
                class="fa-regular fa-angle-right"></i></button>
        </div>
        <div class="row align-items-center">
            <div class="col-xxl-12">
                <div class="swiper new-arrivals-active" wire:ignore>
                    <div class="swiper-wrapper">
                            @foreach($newArrivals as $newArrival)
                            <div class="swiper-slide" wire:key="new-arrival-{{ $newArrival->id }}">
                                <div class="product-item furniture__product">
                                    <!-- <div class="product-badge">
                                    <span class="product-trending">10% off</span>
                                    </div> -->
                                    <div class="product-thumb theme-bg-2">
                                    <img src="{{ asset('storage/' . $newArrival->productImages[0]->image) }}"
                                            alt="{{ $newArrival->name }}" loading="lazy">
                                    <div class="product-action-item">
                                        <button type="button" class="product-action-btn"  wire:click="addToCart(@js($newArrival->slug))" wire:loading.attr="disabled">
                                            <svg width="20" height="22" viewBox="0 0 20 22" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                d="M13.0768 10.1416C13.0768 11.9228 11.648 13.3666 9.88542 13.3666C8.1228 13.3666 6.69401 11.9228 6.69401 10.1416M1.375 5.84163H18.3958M1.375 5.84163V12.2916C1.375 19.1359 2.57494 20.3541 9.88542 20.3541C17.1959 20.3541 18.3958 19.1359 18.3958 12.2916V5.84163M1.375 5.84163L2.91454 2.73011C3.27495 2.00173 4.01165 1.54163 4.81754 1.54163H14.9533C15.7592 1.54163 16.4959 2.00173 16.8563 2.73011L18.3958 5.84163"
                                                stroke="white" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round" />
                                            </svg>
                                            <span class="product-tooltip">
                                                <span wire:loading.remove wire:target="addToCart">Add to Cart</span>
                                                <span wire:loading wire:target="addToCart">Adding...</span>
                                            </span>
                                        </button>
                                        <button type="button" class="product-action-btn" wire:click="showQuickView(@js($newArrival->slug))" data-bs-toggle="modal"
                                            data-bs-target="#newArrivalsQuickViewModal" wire:loading.attr="disabled">

                                            <svg width="26" height="18" viewBox="0 0 26 18" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                d="M13.092 4.55026C10.5878 4.55026 8.55683 6.58125 8.55683 9.08541C8.55683 11.5896 10.5878 13.6206 13.092 13.6206C15.5961 13.6206 17.6271 11.5903 17.6271 9.08541C17.6271 6.5805 15.5969 4.55026 13.092 4.55026ZM13.092 12.1089C11.4246 12.1089 10.0338 10.7196 10.0338 9.05216C10.0338 7.38473 11.3898 6.02872 13.0572 6.02872C14.7246 6.02872 16.0807 7.38473 16.0807 9.05216C16.0807 10.7196 14.7594 12.1089 13.092 12.1089ZM25.0965 8.8768C25.0875 8.839 25.092 8.79819 25.0807 8.76115C25.0761 8.74528 25.0655 8.73621 25.0603 8.7226C25.0519 8.70144 25.0542 8.67574 25.0429 8.65533C22.8441 3.62131 18.1064 0.724854 13.0572 0.724854C8.00807 0.724854 3.17511 3.61677 0.975559 8.65079C0.966488 8.67196 0.968 8.69388 0.959686 8.71806C0.954395 8.73318 0.943812 8.74074 0.938521 8.7551C0.927184 8.7929 0.931719 8.83296 0.92416 8.8715C0.910555 8.93953 0.897705 9.00605 0.897705 9.07483C0.897705 9.14361 0.910555 9.20862 0.92416 9.2774C0.931719 9.31519 0.926428 9.35677 0.938521 9.39229C0.943057 9.40968 0.954395 9.41648 0.959686 9.4316C0.967244 9.45201 0.965732 9.4777 0.975559 9.49887C3.17511 14.5314 7.96121 17.381 13.0104 17.381C18.0595 17.381 22.8448 14.5374 25.0436 9.5034C25.055 9.48148 25.0527 9.45956 25.061 9.43538C25.0663 9.42253 25.0761 9.4127 25.0807 9.39758C25.092 9.36055 25.089 9.32049 25.0965 9.28118C25.1101 9.21315 25.1222 9.14739 25.1222 9.0771C25.1222 9.01058 25.1094 8.94482 25.0958 8.87604L25.0965 8.8768ZM13.0104 15.8692C8.72841 15.8692 4.51298 13.6123 2.44193 9.07407C4.49333 4.55177 8.76469 2.23582 13.0572 2.23582C17.349 2.23582 21.5251 4.55404 23.5773 9.07861C21.5266 13.6002 17.3036 15.8692 13.0104 15.8692Z"
                                                fill="white" />
                                            </svg>
                                            <span class="product-tooltip">Quick View</span>
                                        </button>
                                        <button type="button" class="product-action-btn">

                                            <svg width="21" height="20" viewBox="0 0 21 20" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                <path
                                                d="M19.2041 2.63262C18.6402 1.97669 17.932 1.44916 17.1305 1.08804C16.329 0.726918 15.4541 0.54119 14.569 0.544237C13.0545 0.500151 11.58 1.01577 10.4489 1.98501C9.31782 1.01577 7.84334 0.500151 6.32883 0.544237C5.44368 0.54119 4.56885 0.726918 3.76735 1.08804C2.96585 1.44916 2.25764 1.97669 1.69374 2.63262C0.712132 3.77732 -0.314799 5.84986 0.366045 9.22751C1.45272 14.6213 9.60121 19.0476 9.94523 19.2288C10.0986 19.311 10.2713 19.3541 10.4469 19.3541C10.6224 19.3541 10.7951 19.311 10.9485 19.2288C11.2946 19.0436 19.4431 14.6173 20.5277 9.22751C21.2126 5.84986 20.1857 3.77732 19.2041 2.63262ZM18.5099 8.85122C17.7415 12.6646 12.1567 16.2116 10.4489 17.2196C8.04279 15.8234 3.09251 12.318 2.39312 8.85122C1.86472 6.23109 2.5878 4.70912 3.28821 3.89317C3.65861 3.46353 4.12333 3.11801 4.64903 2.88141C5.17473 2.64481 5.74838 2.52299 6.32883 2.52468C6.94879 2.47998 7.57022 2.59049 8.13253 2.84542C8.69484 3.10036 9.17884 3.49102 9.53734 3.97932C9.62575 4.13571 9.75616 4.26645 9.915 4.3579C10.0738 4.44936 10.2553 4.49819 10.4404 4.4993C10.6256 4.50041 10.8076 4.45377 10.9676 4.36423C11.1276 4.27469 11.2598 4.14553 11.3502 3.99022C11.708 3.49811 12.193 3.10414 12.7575 2.84715C13.3219 2.59016 13.9463 2.47902 14.569 2.52468C15.1507 2.52196 15.7257 2.64329 16.2527 2.87993C16.7798 3.11656 17.2456 3.46262 17.6168 3.89317C18.3152 4.70912 19.0383 6.23109 18.5099 8.85122Z"
                                                fill="white" />
                                            </svg>
                                            <span class="product-tooltip">Add To Wishlist</span>
                                        </button>
                                    </div>
                                    </div>
                                    <div class="product-content text-center">
                                    <h4 class="product-title"><a href="product-details.html">{{ Str::limit($newArrival->name, 30) }}</a></h4>
                                    <div class="user-rating">
                                        <i class="fal fa-star"></i>
                                        <i class="fal fa-star"></i>
                                        <i class="fal fa-star"></i>
                                        <i class="fal fa-star"></i>
                                        <i class="fal fa-star"></i>
                                    </div>
                                    <div class="product-price">
                                        <span class="product-new-price">₦{{ number_format($newArrival->price, 2) }}</span>
                                    </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                    </div>
                </div>
                @if(session('success'))
                    <div class="alert alert-success border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center col-lg-6 mx-auto d-block">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0 mt-0.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{session('success')}}</span>
                    </div>
                    
                @endif

                @if(session('error'))
                 <div class="alert alert-danger border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center {{ $removeAlert ? 'd-none' : '' }}" role="alert" wire:transition>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <ul class="mb-0 ps-2 list-unstyled">
                            <li>{{ $error }}</li>
                    </ul>
                    <button type="button" class="btn btn-link text-danger p-0 ms-auto" aria-label="Dismiss alert" wire:click="$set('removeAlert', true)" wire:loading.attr="disabled">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6" />
                        </svg>
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif
    <div class="product-modal-sm modal fade" id="newArrivalsQuickViewModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="product-modal">
                    <div class="product-modal-wrapper p-relative">
                        <button type="button" class="close product-modal-close" data-bs-dismiss="modal" aria-label="Close">
                            <i class="fal fa-times"></i>
                        </button>

                        <div class="modal__inner">
                            @if($quickViewProduct)
                                <div class="bd__shop-details-inner">
                                    <div class="row align-items-center">
                                        <div class="col-lg-6">
                                            <div class="product__details-thumb-wrapper d-sm-flex align-items-start">
                                                <div class="product__details-thumb-tab mr-20">
                                                    <nav>
                                                        <div class="nav nav-tabs flex-nowrap flex-sm-column" id="quick-view-images-tab" role="tablist">
                                                            @foreach($quickViewProduct->productImages as $productImage)
                                                                <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="quick-view-image-{{ $productImage->id }}-tab" data-bs-toggle="tab" data-bs-target="#quick-view-image-{{ $productImage->id }}" type="button" role="tab" aria-controls="quick-view-image-{{ $productImage->id }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                                                    <img src="{{ asset('storage/' . $productImage->image) }}" alt="{{ $quickViewProduct->name }} image {{ $loop->iteration }}">
                                                                </button>
                                                            @endforeach
                                                        </div>
                                                    </nav>
                                                </div>
                                                <div class="product__details-thumb-tab-content">
                                                    <div class="tab-content" id="quick-view-images-content">
                                                        @foreach($quickViewProduct->productImages as $productImage)
                                                            <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="quick-view-image-{{ $productImage->id }}" role="tabpanel" aria-labelledby="quick-view-image-{{ $productImage->id }}-tab">
                                                                <div class="product__details-thumb-big w-img">
                                                                    <img src="{{ asset('storage/' . $productImage->image) }}" alt="{{ $quickViewProduct->name }} image {{ $loop->iteration }}">
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="product__details-content">
                                                <div class="product__details-top d-flex flex-wrap gap-3 align-items-center mb-15">
                                                    <div class="product__details-tag">
                                                        <span>{{ $quickViewProduct->brand?->name ?? 'New Arrival' }}</span>
                                                    </div>
                                                    <div class="product__details-rating">
                                                        <i class="fa-solid fa-star"></i>
                                                        <i class="fa-solid fa-star"></i>
                                                        <i class="fa-solid fa-star"></i>
                                                        <i class="fa-solid fa-star"></i>
                                                        <i class="fa-regular fa-star"></i>
                                                    </div>
                                                </div>
                                                <h3 class="product__details-title">{{ $quickViewProduct->name }}</h3>
                                                <div class="product__details-price">
                                                    <span class="new-price">₦{{ number_format($quickViewProduct->price, 2) }}</span>
                                                </div>
                                                <p>{{ $quickViewProduct->description }}</p>
                                                <div class="product__details-action mb-35">
                                                    <div class="product__quantity">
                                                        <div class="product-quantity-wrapper">
                                                            <form action="#" onsubmit="return false;">
                                                                <button type="button" class="cart-minus" aria-label="Decrease quantity">
                                                                    <i class="fa-light fa-minus"></i>
                                                                </button>
                                                                <input class="cart-input" type="text" value="1" aria-label="Quantity">
                                                                <button type="button" class="cart-plus" aria-label="Increase quantity">
                                                                    <i class="fa-light fa-plus"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    <div class="product__add-cart">
                                                        <button type="button" class="fill-btn cart-btn" wire:click="addToCart('{{ $quickViewProduct->slug }}')" data-bs-dismiss="modal" wire:loading.attr="disabled">
                                                            <span class="fill-btn-inner">
                                                                <span class="fill-btn-normal">Add To Cart<i class="fa-solid fa-basket-shopping"></i></span>
                                                                <span class="fill-btn-hover">Add To Cart<i class="fa-solid fa-basket-shopping"></i></span>
                                                            </span>
                                                        </button>
                                                    </div>
                                                    <div class="product__add-wish">
                                                        <button type="button" class="product__add-wish-btn" aria-label="Add to wishlist" title="Add to wishlist">
                                                            <i class="fa-solid fa-heart"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="product__details-meta">
                                                    <div class="sku"><span>SKU:</span> {{ $quickViewProduct->sku ?? 'N/A' }}</div>
                                                    <div class="categories">
                                                        <span>Categories:</span>
                                                        @forelse($quickViewProduct->categories as $category)
                                                            <span>{{ $category->name }}{{ !$loop->last ? ',' : '' }}</span>
                                                        @empty
                                                            <span>N/A</span>
                                                        @endforelse
                                                    </div>
                                                    <div><span>Available:</span> {{ $quickViewProduct->quantity }}</div>
                                                    <div><span>Weight:</span> {{ $quickViewProduct->weight ?? 'N/A' }}{{ $quickViewProduct->weight ? 'kg' : '' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="store-info-loading">Loading product details...</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>




