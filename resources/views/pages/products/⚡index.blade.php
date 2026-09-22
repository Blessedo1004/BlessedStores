<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\Product;
use Livewire\WithPagination;
use App\Models\Store;
use App\Models\Category;

new class extends Component
{
    use WithPagination;
     #[Title('Products')]
     public string $status = '';
     public string $searchTerm = '';
     public string $categoryTerm ='';     
     public ?int $category_id = null;
     public string $storeTerm ='';
     public ?int $store_id = null;
     public bool $showInfo = false;
     public bool $isLoadingInfo = false;
     public ?string $currentShowSlug = null;
     public ?Product $productInfo = null;
     public int $storeLoadAmount = 3;
     public int $categoryLoadAmount = 3;
     public bool $removeAlert = false;

     public function with() {
        $stores = collect();
        $storesTotal = 0;
        $categories = collect();
        $categoriesTotal = 0;

        if(filled($this->storeTerm)){
            $query = Store::select(['id','name'])->where('name', 'LIKE', '%' . trim($this->storeTerm) . '%')->where('user_id', auth()->user()->id);
            $storesTotal = $query->count();
            $stores = $query->take($this->storeLoadAmount)->orderBy('name', 'asc')->get(['id', 'name']);
        }
        else{
            $stores = collect();
            $storesTotal = 0; 
            $this->store_id = null;
        }

        if(filled($this->categoryTerm)){
            $query = Category::select(['id','name'])->where('name', 'LIKE', '%' . trim($this->categoryTerm) . '%');
            $categoriesTotal = $query->count();
            $categories = $query->take($this->categoryLoadAmount)->orderBy('name', 'asc')->get(['id', 'name']);
        }
        else{
            $categories = collect();
            $categoriesTotal = 0; 
            $this->category_id = null;
        }

        $query = Product::with('store', 'productImages')
            ->where('user_id', auth()->user()->id)
            ->when($this->status, function ($query) {
                $query->where('status', $this->status);
            })
            ->when($this->category_id, function ($query) {
                $query->whereHas('categories', function ($query){
                    $query->where('categories.id', $this->category_id);
                });
            })
            ->when($this->store_id, function ($query) {
                $query->where('store_id', $this->store_id);
            })
            ->when($this->searchTerm, function ($query) {
                $search = '%' . trim($this->searchTerm) . '%';

                $query->where(function ($query) use ($search) {
                    $query->where('slug', 'like', $search)
                        ->orWhere('name', 'like', $search);
                });
            });

        $products = (clone $query)
        ->latest()->paginate(10)->onEachSide(0);

        $count = (clone $query)->count();
        return compact('products', 'count','stores', 'storesTotal',
         'categories', 'categoriesTotal',);
     }

    public function setStore(Store $store){
        $this->store_id = $store->id;
        $this->storeTerm = $store->name;
    }

     public function setCategory(Category $category){
        $this->category_id = $category->id;
        $this->categoryTerm = $category->name;
    }

    public function loadMoreStores(){
        $this->storeLoadAmount+=3;
    }

    public function loadMoreCategories(){
        $this->categoryLoadAmount+=3;
    }

     public function showProductInfo($slug){
        // Authentication check
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

        if ($this->isLoadingInfo && $this->currentShowSlug !== $slug) {
            return;
        }

        $this->isLoadingInfo = true;
        $this->currentShowSlug = $slug;

        $product = Product::with('productImages')->where('slug' , $slug)->firstOrFail();

        if ($this->currentShowSlug !== $slug) {
            return;
        }

        // Authorization check
        if (!auth()->user()->can('store') || $product->user_id !== auth()->id()) {
            abort(403);
        }
        
        $this->productInfo = $product;
        $this->showInfo = true;
        $this->isLoadingInfo = false;
        $this->dispatch('product-gallery-updated');
    }


    public function delete($slug){
        // Authentication check
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

        // Authorization check
        else if (!auth()->user()->can('store')) {
            abort(403);
        }

        $product = Product::with('productImages' , 'categories', 'brand')->where('slug', $slug)->firstOrFail();

        // Rate limiting
        $key = 'delete-product:' . $product->id . ':' . request()->ip();
       
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError(
                'delete',
                "Too many requests. Please wait {$seconds} seconds before trying again."
            );
        return;
       }

        // Allow five requests every five minutes from each IP address.
        RateLimiter::hit($key, 60 * 5);
      
        $product->delete();
        session()->flash('success', 'Product deleted successfully!');   

    }

    public function updatedStatus()
    {
        $this->resetPage();
    }

    public function updatedSearchTerm()
    {
        $this->resetPage();
    }
}
?>

<div>
    <div class="dashboard-content">
        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center position-fixed">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{session('success')}}</span>
            </div>   
        @endif

        
         @if ($errors->any())
            @foreach ($errors->all() as $error)
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
            @endforeach
        @endif

        <div class="row d-flex align-items-center justify-content-between mb-4">
            <div class="col-12 col-lg-4 text-center text-lg-start">
                <h5 class="fw-bold text-dark mb-1">Products</h5>
                <p class="text-muted small mb-0">Manage merchant products — add, search, and view product data.</p>
            </div>

            <div class="col-12 col-lg-8">
                <div class="row d-flex justify-content-center justify-content-lg-end">
                    <div class="col-12 col-md-6 mt-4 mt-lg-0">
                        <input type="search" class="header-search-bar mx-auto d-block" placeholder="Search products by name" wire:model.live.debounce.500ms="searchTerm" inputmode="search">
                    </div>

                    <div class="col-12 col-md-6 mt-4 mt-lg-0 text-center">
                        <a class="fill-btn border-0" href="{{ route('products.create') }}"  wire:navigate>                        
                            <span class="fill-btn-inner">
                                <span class="fill-btn-normal">Add Product</span>
                                <span class="fill-btn-hover">Add Product</span>
                            </span>
                        </a>
                    </div>
                </div>

            </div>


        </div>

        <div class="custom-table-container">
            <div class="row p-4 bg-white border-bottom">
                <label class="form-label fw-semibold text-dark small mb-2">Filter products</label>
                <select class="form-select" wire:model.live="status">
                    <option value="">All products</option>
                    <option value="in-stock">In Stock</option>
                    <option value="out-of-stock">Out of Stock</option>
                </select>
                <div class="col-12 col-md-6 mt-4">
                    <label class="form-label fw-semibold text-dark small mb-2">Store</label>
                    <input type="search" class="header-search-bar mx-auto d-block" placeholder="Search store" wire:model.live.debounce.500ms="storeTerm" inputmode="search">
                    <span class="store-search-results-status" wire:loading wire:target="storeTerm"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Searching...</span>
                    <span class="store-search-results-status" wire:loading wire:target="setStore"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Setting store...</span>

                        @if(filled($storeTerm))
                            <div class="store-search-results" aria-live="polite">
                                <div class="store-search-results-header">
                                    <span>Store search results</span>
                                </div>
                                <div class="store-search-results-body">
                                        @forelse ($stores as $store)
                                            <p class="store-search-result" wire:key="store-search-result-{{ $store->id }}" wire:click="setStore({{ $store->id }})" wire:loading.attr="disabled">{{ $store->name }}</p>
                                            @empty
                                            <p class="text-muted text-center py-4">{{ "No results found for '{$storeTerm}'" }}</p>
                                        @endforelse
                                        @if($storeLoadAmount < $storesTotal)
                                            <p class="text-center load-more mt-4" wire:click="loadMoreStores" wire:loading.attr="disabled">
                                                <span wire:loading.remove wire:target="loadMoreStores">Load More</span>
                                                <span wire:loading wire:target="loadMoreStores">Loading...</span>
                                            </p>
                                        @endif
                                </div>
                            </div>
                        @endif
                </div>

                <div class="col-12 col-md-6 mt-4">
                    <label class="form-label fw-semibold text-dark small mb-2">Category</label>
                    <input type="search" class="header-search-bar mx-auto d-block" placeholder="Search category" wire:model.live.debounce.500ms="categoryTerm" inputmode="search">
                    <span class="store-search-results-status" wire:loading wire:target="categoryTerm"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Searching...</span>
                    <span class="store-search-results-status" wire:loading wire:target="setCategory"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Setting category...</span>
                    @if(filled($categoryTerm))
                        <div class="store-search-results" aria-live="polite">
                            <div class="store-search-results-header">
                                <span>Category search results</span>
                            </div>
                            <div class="store-search-results-body">
                                @forelse ($categories as $category)
                                    <p class="store-search-result" wire:key="category-search-result-{{ $category->id }}" wire:click="setCategory({{ $category->id }})" wire:loading.attr="disabled">{{ $category->name }}</p>
                                    @empty
                                    <p class="text-muted text-center py-4">{{ "No results found for '{$categoryTerm}'" }}</p>
                                @endforelse
                                @if($categoryLoadAmount < $categoriesTotal)
                                    <p class="text-center load-more mt-4" wire:click="loadMoreCategories" wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="loadMoreCategories">Load More</span>
                                        <span wire:loading wire:target="loadMoreCategories">Loading...</span>
                                    </p>
                                @endif
                            </div>


                        </div>
                    @endif
                </div>
            </div>
            <div class="p-4 bg-white border-bottom d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-dark mb-0">Products</h6>
                <span class="text-muted small">Total: {{ $count}}</span>
            </div>

            <div class="table-responsive">
                <table class="table custom-table mb-0 w-100">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Store</th>
                            <th>SKU</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Visibility</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                        <tr wire:key="product-{{ $product->id }}" wire:transition>
                            <td class="fw-semibold text-dark">{{ Str::limit($product->name, 30) }}</td>
                            <td class="fw-semibold text-dark">{{ $product->store->name }}</td>
                            <td class="fw-semibold text-dark">{{ $product->sku }}</td>
                            <td>{{ $product->quantity }}</td>
                            <td>₦{{ number_format($product->price, 2) }}</td>
                            <td>{{Str::limit($product->description, 50) }}</td>
                            <td>
                                <span class="status-badge {{ $product->status === 'in-stock' ? 'bg-success' : 'bg-danger'}}">
                                    {{ $product->status === "in-stock" ? "In Stock" : "Out of Stock"}}
                                </span>
                            </td>
                            <td>
                                <span class="status-badge {{ $product->visibility === 'draft' ? 'bg-warning' : 'bg-success'}}">
                                    {{ $product->visibility === "draft" ? "Draft" : "Published"}}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-column flex-sm-row align-items-center gap-2">
                                    <button class="btn btn-outline-info btn-sm rounded-pill" wire:click="showProductInfo(@js($product->slug))" wire:loading.attr="disabled" @disabled($isLoadingInfo)>View Info</button>
                                    <a href="{{ route('products.edit'  , $product->slug) }}" class="btn btn-outline-secondary btn-sm rounded-pill" wire:navigate>Edit</a>
                                    <button class="btn btn-outline-danger btn-sm rounded-pill" wire:click="delete(@js($product->slug))" wire:confirm="Are you sure you want to delete this product?? This is a permanent action." wire:loading.attr="disabled">Delete</button>
                                </div>
                            </td>
                            </tr>  
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted text-center py-4">{{ $searchTerm ? "No results found for '{$searchTerm}'" : "No products found." }}</td>
                            </tr>     
                        @endforelse


                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $products->links() }}
            </div>
        </div>

    </div>

    <!-- Product Info Panel -->
    <div class="store-info-overlay {{ $showInfo ? '' : 'd-none' }}" wire:loading.class.remove="d-none" wire:target="showProductInfo" wire:transition>
        <div class="store-info-panel">
            <div class="store-info-panel-header">
                <h5 class="store-info-panel-title">Product Information</h5>
                <button type="button" class="store-info-close" wire:click="$set('showInfo', false); $set('currentShowSlug', null)" wire:loading.attr="disabled">×</button>
            </div>
            <div class="store-info-panel-body">
                <div class="store-info-card">
                    @if($showInfo)
                        <div class="product-info-gallery" data-product-gallery wire:ignore.self>
                            <div class="product-info-carousel">
                                <button type="button" class="product-gallery-arrow product-gallery-arrow-prev" data-product-gallery-prev aria-label="Previous product image">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6" /></svg>
                                </button>
                                <div class="product-info-gallery-main" data-product-gallery-main>
                                    @foreach($productInfo->productImages as $productImage)
                                        <div class="product-info-slide">
                                            <img src="{{ asset('storage/' . $productImage->image) }}" alt="{{ $productInfo->name }} image {{ $loop->iteration }}">
                                        </div>
                                    @endforeach
                                </div>
                                <div class="product-info-gallery-dots" data-product-gallery-dots>
                                    @foreach($productInfo->productImages as $productImage)
                                        <button type="button" data-product-gallery-dot="{{ $loop->index }}" aria-label="Show product image {{ $loop->iteration }}"></button>
                                    @endforeach
                                </div>
                                <button type="button" class="product-gallery-arrow product-gallery-arrow-next" data-product-gallery-next aria-label="Next product image">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6" /></svg>
                                </button>
                            </div>
                        </div>

                        <div class="store-info-placeholder" wire:transition>
                            <p><strong>Name:</strong> {{ $productInfo->name }}</p>
                            <p><strong>Quantity:</strong> {{ $productInfo->quantity }}</p>
                            <p><strong>Price:</strong> ₦{{ number_format($productInfo->price, 2) }}</p>
                            <p><strong>Weight:</strong> {{ $productInfo->weight }}kg</p>
                            <p><strong>SKU:</strong> {{ $productInfo->sku }}</p>
                            <p><strong>Brand:</strong> {{ $productInfo->brand ? $productInfo->brand->name : 'N/A' }}</p>
                            <p><strong>Categories:</strong> 
                                @foreach($productInfo->categories as $category)
                                    <span class="badge bg-secondary">{{ $category->name }}</span>
                                @endforeach
                            </p>
                            <p class="mt-4"><strong>Description:</strong> {{ $productInfo->description }}</p>
                            <p class="mt-4"><strong>Status:</strong> 
                                <span class="status-badge {{ $productInfo->status === 'in-stock' ? 'bg-success' : 'bg-danger'}}">{{ $productInfo->status === "in-stock" ? "In Stock" : "Out of Stock"}}</span>
                            </p>
                            <p class="mt-4"><strong>Visibility:</strong> 
                                <span class="status-badge {{ $productInfo->visibility === 'draft' ? 'bg-warning' : 'bg-success'}}">{{ $productInfo->visibility === "draft" ? "Draft" : "Published"}}</span>
                            </p>
                        </div>
                    @else
                        <div class="store-info-loading">Loading product details...</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>
