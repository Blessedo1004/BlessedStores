<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\Store;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    use WithPagination;
    #[Title('Stores')]
    public string $status = '';
    public string $searchTerm = '';
    public bool $showInfo = false;
    public bool $isLoadingInfo = false;
    public ?string $currentShowSlug = null;
    public ?Store $storeInfo = null;
    public bool $removeAlert = false;


     public function with() {
        $user = auth()->user();
        $isAdmin = $user->can('admin-or-super-admin');

        $query = Store::with('user:id,name,email', 'socials')
            ->when(!$isAdmin, function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->when($this->status, function ($query) {
                $query->where('status', $this->status);
            })
            ->when($this->searchTerm, function ($query) {
                $search = '%' . trim($this->searchTerm) . '%';

                $query->where(function ($query) use ($search) {
                    $query->where('slug', 'like', $search)
                        ->orWhere('name', 'like', $search)
                        ->orWhere('address', 'like', $search);
                });
            });

        $stores = (clone $query)
        ->latest()->paginate(10)->onEachSide(0);

        $count = (clone $query)->count();
        return compact('stores', 'count');
     }

     public function showStoreInfo($slug){
        // Authentication check
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

        if ($this->isLoadingInfo && $this->currentShowSlug !== $slug) {
            return;
        }

        $this->isLoadingInfo = true;
        $this->currentShowSlug = $slug;

        $store = Store::with('user','socials')->where('slug' , $slug)->firstOrFail();

        if ($this->currentShowSlug !== $slug) {
            return;
        }

        // Authorization check
        if (!auth()->user()->can('admin-or-super-admin') && $store->user_id !== auth()->id()) {
            abort(403);
        }
        
        $this->storeInfo = $store;
        $this->showInfo = true;
        $this->isLoadingInfo = false;
    }

    public function delete($slug){
        $this->removeAlert = false;
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
        $this->removeAlert = false;
        $this->resetPage();
    }

    public function updatedSearchTerm()
    {
        $this->removeAlert = false;
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
                <h5 class="fw-bold text-dark mb-1">Stores</h5>
                <p class="text-muted small mb-0">Manage merchant stores — add, search, and view store data.</p>
            </div>

            <div class="col-12 col-lg-8">
                <div class="row d-flex justify-content-center justify-content-lg-end">
                    <div class="col-12 col-md-6 mt-4 mt-lg-0">
                        <input type="search" class="header-search-bar mx-auto d-block" placeholder="Search stores by name or address" wire:model.live.debounce.500ms="searchTerm" inputmode="search">
                    </div>

                    @can('store')
                        <div class="col-12 col-md-6 mt-4 mt-lg-0 text-center">
                            <a class="fill-btn border-0" href="{{ route('stores.register') }}"  wire:navigate>                        
                                <span class="fill-btn-inner">
                                    <span class="fill-btn-normal">Add Store</span>
                                    <span class="fill-btn-hover">Add Store</span>
                                </span>
                            </a>
                        </div>
                    @endcan
                </div>

            </div>


        </div>

        <div class="custom-table-container">
            <div class="p-4 bg-white border-bottom">
                <label class="form-label fw-semibold text-dark small mb-2">Filter stores</label>
                <select class="form-select" wire:model.live="status">
                    <option value="">All stores</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="p-4 bg-white border-bottom d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-dark mb-0">Stores</h6>
                <span class="text-muted small">Total: {{ $count}}</span>
            </div>

            <div class="table-responsive">
                <table class="table custom-table mb-0 w-100">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Phone Number</th>
                            <th>Logo</th>
                            <th>Description</th>
                            <th>Address</th>
                            <th>Date of Registration</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stores as $store)
                        <tr wire:key="store-{{ $store->id }}" wire:transition>
                            <td class="fw-semibold text-dark">{{ Str::limit($store->name, 30) }}</td>
                            <td>{{ $store->phone_number }}</td>
                            <td>
                                <img src="{{ asset('storage/' . $store->logo) }}" alt="logo" class="rounded" style="height:40px; width:auto;">
                            </td>
                            <td>{{Str::limit($store->description, 50) }}</td>
                            <td>{{ Str::limit($store->address, 50) }}</td>
                            <td>{{ $store->created_at->format('M d,Y') }}</td>
                            <td>
                                <span class="status-badge {{ $store->status === 'active' ? 'bg-success' : 'bg-danger'}}">
                                    {{ $store->status }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-column flex-sm-row align-items-center gap-2">
                                    <button class="btn btn-outline-info btn-sm rounded-pill" wire:click="showStoreInfo(@js($store->slug))" wire:loading.attr="disabled" @disabled($isLoadingInfo)>View Info</button>
                                    <a href="{{ route('stores.edit', $store->slug) }}" class="btn btn-outline-secondary btn-sm rounded-pill" wire:navigate>Edit</a>
                                    <button class="btn btn-outline-danger btn-sm rounded-pill" wire:click="delete(@js($store->slug))" wire:confirm="Are you sure you want to delete this store?? This is a permanent action." wire:loading.attr="disabled">Delete</button>
                                </div>
                            </td>
                            </tr>  
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted text-center py-4">{{ $searchTerm ? "No results found for '{$searchTerm}'" : "No stores found." }}</td>
                            </tr>     
                        @endforelse


                    </tbody>
                </table>
            </div>
            <div class="p-3">
                {{ $stores->links() }}
            </div>
        </div>

    </div>

    <!-- Store Info Panel -->
    <div class="store-info-overlay {{ $showInfo ? '' : 'd-none' }}" wire:loading.class.remove="d-none" wire:target="showStoreInfo" wire:transition>
        <div class="store-info-panel">
            <div class="store-info-panel-header">
                <h5 class="store-info-panel-title">Store Information</h5>
                <button type="button" class="store-info-close" wire:click="$set('showInfo', false); $set('currentShowSlug', null)" wire:loading.attr="disabled">×</button>
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
    </div>

</div>
