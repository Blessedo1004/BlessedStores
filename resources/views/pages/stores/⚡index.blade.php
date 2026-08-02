<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\Store;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
     #[Title('Stores')]
     public string $status = '';
     public string $searchTerm = '';


     public function with() {
        $stores = Store::with('user:id,name,email','socials:id,store_id,platform,user_name')->
        select(['id','user_id','name', 'phone_number', 'logo', 'description','address','status', 'slug', 'created_at'])
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

    public function delete($slug){
        // Authentication check
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

        // Authorization check
        else if (!auth()->user()->can('admin-or-super-admin')) {
            abort(403);
        }

        $store = Store::with('user','socials')->where('slug', $slug)->firstOrFail();

        $store->user->delete();
        $store->socials()->delete();
        $store->delete();

        session()->flash('success', 'Store deleted successfully!');

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

        <div class="row d-flex align-items-center justify-content-between mb-4">
            <div class="col-12 col-lg-4 text-center text-lg-start">
                <h5 class="fw-bold text-dark mb-1">Stores</h5>
                <p class="text-muted small mb-0">Manage merchant stores — add, search, and view store data.</p>
            </div>

            <div class="col-12 col-lg-8">
                <div class="row d-flex justify-content-md-center justify-content-lg-end">
                    <div class="col-12 col-md-4 mt-4 mt-lg-0">
                        <input type="search" class="header-search-bar mx-auto d-block" placeholder="Search stores by name" wire:model.live.debounce.500ms="searchTerm" inputmode="search">
                    </div>
                    <div class="col-12 col-md-6 mt-4 mt-lg-0 text-center">
                        <a href="{{ route('stores.add') }}" class="fill-btn border-0" wire:navigate>
                                <span class="fill-btn-inner">
                                    <span class="fill-btn-normal">Add Store</span>
                                    <span class="fill-btn-hover">Add Store</span>
                                </span>
                        </a>
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
                            <td class="d-flex">
                                <a href="{{ route('stores.edit', $store->slug) }}" class="btn btn-outline-secondary btn-md rounded-pill" wire:navigate>Edit</a>
                                <button class="btn btn-outline-danger btn-md rounded-pill ms-2" wire:click="delete(@js($store->slug))" wire:confirm="Are you sure you want to delete this store?? This is a permanent action.">Delete</button>
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
</div>
