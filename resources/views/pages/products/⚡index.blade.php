<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\Product;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use WithPagination;
     #[Title('Products')]
     public string $status = '';
     public string $searchTerm = '';

     public function with() {
        $query = Product::with('store', 'productImages')
            ->when($this->status, function ($query) {
                $query->where('status', $this->status);
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
        return compact('products', 'count');
     }


    public function delete($slug){
        // Authentication check
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

        // Authorization check
        else if (!auth()->user()->can('admin-or-super-admin')) {
            abort(403);
        }

        $store = Store::with('socials')->where('slug', $slug)->firstOrFail();

        // Rate limiting
        $key = 'delete-store:' . $store->id . ':' . request()->ip();
       
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

        return DB::transaction(function () use ($store){        
            $store->socials()->delete();
            $store->delete();
            session()->flash('success', 'Store deleted successfully!');
        });    

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
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0 mt-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{session('success')}}</span>
            </div>   
        @endif

        
         @if ($errors->any())
            @foreach ($errors->all() as $error)
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
            <div class="p-4 bg-white border-bottom">
                <label class="form-label fw-semibold text-dark small mb-2">Filter products</label>
                <select class="form-select" wire:model.live="status">
                    <option value="">All products</option>
                    <option value="in-stock">In Stock</option>
                    <option value="out-of-stock">Out of Stock</option>
                </select>
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
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                        <tr wire:key="product-{{ $product->id }}" wire:transition>
                            <td class="fw-semibold text-dark">{{ Str::limit($product->name, 30) }}</td>
                            <td>{{ $product->quantity }}</td>
                            <td>{{ $product->price }}</td>
                            <td>{{Str::limit($product->description, 50) }}</td>
                            <td>
                                <span class="status-badge {{ $product->status === 'in-stock' ? 'bg-success' : 'bg-danger'}}">
                                    {{ $product->status }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-column flex-sm-row align-items-center gap-2">
                                    <button class="btn btn-outline-info btn-sm rounded-pill" wire:click="showStoreInfo(@js($product->slug))" wire:loading.attr="disabled">View Info</button>
                                    <a href="" class="btn btn-outline-secondary btn-sm rounded-pill" wire:navigate>Edit</a>
                                    <button class="btn btn-outline-danger btn-sm rounded-pill" wire:click="delete(@js($product->slug))" wire:confirm="Are you sure you want to delete this store?? This is a permanent action." wire:loading.attr="disabled">Delete</button>
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
        </div>

    </div>

    <!-- Store Info Panel -->
    {{-- <div class="store-info-overlay {{ $showInfo ? '' : 'd-none' }}" wire:loading.class.remove="d-none" wire:target="showStoreInfo" wire:transition>
        <div class="store-info-panel">
            <div class="store-info-panel-header">
                <h5 class="store-info-panel-title">Store Information</h5>
                <button type="button" class="store-info-close" wire:click="$set('showInfo', false)" wire:loading.attr="disabled">×</button>
            </div>
            <div class="store-info-panel-body">
                <div class="store-info-card">
                    @if($showInfo)
                        <div class="store-info-placeholder" wire:transition>
                            <p><strong>Store Owner:</strong> {{ $storeInfo->user->name }}</p>
                            <p><strong>Email:</strong> {{ $storeInfo->user->email }}</p>
                            <p><strong>Store Name:</strong> {{ $storeInfo->name }}</p>
                            <p><strong>Address:</strong> {{ $storeInfo->address }}</p>
                            <p><strong>Logo:</strong> 
                                <img src="{{ asset('storage/' . $storeInfo->logo) }}" alt="Store Logo" class="img-fluid" style="max-height: 100px;">
                            </p>
                            <p><strong>Phone Number:</strong> {{ $storeInfo->phone_number }}</p>
                            <p><strong>Description:</strong> {{ $storeInfo->description }}</p>
                            <p><strong>Social Media:</strong></p>
                            <ul>
                                @foreach($storeInfo->socials as $social)
                                    <li><h6 class="platform-name">{{ $social->platform }}:</h6> <span class="username">{{ $social->user_name }}</span></li>
                                @endforeach
                            </ul>
                            <p class="mt-2"><strong>Date of Registration:</strong> {{ $storeInfo->created_at->format('M d, Y') }}</p>
                            <p><strong>Status:</strong> {{ $storeInfo->status }}</p>
                        </div>
                    @else
                        <div class="store-info-loading">
                            Loading store details...
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div> --}}

</div>
