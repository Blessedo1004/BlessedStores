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
     public ?Store $storeInfo = null;


     public function with() {
        $stores = Store::with('user:id,name,email','socials') 
            ->when($this->status , function($query){
            $query->where('status' , $this->status);
        })
        ->when($this->searchTerm , function($query){
            $query->where('slug', 'like', '%'.trim($this->searchTerm).'%')
            ->orWhere('name', 'like', '%'.trim($this->searchTerm).'%')
            ->orWhere('address', 'like', '%'.trim($this->searchTerm).'%');
        })
        ->latest()->paginate(10)->onEachSide(0);
        return compact('stores');
     }

     public function showStoreInfo($slug){
        // Authentication check
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

        // Authorization check
        else if (!auth()->user()->can('admin-or-super-admin')) {
            abort(403);
        }

        $this->storeInfo = Store::with('user','socials')->where('slug' , $slug)->firstOrFail();
        $this->showInfo = true;
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

        return DB::transaction(function () use ($slug) {        
            $store = Store::with('socials')->where('slug', $slug)->firstOrFail();
            $store->socials()->delete();
            $store->delete();
            session()->flash('store-delete-success', 'Store deleted successfully!');
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
        @if(session('store-registration-success'))
            <div class="alert alert-success border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center position-fixed">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0 mt-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{session('store-registration-success')}}</span>
            </div>

            @elseif (session('store-update-success'))    
                <div class="alert alert-success border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center position-fixed">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0 mt-0.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{session('store-update-success')}}</span>
                </div>
            @elseif ((session('store-delete-success')) )   
                <div class="alert alert-success border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center position-fixed">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0 mt-0.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{session('store-delete-success')}}</span>
                </div>    
        @endif

        <div class="row d-flex align-items-center justify-content-between mb-4">
            <div class="col-12 col-lg-4 text-center text-lg-start">
                <h5 class="fw-bold text-dark mb-1">Stores</h5>
                <p class="text-muted small mb-0">Manage merchant stores — add, search, and view store data.</p>
            </div>

            <div class="col-12 col-lg-8">
                <div class="row d-flex justify-content-md-center justify-content-lg-end">
                    <div class="col-12 mt-4 mt-lg-0">
                        <input type="search" class="header-search-bar mx-auto d-block" placeholder="Search stores by name" wire:model.live.debounce.500ms="searchTerm" inputmode="search">
                    </div>
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
                <span class="text-muted small">Total: {{ $stores->count()}}</span>
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
                                    <button class="btn btn-outline-info btn-sm rounded-pill" wire:click="showStoreInfo(@js($store->slug))" wire:loading.attr="disabled">View Info</button>
                                    <a href="{{ route('stores.edit', $store->slug) }}" class="btn btn-outline-secondary btn-sm rounded-pill" wire:navigate>Edit</a>
                                    <button class="btn btn-outline-danger btn-sm rounded-pill" wire:click="delete(@js($store->slug))" wire:confirm="Are you sure you want to delete this store?? This is a permanent action." wire:loading.attr="disabled">Delete</button>
                                </div>
                            </td>
                            </tr>  
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted text-center py-4">No stores found.</td>
                            </tr>     
                        @endforelse


                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Store Info Panel -->
    <div class="store-info-overlay {{ $showInfo ? '' : 'd-none' }}" wire:loading.class.remove="d-none" wire:target="showStoreInfo">
        <div class="store-info-panel">
            <div class="store-info-panel-header">
                <h5 class="store-info-panel-title">Store Information</h5>
                <button type="button" class="store-info-close" wire:click="$set('showInfo', false)" wire:loading.attr="disabled">×</button>
            </div>
            <div class="store-info-panel-body">
                <div class="store-info-card">
                    @if($showInfo)
                        <div class="store-info-placeholder">
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
