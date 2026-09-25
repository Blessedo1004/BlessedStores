<?php

use Livewire\Component;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new class extends Component
{
    #[Layout('components.page-layout')] 
    #[Title('Product Details')]

    public Product $product;
    public ?int $selectedVariantId = null;
    public int $quantity = 1;
    public bool $removeAlert = false;

    public function mount($slug){
        $this->product = Product::with('productImages', 'brand', 'categories', 'productVariants.size')
            ->where('slug', $slug)
            ->firstOrFail();

        $this->selectedVariantId = $this->product->productVariants->first()?->id;
    }

    public function selectVariant(int $variantId): void
    {
        $this->selectedVariantId = $variantId;
        $this->quantity = 1;
    }

    public function decrementQuantity(): void
    {
        $this->quantity = max(1, $this->quantity - 1);
    }

    public function incrementQuantity(): void
    {
        $variant = $this->selectedVariantId
            ? $this->product->productVariants->firstWhere('id', $this->selectedVariantId)
            : null;

        $maxQuantity = $variant ? $variant->quantity : ($this->product->quantity ?? 1);

        if ($this->quantity < $maxQuantity) {
            $this->quantity++;
        }
    }

    public function addToCart(): void
    {
        $this->removeAlert = false;
        $variantId = $this->selectedVariantId;
        $product = Product::with('productVariants.size')->findOrFail($this->product->id);

        if ($product->productVariants->isNotEmpty() && !$variantId) {
            session()->flash('error', 'Please select a variant first.');
            return;
        }

        if (app(\App\Services\CartService::class)->add($product, $this->quantity, $variantId)) {
            $this->dispatch('cart-updated');
        }
    }
};
?>
<div>
      <!-- Breadcrumb area start  -->
      <div class="breadcrumb__area theme-bg-1 p-relative z-index-11 pt-95 pb-95">
         <div class="breadcrumb__thumb" data-background="{{ asset('imgs/bg/breadcrumb-bg-furniture.jpg') }}"></div>
         <div class="container">
            <div class="row justify-content-center">
               <div class="col-xxl-12">
                  <div class="breadcrumb__wrapper text-center">
                     <h2 class="breadcrumb__title">Product Details</h2>
                     <div class="breadcrumb__menu">
                        <nav>
                           <ul>
                              <li><span><a href="{{ route('home') }}">Home</a></span></li>
                              <li><span>{{ $product->name }}</span></li>
                           </ul>
                        </nav>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <!-- Breadcrumb area start  -->

      @island
      <!-- Product details area start -->
      <div class="product__details-area section-space-medium">
         <div class="container">
            @php
                $selectedVariant = $product->productVariants->firstWhere('id', $this->selectedVariantId);
                $displayPrice = $selectedVariant ? $selectedVariant->price : $product->price;
                $availableStock = $selectedVariant ? $selectedVariant->quantity : $product->quantity;
            @endphp

            @if(session('success'))
                <div class="alert alert-success border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center col-lg-6 mx-auto d-block">
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error') && !$removeAlert)
                <div class="alert alert-danger border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center col-lg-6 mx-auto d-block" role="alert" wire:transition>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <ul class="mb-0 ps-2 list-unstyled"><li>{{ session('error') }}</li></ul>
                    <button type="button" class="btn btn-link text-danger p-0 ms-auto" aria-label="Dismiss alert" wire:click="$set('removeAlert', true)" wire:loading.attr="disabled">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6" />
                        </svg>
                    </button>
                </div>
            @endif

            <div class="row align-items-center">
               <div class="col-xxl-6 col-lg-6">
                  <div class="product__details-thumb-wrapper d-sm-flex align-items-start mr-50">
                     <div class="product__details-thumb-tab mr-20">
                        <nav>
                           <div class="nav nav-tabs flex-nowrap flex-sm-column" id="nav-tab" role="tablist">
                              @forelse($product->productImages as $image)
                                  <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="img-{{ $image->id }}-tab" data-bs-toggle="tab" data-bs-target="#img-{{ $image->id }}" type="button" role="tab" aria-controls="img-{{ $image->id }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                      <img src="{{ asset('storage/' . $image->image) }}" alt="{{ $product->name }} thumbnail {{ $loop->iteration }}">
                                  </button>
                              @empty
                                  <button class="nav-link active" type="button" aria-selected="true">
                                      <img src="{{ asset('imgs/product/details/details-04.png') }}" alt="{{ $product->name }}">
                                  </button>
                              @endforelse
                           </div>
                        </nav>
                     </div>
                     <div class="product__details-thumb-tab-content">
                        <div class="tab-content" id="productthumbcontent">
                           @forelse($product->productImages as $image)
                               <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="img-{{ $image->id }}" role="tabpanel" aria-labelledby="img-{{ $image->id }}-tab">
                                  <div class="product__details-thumb-big w-img">
                                     <img src="{{ asset('storage/' . $image->image) }}" alt="{{ $product->name }} image {{ $loop->iteration }}">
                                  </div>
                               </div>
                           @empty
                               <div class="tab-pane fade show active" id="img-default" role="tabpanel" aria-labelledby="img-default-tab">
                                  <div class="product__details-thumb-big w-img">
                                     <img src="{{ asset('imgs/product/details/details-04.png') }}" alt="{{ $product->name }}">
                                  </div>
                               </div>
                           @endforelse
                        </div>
                     </div>
                  </div>
               </div>
               <div class="col-xxl-6 col-lg-6">
                  <div class="product__details-content pr-80">
                     <div class="product__details-top d-sm-flex align-items-center mb-15">
                        <div class="product__details-tag mr-10">
                           <a href="#">{{ $product->brand?->name ?? 'Featured Product' }}</a>
                        </div>
                        <div class="product__details-rating mr-10">
                           <a href="#"><i class="fa-solid fa-star"></i></a>
                           <a href="#"><i class="fa-solid fa-star"></i></a>
                           <a href="#"><i class="fa-solid fa-star"></i></a>
                           <a href="#"><i class="fa-solid fa-star"></i></a>
                           <a href="#"><i class="fa-regular fa-star"></i></a>
                        </div>
                     </div>
                     <h3 class="product__details-title text-capitalize">{{ $product->name }}</h3>
                     <div class="product__details-price">
                        <span class="new-price">₦{{ number_format($displayPrice, 2) }}</span>
                     </div>
                     <p>{{ Str::limit($product->description, 260) }}</p>

                     @if($product->productVariants->isNotEmpty())
                         <div class="mb-3">
                             <div class="mb-2"><strong>Choose a variant:</strong></div>
                             <div class="d-flex flex-wrap gap-2 align-items-center">
                                 @foreach($product->productVariants as $variant)
                                     <button type="button" class="btn btn-lg {{ $selectedVariant && $selectedVariant->id === $variant->id ? 'btn-dark' : 'btn-outline-dark' }}" wire:click="selectVariant({{ $variant->id }})" wire:loading.attr="disabled">
                                         {{ $variant->name }}
                                     </button>
                                 @endforeach
                                 <span class="store-search-results-status" wire:loading wire:target="selectVariant">
                                     <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                     Updating variant...
                                 </span>
                             </div>
                         </div>
                     @endif

                     <div class="product__details-action mb-35">
                        <div class="product__quantity">
                           <div class="product-quantity-wrapper">
                              <button type="button" class="cart-minus" wire:click="decrementQuantity"><i class="fa-light fa-minus"></i></button>
                              <input class="cart-input" type="text" value="{{ $quantity }}" readonly aria-label="Quantity">
                              <button type="button" class="cart-plus" wire:click="incrementQuantity"><i class="fa-light fa-plus"></i></button>
                           </div>
                        </div>
                        <div class="product__add-cart">
                           <button type="button" class="fill-btn cart-btn" wire:click="addToCart" wire:loading.attr="disabled">
                              <span class="fill-btn-inner">
                                 <span class="fill-btn-normal">Add To Cart<i class="fa-solid fa-basket-shopping"></i></span>
                                 <span class="fill-btn-hover">Add To Cart<i class="fa-solid fa-basket-shopping"></i></span>
                              </span>
                           </button>
                        </div>
                        <div class="product__add-wish">
                           <a href="#" class="product__add-wish-btn"><i class="fa-solid fa-heart"></i></a>
                        </div>
                     </div>
                     <div class="product__details-meta mb-20">
                        <div class="sku">
                           <span>SKU:</span>
                           <span>{{ $selectedVariant?->sku ?? $product->sku ?? 'N/A' }}</span>
                        </div>
                        <div class="categories">
                           <span>Categories:</span>
                           @forelse($product->categories as $category)
                               <span>{{ $category->name }}{{ !$loop->last ? ',' : '' }}</span>
                           @empty
                               <span>N/A</span>
                           @endforelse
                        </div>
                        <div class="tag">
                           <span>Available:</span>
                           <span>{{ $availableStock }}</span>
                        </div>
                        @if($selectedVariant && $selectedVariant->size_id)
                            <div class="tag">
                               <span>Size:</span>
                               <span>{{ $selectedVariant->size?->name ?? 'N/A' }}</span>
                            </div>
                        @endif
                     </div>
                     <div class="product__details-share">
                        <span>Share:</span>
                        <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
                        <a href="#"><i class="fa-brands fa-twitter"></i></a>
                        <a href="#"><i class="fa-brands fa-behance"></i></a>
                        <a href="#"><i class="fa-brands fa-youtube"></i></a>
                        <a href="#"><i class="fa-brands fa-linkedin-in"></i></a>
                     </div>
                  </div>
               </div>
            </div>
            <div class="product__details-additional-info section-space-medium-top">
               <div class="row">
                  <div class="col-xxl-3 col-xl-4 col-lg-4">
                     <div class="product__details-more-tab mr-15">
                        <nav>
                           <div class="nav nav-tabs flex-column " id="productmoretab" role="tablist">
                              <button class="nav-link active" id="nav-description-tab" data-bs-toggle="tab"
                                 data-bs-target="#nav-description" type="button" role="tab"
                                 aria-controls="nav-description" aria-selected="true">Description</button>
                              <button class="nav-link" id="nav-additional-tab" data-bs-toggle="tab"
                                 data-bs-target="#nav-additional" type="button" role="tab"
                                 aria-controls="nav-additional" aria-selected="false">Additional Information </button>
                              <button class="nav-link" id="nav-review-tab" data-bs-toggle="tab"
                                 data-bs-target="#nav-review" type="button" role="tab" aria-controls="nav-review"
                                 aria-selected="false">Reviews (3)</button>
                           </div>
                        </nav>
                     </div>
                  </div>
                  <div class="col-xxl-9 col-xl-8 col-lg-8">
                     <div class="product__details-more-tab-content">
                        <div class="tab-content" id="productmorecontent">
                           <div class="tab-pane fade show active" id="nav-description" role="tabpanel"
                              aria-labelledby="nav-description-tab">
                              <div class="product__details-des">
                                 <p>{{ $product->description ?: 'No description available for this product yet.' }}</p>
                              </div>
                           </div>
                           <div class="tab-pane fade" id="nav-additional" role="tabpanel"
                              aria-labelledby="nav-additional-tab">
                              <div class="product__details-info">
                                 <ul>
                                    <li>
                                       <h4>Weight</h4>
                                       <span>{{ $selectedVariant?->weight ?? $product->weight ?? 'N/A' }}</span>
                                    </li>
                                    <li>
                                       <h4>Price</h4>
                                       <span>₦{{ number_format($displayPrice, 2) }}</span>
                                    </li>
                                    <li>
                                       <h4>Brand</h4>
                                       <span>{{ $product->brand?->name ?? 'N/A' }}</span>
                                    </li>
                                    <li>
                                       <h4>Available</h4>
                                       <span>{{ $availableStock }}</span>
                                    </li>
                                    <li>
                                       <h4>Color</h4>
                                       <span>{{ $selectedVariant?->color ?? $product->color ?? 'N/A' }}</span>
                                    </li>
                                    <li>
                                       <h4>Size</h4>
                                       <span>{{ $selectedVariant?->size?->name ?? 'N/A' }}</span>
                                    </li>
                                    <li>
                                       <h4>SKU</h4>
                                       <span>{{ $selectedVariant?->sku ?? $product->sku ?? 'N/A' }}</span>
                                    </li>
                                    <li>
                                       <h4>Category</h4>
                                       <span>{{ optional($product->categories->first())->name ?? 'N/A' }}</span>
                                    </li>
                                 </ul>
                              </div>
                           </div>
                           <div class="tab-pane fade" id="nav-review" role="tabpanel" aria-labelledby="nav-review-tab">
                              <div class="product__details-review">
                                 <h3 class="comments-title">Product reviews</h3>
                                 <div class="latest-comments mb-50">
                                    <ul>
                                       <li>
                                          <div class="comments-box d-flex">
                                             <div class="comments-avatar mr-10">
                                                <img src="{{ asset('imgs/user/user-01.png') }}" alt="">
                                             </div>
                                             <div class="comments-text">
                                                <div class="comments-top d-sm-flex align-items-start justify-content-between mb-5">
                                                   <div class="avatar-name">
                                                      <h5>Customer review</h5>
                                                      <div class="comments-date">
                                                         <span>Recent customer feedback</span>
                                                      </div>
                                                   </div>
                                                   <div class="user-rating">
                                                      <ul>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fas fa-star"></i></a></li>
                                                         <li><a href="#"><i class="fal fa-star"></i></a></li>
                                                      </ul>
                                                   </div>
                                                </div>
                                                <p>{{ $product->name }} is a quality item and a great fit for customers looking for a practical and stylish purchase.</p>
                                             </div>
                                          </div>
                                       </li>
                                    </ul>
                                 </div>
                              </div>
                           </div>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <!-- Product details area end -->
       @endisland
</div>
