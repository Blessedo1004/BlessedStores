<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use App\Models\Product;
use App\Models\Store;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use WithFileUploads;
    #[Title('Edit Product')]

    public Product $product;
    public string $name = '';
    public int $quantity;
    public string $price = '';
    public string $description ;
    public array $product_images = [];
    public $image;
    public string $searchTerm ='';
    public ?int $store_id;
    

    public function mount($slug){
        $product = Product::with('store', 'productImages')->where('slug', $slug)->firstOrFail();
        $this->product = $product;
        $this->quantity = $product->quantity;
        $this->price = $product->price;
        $this->description = $product->description;
        $this->searchTerm = $product->store->name;
        $this->store_id = $product->store_id;
        $product_images = $product->productImages;
        foreach ($product_images as $image) {
            $this->product_images[] = $image;
        }
    }

    public function with(){
        if($this->searchTerm){
            $stores = Store::select(['id','name'])->where('name', 'LIKE', '%' . trim($this->searchTerm) . '%')->where('user_id', auth()->user()->id)->get();
            return compact('stores');
        }
    }

    protected $messages = [
        'store_id.required' => 'Please select a store from the search results.',
    ];

    public function updated($property)
    {
        $this->validateOnly($property, $this->rules());
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'nullable',
                'string',
                'min:3',
                'max:50',
                'unique:products,name',
                'regex:/^[^<>]*$/',
            ],

            'quantity' => [
                'required',
                'integer',
                'regex:/^[^<>]*$/',
            ],
            'price' => [
                'required',
                'min:0',
                'decimal:0,2',
                'regex:/^[^<>]*$/',
            ],
            'description' => [
                'required',
                'string',
                'min:10',
                'max:200',
                'regex:/^[^<>]*$/',
            ],
            'product_images' => [
                'required',
                'array',
                'min:1',
                'max:5'
            ],
            'store_id' => [
                'required',
                'integer'
            ],

    ];
    }

    public function addProductImage()
    {
        if (count($this->product_images) >= 5) {
            $this->addError('product_images', 'You may only add up to 5 product images.');
            return;
        }

        // Validate the image field
        $this->validate([
            'image' => 'required|image|max:2048',
        ]);

        // Add the image to the product_images array
        $this->product_images[] = $this->image;

        $this->image = '';
    }

    public function removeProductImage($index)
    {
        // Remove the image entry at the specified index
        unset($this->product_images[$index]);
        // Re-index the array to maintain proper indices
        $this->product_images = array_values($this->product_images);
    }

    public function setStore(Store $store){
        $this->store_id = $store->id;
        $this->searchTerm = $store->name;
    }

    public function save(){
        // Authentication check
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

        // Authorization check
        else if (!auth()->user()->can('store') || $this->product->user_id !== auth()->user()->id ) {
            abort(403);
        }

        // Rate limiting 
        $user = auth()->user();
        $key = 'update-product:' . $user->id . ':' . request()->ip();
       
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError(
                'update',
                "Too many attempts. Please wait {$seconds} seconds before trying again."
            );
        return;
       }

        // Allow five requests every five minutes from each IP address plus user id.
        RateLimiter::hit($key, 60 * 5);

        // Validation
        $this->validate($this->rules(), $this->messages);

        return DB::transaction(function () {
            if (filled($this->name)) {
                $this->product->name = $this->name;
            }
            $this->product->price = $this->price;
            $this->product->quantity = $this->quantity;
            if($this->product->quantity < 1){
                $this->product->status = "out-of-stock";
            }
            $this->product->description = $this->description;
            $this->product->store_id = $this->store_id;

            $existingImageIds = collect($this->product_images)
                ->filter(fn ($productImage) => $productImage instanceof ProductImage)
                ->pluck('id');

            $this->product->productImages()
                ->when($existingImageIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $existingImageIds))
                ->delete();

            foreach ($this->product_images as $product_image) {
                if ($product_image instanceof ProductImage) {
                    continue;
                }

                $image = new ProductImage();
                $image->product_id = $this->product->id;
                $path = $product_image->store('product-images','public');
                $image->image = $path;
                $image->save();
            }
            $this->product->save();
            session()->flash('success','Product updated successfully');
            return $this->redirect(route('products'), navigate:true);
        });
    }
    
};
?>
<div class="dashboard-content">
        @php
            $rateLimitErrors = collect($errors->messages())
                ->except(['name', 'quantity', 'price','description', 'product_images','image', 'store_id'])
                ->flatten();
        @endphp 
        
         @if ($rateLimitErrors->isNotEmpty())
            @foreach ($rateLimitErrors as $error)
                <div class="alert alert-danger border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0 mt-0.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <ul class="mb-0 ps-2 list-unstyled">
                            <li>{{ $error }}</li>
                    </ul>
                </div>
            @endforeach
        @endif
        <div class="mb-4">
            <h5 class="fw-bold text-dark mb-1">Update Product</h5>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">

                <form wire:submit="save" class="needs-validation" novalidate>


                    <div class="row g-3 justify-content-center">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Name</label>
                            <input type="text" placeholder="Leave empty if you want it unchanged" required wire:model.live.debounce.500ms="name">
                            @error('name')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Price</label>
                            <input type="number" placeholder="#6,000" required wire:model.live.debounce.500ms="price" min="0" step="0.01" inputmode="numeric">
                            @error('price')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Quantity</label>
                            <input type="number" placeholder="30" wire:model.live.debounce.500ms="quantity" min="1" step="1" inputmode="numeric">
                            @error('quantity')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Store</label>
                            <input type="search" class="header-search-bar mx-auto d-block" placeholder="Search store" wire:model.live.debounce.500ms="searchTerm" inputmode="search">

                            <div class="store-search-results" aria-live="polite">
                                <div class="store-search-results-header">
                                    <span>Store search results</span>
                                    <span class="store-search-results-status" wire:loading wire:target="searchTerm">Searching...</span>
                                </div>
                                @if(!$searchTerm)
                                    <div class="store-search-results-empty" wire:loading.remove wire:target="searchTerm">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35m1.35-5.15a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" />
                                        </svg>
                                        <span>Matching stores will appear here.</span>
                                    </div>

                                    @else
                                    @forelse ($stores as $store)
                                        <p class="store-search-result" wire:key="store-search-result-{{ $store->id }}" wire:click="setStore({{ $store->id }})" wire:loading.attr="disabled">{{ $store->name }}</p>
                                         
                                        @empty
                                        <p class="text-muted text-center py-4">{{ "No results found for '{$searchTerm}'" }}</p>
                                    @endforelse
                                @endif


                            </div>

                            @error('store_id')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Description</label>
                            <textarea rows="3" placeholder="Short description about the product" wire:model.live.debounce.500ms="description"></textarea>
                            @error('description')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Product Images</label>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="file" wire:model="image" accept="image/*" required>
                                <button type="button" id="addSocialBtn" class="btn btn-outline-secondary rounded-pill px-3" wire:click="addProductImage" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="addProductImage">Add</span>  
                                    <span wire:loading wire:target="addProductImage">Adding...</span> 
                                </button>
                            </div>
                            @error('image')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror 
                        </div>

                            <div class="col-12">
                                <div class="product-image-previews mt-3" aria-label="Selected product images">
                                    @foreach ($product_images as $product_image)

                                        <div class="product-image-preview" wire:key="image-{{ $loop->index }}" wire:transition>
                                            <img src="{{ method_exists($product_image, 'temporaryUrl') ? $product_image->temporaryUrl() : asset('storage/' . $product_image->image) }}" alt="Product image {{ $loop->iteration }} preview">
                                            <button type="button" class="product-image-remove" aria-label="Remove product image {{ $loop->iteration }}" wire:click="removeProductImage({{ $loop->index }})" wire:loading.attr="disabled">
                                                <span wire:loading.remove wire:target="removeProductImage({{ $loop->index }})">&times;</span>
                                                <span wire:loading wire:target="removeProductImage({{ $loop->index }})">...</span>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                                @error('product_images')
                                    <div class="invalid-feedback mt-1 d-block text-center">{{ $message }}</div>
                                @enderror
                            </div>

                        <div class="col-12 col-sm-7 text-center mt-4">
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <button type="submit" class="fill-btn border-0">                        
                                            <span class="fill-btn-inner" wire:loading.remove wire:target="save">
                                                <span class="fill-btn-normal">Update Product</span>
                                                <span class="fill-btn-hover">Update Product</span>
                                            </span>

                                            <span class="fill-btn-inner" wire:loading wire:target="save">
                                                <span class="fill-btn-normal">Updating</span>
                                                <span class="fill-btn-hover">Updating</span>
                                            </span>
                                    </button>
                                </div>
                                <div class="col-12 col-sm-6 mt-4 mt-sm-0">
                                    <a href="{{ route('products') }}" class="fill-btn-red">
                                        <span class="fill-btn-inner">
                                            <span class="fill-btn-normal">Cancel</span>
                                            <span class="fill-btn-hover">Cancel</span>
                                        </span>
                                    </a>
                                </div>

                            </div> 
                       </div>
   

                           
                       


                    </div>

                </form>

            </div>
        </div>

</div>