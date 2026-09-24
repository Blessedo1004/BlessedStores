<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use App\Models\Store;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Size;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use WithFileUploads;
    #[Title('Edit Product')]

    public Product $product;
    public string $name = '';
    public ?int $quantity = null;
    public ?string $price = '';
    public string $description = '';
    public string $sku='';
    public string $weight = '';
    public $image;
    public string $visibility='';
    public array $product_images = [];
    public string $storeTerm ='';
    public ?int $store_id = null;
    public string $categoryTerm ='';
    public array $selectedCategories = [];
    public array $selectedCategoryTerms = [];
    public string $brandTerm ='';
    public ?int $brand_id = null;
    public int $storeLoadAmount = 3;
    public int $categoryLoadAmount = 3;
    public int $brandLoadAmount = 3;
    public bool $removeAlert = false;
    public bool $hasSizes = false;
    public array $variantRows = [];

    public function mount($slug){
        $product = Product::with('store', 'productImages', 'categories', 'brand', 'productVariants.size')->where('slug', $slug)->where('user_id', auth()->user()->id)->firstOrFail();
        $this->product = $product;
        $this->quantity = $product->quantity;
        $this->price = $product->price;
        $this->description = $product->description;
        $this->storeTerm = $product->store->name;
        $this->visibility = $product->visibility;
        $this->store_id = $product->store_id;
        if($product->brand_id){
            $this->brandTerm = $product->brand->name;
            $this->brand_id =  $product->brand_id;
        }
        foreach($product->categories as $category){
            $this->selectedCategories[] = $category->id;
            $this->selectedCategoryTerms[] = $category->name;
        }
        if($product->weight){
            $this->weight = $product->weight;
        }

        $this->hasSizes = $product->productVariants->isNotEmpty();

        if ($this->hasSizes) {
            $this->variantRows = $product->productVariants->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'row_key' => 'variant-' . $variant->id,
                    'name' => $variant->name,
                    'size_id' => $variant->size_id,
                    'price' => (string) $variant->price,
                    'quantity' => (string) $variant->quantity,
                    'weight' => $variant->weight !== null ? (string) $variant->weight : '',
                    'sku' => $variant->sku,
                ];
            })->toArray();
        } else {
            $this->variantRows = [[
                'row_key' => uniqid('new-', true),
                'name' => '',
                'size_id' => '',
                'price' => '',
                'quantity' => '',
                'weight' => '',
                'sku' => '',
            ]];
        }

        $product_images = $product->productImages;
        foreach ($product_images as $image) {
            $this->product_images[] = $image;
        }

    }

    public function with(){
        $stores = collect();
        $storesTotal = 0;
        $categories = collect();
        $categoriesTotal = 0;
        $brands = collect();
        $brandsTotal = 0;

        if(filled($this->storeTerm)){
            $query = Store::select(['id','name'])->where('name', 'LIKE', '%' . trim($this->storeTerm) . '%')->where('user_id', auth()->user()->id);
            $storesTotal = $query->count();
            $stores = $query->take($this->storeLoadAmount)->orderBy('name', 'asc')->get(['id', 'name']);
        } else {
            $this->store_id = null;
        }

        if(filled($this->categoryTerm)){
            $query = Category::select(['id','name','parent_id'])->where('name', 'LIKE', '%' . trim($this->categoryTerm) . '%');
            $categoriesTotal = $query->count();
            $categories = $query->take($this->categoryLoadAmount)->orderBy('parent_id')->orderBy('name', 'asc')->get();
        }

         if(filled($this->brandTerm)){
            $query = Brand::select(['id','name'])->where('name', 'LIKE', '%' . trim($this->brandTerm) . '%');
            $brandsTotal = $query->count();
            $brands = $query->take($this->brandLoadAmount)->orderBy('name', 'asc')->get(['id', 'name']);
        } else {
            $this->brand_id = null;
        }

        $sizes = Size::orderBy('name')->get();

        return compact(
            'stores', 'storesTotal',
            'categories', 'categoriesTotal',
            'brands', 'brandsTotal',
            'sizes'
        );
    }

    public function loadMoreStores(){
        $this->storeLoadAmount+=3;
    }

    public function loadMoreCategories(){
        $this->categoryLoadAmount+=3;
    }

    public function loadMoreBrands(){
        $this->brandLoadAmount+=3;
    }

    protected $messages = [
        'store_id.required' => 'Please select a store from the search results.',
        'selectedCategories.required' => 'Please select at least one category.',
        'variantRows.*.name.required' => 'Please type a variant name.',
        'variantRows.*.price.required' => 'Please type a variant price.',
        'variantRows.*.price.numeric' => 'Please type a valid variant price.',
        'variantRows.*.price.min' => 'Variant price cannot be negative.',
        'variantRows.*.quantity.required' => 'Please type a variant quantity.',
        'variantRows.*.quantity.integer' => 'Please type a whole number for the variant quantity.',
        'variantRows.*.quantity.min' => 'Variant quantity must be at least 1.',
        'variantRows.*.weight.numeric' => 'Please type a valid variant weight.',
        'variantRows.*.weight.min' => 'Variant weight cannot be negative.',
        'variantRows.*.sku.required' => 'Please type a variant SKU.',
        'variantRows.*.sku.max' => 'Variant SKU cannot exceed 100 characters.',
        'variantRows.*.size_id.integer' => 'Please select a valid size.',
        'variantRows.*.size_id.distinct' => 'Please choose a different size for each variant.',
    ];

    public function updated($property)
    {
        if ($property !== 'removeAlert') {
            $this->removeAlert = false;
        }

        $this->validateOnly($property, $this->rules());

        if (str_starts_with($property, 'variantRows.')) {
            $this->syncVariantQuantity();
        }
    }

    protected function rules(): array
    {
        $rules = [
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
                'min:0',
                'regex:/^[^<>]*$/',
            ],
            'price' => [
                'required',
                'min:0',
                'decimal:0,2',
                'regex:/^[^<>]*$/',
            ],
            'weight' => [
                'nullable',
                'min:0.01',
                'decimal:0,2',
                'regex:/^[^<>]*$/',
            ],
            'sku' => [
                'nullable',
                'string',
                'max:100',
                'unique:products,sku',
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
            'selectedCategories' => [
                'required',
                'array',
                'min:1'
            ],
            'brand_id' => [
                'nullable',
                'integer'
            ],
            'visibility' => [
                'required',
                'string',
                'in:draft,published'
            ],

        ];

        if ($this->hasSizes) {
            $rules['variantRows'] = ['required', 'array', 'min:1'];
            $rules['variantRows.*.name'] = ['required', 'string', 'max:100', 'distinct'];
            $rules['variantRows.*.size_id'] = ['nullable', 'integer', 'distinct'];
            $rules['variantRows.*.price'] = ['required', 'numeric', 'min:0'];
            $rules['variantRows.*.quantity'] = ['required', 'integer', 'min:1'];
            $rules['variantRows.*.weight'] = ['nullable', 'numeric', 'min:0'];
            $rules['variantRows.*.sku'] = ['required', 'string', 'max:100'];
            $rules['quantity'] = ['nullable'];
            $rules['price'] = ['nullable'];
            $rules['weight'] = ['nullable'];
            $rules['sku'] = ['nullable'];
        }

        return $rules;
    }

    public function addProductImage()
    {
        $this->removeAlert = false;
        if (count($this->product_images) >= 5) {
            $this->addError('product_images', 'You may only add up to 5 product images.');
            return;
        }

        // Validate the image field
        $this->validate([
            'image' => 'required|mimetypes:image/jpeg,image/png,image/webp,image/avif|max:2048',
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
        $this->storeTerm = $store->name;
    }

    public function setCategory(Category $category){
        $category->load('parent');

        if ($category->parent && !in_array($category->parent->id, $this->selectedCategories, true)) {
            $this->selectedCategories[] = $category->parent->id;
            $this->selectedCategoryTerms[] = $category->parent->name;
        }

        if (!in_array($category->id, $this->selectedCategories, true)) {
            $this->selectedCategories[] = $category->id;
            $this->selectedCategoryTerms[] = $category->name;
        }
        $this->categoryTerm = '';
    }

    public function removeCategory(int $index): void
    {
        $categoryId = (int) ($this->selectedCategories[$index] ?? 0);
        if (!$categoryId) {
            return;
        }

        $categoryIdsToRemove = [$categoryId];
        $pendingIds = [$categoryId];

        while ($pendingIds !== []) {
            $childIds = Category::whereIn('parent_id', $pendingIds)->pluck('id')->all();
            $pendingIds = array_values(array_diff($childIds, $categoryIdsToRemove));
            $categoryIdsToRemove = array_merge($categoryIdsToRemove, $pendingIds);
        }

        foreach ($this->selectedCategories as $selectedIndex => $selectedCategoryId) {
            if (in_array((int) $selectedCategoryId, $categoryIdsToRemove, true)) {
                unset($this->selectedCategories[$selectedIndex], $this->selectedCategoryTerms[$selectedIndex]);
            }
        }

        $this->selectedCategories = array_values($this->selectedCategories);
        $this->selectedCategoryTerms = array_values($this->selectedCategoryTerms);
    }

    public function setBrand(Brand $brand){
        $this->brand_id = $brand->id;
        $this->brandTerm = $brand->name;
    }

    public function addVariantRow(): void
    {
        $this->variantRows[] = [
            'id' => null,
            'row_key' => uniqid('new-', true),
            'name' => '',
            'size_id' => '',
            'price' => '',
            'quantity' => '',
            'weight' => '',
            'sku' => '',
        ];
    }

    public function updatedHasSizes($hasSizes): void
    {
        if ((bool) $hasSizes) {
            $this->price = '';
            $this->quantity = collect($this->variantRows)->sum(fn ($variantRow) => (int) ($variantRow['quantity'] ?? 0));
            $this->weight = '';
            $this->sku = '';
            return;
        }

        $this->variantRows = [[
            'row_key' => uniqid('new-', true),
            'name' => '',
            'size_id' => '',
            'price' => '',
            'quantity' => '',
            'weight' => '',
            'sku' => '',
        ]];
        $this->quantity = null;
    }

    protected function syncVariantQuantity(): void
    {
        if ($this->hasSizes) {
            $this->quantity = collect($this->variantRows)->sum(fn ($variantRow) => (int) ($variantRow['quantity'] ?? 0));
        }
    }

    public function removeVariantRow(string $rowKey): void
    {
        if (count($this->variantRows) <= 1) {
            return;
        }

        foreach ($this->variantRows as $index => $variantRow) {
            if (($variantRow['row_key'] ?? null) === $rowKey) {
                unset($this->variantRows[$index]);
                break;
            }
        }
    }

    public function save(){
        $this->removeAlert = false;
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

        else if (!auth()->user()->can('store') || $this->product->user_id !== auth()->user()->id ) {
            abort(403);
        }

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

        RateLimiter::hit($key, 60 * 5);

        $this->validate($this->rules(), $this->messages);

        return DB::transaction(function () {
            if (filled($this->name)) {
                $this->product->name = $this->name;
            }
            $this->product->price = $this->hasSizes ? null : $this->price;
            $this->product->quantity = $this->hasSizes
                ? collect($this->variantRows)->sum(fn ($variantRow) => (int) ($variantRow['quantity'] ?? 0))
                : $this->quantity;
            $this->product->description = $this->description;
            $this->product->visibility = $this->visibility;
            $this->product->store_id = $this->store_id;
            
            if($this->brand_id){
                $this->product->brand_id = $this->brand_id;   
            }
            $this->product->weight = $this->weight !== '' ? (float) $this->weight : null;
            if(filled($this->sku)){
                $this->product->sku = $this->sku;
            }
            $this->product->categories()->sync($this->selectedCategories);
            $this->product->save();

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

            if ($this->hasSizes) {
                $variantIds = [];
                foreach ($this->variantRows as $variantRow) {
                    if (empty($variantRow['name']) || empty($variantRow['sku'])) {
                        continue;
                    }

                    $variant = ProductVariant::updateOrCreate(
                        ['id' => $variantRow['id'] ?? null, 'product_id' => $this->product->id],
                        [
                            'product_id' => $this->product->id,
                            'name' => $variantRow['name'],
                            'size_id' => $variantRow['size_id'] ?: null,
                            'price' => (float) $variantRow['price'],
                            'quantity' => (int) $variantRow['quantity'],
                            'weight' => $variantRow['weight'] !== '' ? (float) $variantRow['weight'] : null,
                            'sku' => $variantRow['sku'],
                        ]
                    );

                    $variantIds[] = $variant->id;
                }

                $this->product->productVariants()->whereNotIn('id', $variantIds)->delete();
            } else {
                $this->product->productVariants()->delete();
            }

            session()->flash('success','Product updated successfully');
            return $this->redirect(route('products'), navigate:true);
        });
    }
    
};
?>
<div class="dashboard-content">
        @php
            $rateLimitErrors = collect($errors->get('update'));
        @endphp 
        
         @if ($rateLimitErrors->isNotEmpty())
            @foreach ($rateLimitErrors as $error)
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
        <div class="mb-4">
            <h5 class="fw-bold text-dark mb-1">Update Product</h5>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">

                <form wire:submit="save" class="needs-validation" novalidate>


                    <div class="row g-3 justify-content-center">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Name</label>
                            <input type="text" placeholder="Leave blank if you want it unchanged" required wire:model.live.debounce.500ms="name">
                            @error('name')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <div class="border rounded-4 p-3 bg-light-subtle d-flex justify-content-between">
                                <label class="form-label fw-semibold text-dark small mb-3 d-block">Does this product have different variants?</label>
                                <div class="d-flex gap-3">
                                    <label class="d-flex align-items-center">
                                        <input type="radio" wire:model.live="hasSizes" value="1">
                                        <span>Yes</span>
                                    </label>
                                    <label class="d-flex align-items-center">
                                        <input type="radio" wire:model.live="hasSizes" value="0" checked>
                                        <span>No</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <span class="store-search-results-status" wire:loading wire:target="hasSizes"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span></span>

                        @if(!$hasSizes)
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
                                <label class="form-label fw-semibold text-dark small mb-2">Weight(kg)</label>
                                <input type="number" placeholder="optional" wire:model.live.debounce.500ms="weight" min="0" step="0.01" inputmode="numeric">
                                @error('weight')
                                    <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold text-dark small mb-2">SKU</label>
                                <input type="text" placeholder="Leave blank if you want it unchanged" required wire:model.live.debounce.500ms="sku">
                                @error('sku')
                                    <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        @else
                            <div class="col-12">
                                <div class="border rounded-4 p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="fw-semibold mb-0">Variants</h6>
                                        <button type="button" class="btn btn-sm btn-outline-dark" wire:click="addVariantRow" wire:loading.attr="disabled">
                                            <span wire:loading wire:target="addVariantRow">Adding...</span>
                                            <span wire:loading.remove wire:target="addVariantRow">+ Add another variant</span>
                                        </button>
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Variant name</th>
                                                    <th>Size (optional)</th>
                                                    <th>Price</th>
                                                    <th>Quantity</th>
                                                    <th>Weight(kg)</th>
                                                    <th>SKU</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($variantRows as $index => $variantRow)
                                                    <tr wire:key="variant-row-{{ $variantRow['row_key'] }}">
                                                        <td>
                                                            <input type="text" wire:model.live.debounce.500ms="variantRows.{{ $index }}.name" placeholder="e.g. Pro 256GB">
                                                            @error('variantRows.' . $index . '.name')
                                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                                            @enderror
                                                        </td>
                                                        <td>
                                                            <select wire:model.live.debounce.500ms="variantRows.{{ $index }}.size_id">
                                                                <option value="">None</option>
                                                                @foreach($sizes as $size)
                                                                    <option value="{{ $size->id }}">{{ $size->name }}</option>
                                                                @endforeach
                                                            </select>
                                                            @error('variantRows.' . $index . '.size_id')
                                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                                            @enderror
                                                        </td>
                                                        <td>
                                                            <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="variantRows.{{ $index }}.price" placeholder="₦">
                                                            @error('variantRows.' . $index . '.price')
                                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                                            @enderror
                                                        </td>
                                                        <td>
                                                            <input type="number" min="1" step="1" wire:model.live.debounce.500ms="variantRows.{{ $index }}.quantity" placeholder="Qty">
                                                            @error('variantRows.' . $index . '.quantity')
                                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                                            @enderror
                                                        </td>
                                                        <td>
                                                            <input type="number" min="0" step="0.01" wire:model.live.debounce.500ms="variantRows.{{ $index }}.weight" placeholder="optional">
                                                            @error('variantRows.' . $index . '.weight')
                                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                                            @enderror
                                                        </td>
                                                        <td>
                                                            <input type="text" wire:model.live.debounce.500ms="variantRows.{{ $index }}.sku" placeholder="SKU">
                                                            @error('variantRows.' . $index . '.sku')
                                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                                            @enderror
                                                        </td>
                                                        <td>
                                                            @if(count($variantRows) > 1)
                                                                <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeVariantRow('{{ $variantRow['row_key'] }}')" wire:loading.attr="disabled">
                                                                    <span wire:loading wire:target="removeVariantRow('{{ $variantRow['row_key'] }}')">
                                                                        Removing...
                                                                    </span>
                                                                    <span wire:loading.remove wire:target="removeVariantRow('{{ $variantRow['row_key'] }}')">
                                                                        Remove
                                                                    </span>
                                                                </button>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="col-12 col-md-6">
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
                            @error('store_id')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
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

                            <div class="d-flex flex-wrap gap-2 mt-3 justify-content-center">
                                @foreach ($selectedCategoryTerms as $categoryTermValue)
                                    <div class="social-badge" wire:key="selected-category-{{ $loop->index }}" wire:transition>
                                        <div class="platform">
                                            <div class="username">{{ $categoryTermValue }}</div>
                                        </div>
                                        <button type="button" class="remove-btn" aria-label="Remove {{ $categoryTermValue }}" wire:click="removeCategory({{ $loop->index }})" wire:loading.attr="disabled">
                                            <span wire:loading.remove wire:target="removeCategory({{ $loop->index }})">&times;</span>
                                            <span wire:loading wire:target="removeCategory({{ $loop->index }})">...</span>
                                        </button>
                                    </div>
                                @endforeach
                            </div>

                            @error('selectedCategories')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Brand</label>
                            <input type="search" class="header-search-bar mx-auto d-block" placeholder="Search brand" wire:model.live.debounce.500ms="brandTerm" inputmode="search">
                            <span class="store-search-results-status" wire:loading wire:target="brandTerm"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Searching...</span>
                            <span class="store-search-results-status" wire:loading wire:target="setBrand"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Setting brand...</span>

                            @if(filled($brandTerm))
                                <div class="store-search-results" aria-live="polite">
                                    <div class="store-search-results-header">
                                        <span>Brand search results</span>
                                    </div>
                                    <div class="store-search-results-body">
                                            @forelse ($brands as $brand)
                                                <p class="store-search-result" wire:key="brand-search-result-{{ $brand->id }}" wire:click="setBrand({{ $brand->id }})" wire:loading.attr="disabled">{{ $brand->name }}</p>
                                                @empty
                                                <p class="text-muted text-center py-4">{{ "No results found for '{$brandTerm}'" }}</p>
                                            @endforelse
                                            @if($brandLoadAmount < $brandsTotal)
                                                <p class="text-center load-more mt-4" wire:click="loadMoreBrands" wire:loading.attr="disabled">
                                                    <span wire:loading.remove wire:target="loadMoreBrands">Load More</span>
                                                    <span wire:loading wire:target="loadMoreBrands">Loading...</span>
                                                </p>
                                            @endif
                                    </div>
                                </div>    
                            @endif        
                            @error('brand_id')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2 d-block">Visibility</label>
                            <div class="d-flex gap-4">
                                <label class="d-flex align-items-center gap-2">
                                    <input type="radio" wire:model="visibility" value="draft">
                                    <span>Draft</span>
                                </label>
                                <label class="d-flex align-items-center gap-2">
                                    <input type="radio" wire:model="visibility" value="published">
                                    <span>Published</span>
                                </label>
                            </div>
                            @error('visibility')
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
                                            <img src="{{ $product_image instanceof ProductImage ? asset('storage/' . $product_image->image) : $product_image->temporaryUrl() }}" alt="Product image {{ $loop->iteration }} preview">
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
                                                <span class="fill-btn-normal">Updating...</span>
                                                <span class="fill-btn-hover">Updating...</span>
                                            </span>
                                    </button>
                                </div>
                                <div class="col-12 col-sm-6 mt-4 mt-sm-0">
                                    <a href="{{ route('products') }}" class="fill-btn-red" wire:navigate>
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