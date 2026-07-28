<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\Store;

new class extends Component
{
     #[Title('Stores')]

     public function with() {
        $stores = Store::select(['name', 'phone_number', 'logo', 'description'])->get();
        return compact('stores');
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
            
        @endif

        <div class="row d-flex align-items-center justify-content-between mb-4">
            <div class="col-12 col-lg-4 text-center text-lg-start">
                <h4 class="fw-bold text-dark mb-1 h5">Stores</h4>
                <p class="text-muted small mb-0">Manage merchant stores — add, search, and view store data.</p>
            </div>

            <div class="col-12 col-lg-8">
                <div class="row d-flex justify-content-md-center justify-content-lg-end">
                    <div class="col-12 col-md-4 mt-4 mt-lg-0">
                        <input type="search" class="header-search-bar mx-auto d-block" placeholder="Search stores by name" />
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
            <div class="p-4 bg-white border-bottom d-flex align-items-center justify-content-between">
                <h4 class="fw-bold text-dark mb-0 h5">Stores</h4>
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
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stores as $store)
                            <tr>
                            <td class="fw-semibold text-dark">{{ $store->name }}</td>
                                <td>{{ $store->phone_number }}</td>
                                <td><img src="{{ asset('storage/' . $store->logo) }}" alt="logo" class="rounded" style="height:40px; width:auto;"></td>
                                <td>{{ $store->description }}</td>
                                <td>
                                    <button class="btn btn-outline-secondary btn-sm rounded-pill">Edit</button>
                                    <button class="btn btn-outline-danger btn-sm rounded-pill ms-2">Delete</button>
                                </td>
                            </tr>  
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted text-center py-4">No more stores found.</td>
                            </tr>     
                        @endforelse


                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
