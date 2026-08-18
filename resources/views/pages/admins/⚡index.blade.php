<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\User;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
     #[Title('Admins')]
     public string $status = '';
     public string $searchTerm = '';


     public function with() {
        $query = User::where('role', 'admin')
        -> when($this->status , function($query){
            $query->where('status' , $this->status);
        })
        ->when($this->searchTerm, function ($query) {
            $search = '%' . trim($this->searchTerm) . '%';

            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        });

        $admins = (clone $query)
        ->latest()->paginate(10)->onEachSide(0);

        $count = (clone $query)->count();
        return compact('admins' , 'count');
     }

    public function delete(User $user){
        // Authentication check
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

        // Authorization check
        else if (!auth()->user()->can('super-admin')) {
            abort(403);
        }

        // Rate limiting
        $key = 'delete-admin:' . $user->id . ':' . request()->ip();
       
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
 
        $user->delete();
        session()->flash('success', 'Admin deleted successfully!');

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

        <div class="row d-flex align-items-center justify-content-center mb-4">
            <div class="col-12 col-lg-4 text-center text-lg-start">
                <h5 class="fw-bold text-dark mb-1">Admins</h5>
                <p class="text-muted small mb-0">Manage admins — add, search, and view admin data.</p>
            </div>

            <div class="col-12 col-lg-8">
                <div class="row d-flex justify-content-center justify-content-lg-end">
                    <div class="col-12 col-md-6 mt-4 mt-lg-0">
                        <input type="search" class="header-search-bar mx-auto d-block" placeholder="Search admins by name or email" wire:model.live.debounce.500ms="searchTerm" inputmode="search">
                    </div>

                    <div class="col-12 col-md-6 mt-4 mt-lg-0 text-center">
                        <a class="fill-btn border-0" href="{{ route('admins.create') }}"  wire:navigate>                        
                            <span class="fill-btn-inner">
                                <span class="fill-btn-normal">Add Admin</span>
                                <span class="fill-btn-hover">Add Admin</span>
                            </span>
                        </a>
                    </div>

                </div>

            </div>


        </div>

        <div class="custom-table-container">
            <div class="p-4 bg-white border-bottom">
                <label class="form-label fw-semibold text-dark small mb-2">Filter admins</label>
                <select class="form-select" wire:model.live="status">
                    <option value="">All admins</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="p-4 bg-white border-bottom d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-dark mb-0">Admins</h6>
                <span class="text-muted small">Total: {{ $count}}</span>
            </div>

            <div class="table-responsive">
                <table class="table custom-table mb-0 w-100">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Date Of Registration</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($admins as $admin)
                        <tr wire:key="admin-{{ $admin->id }}" wire:transition>
                            <td class="fw-semibold text-dark">{{ Str::limit($admin->name, 30) }}</td>
                            <td>{{ $admin->email }}</td>
                            <td>{{ $store->created_at->format('M d,Y') }}</td>
                            <td>
                                <span class="status-badge {{ $admin->status === 'active' ? 'bg-success' : 'bg-danger'}}">
                                    {{ $admin->status }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-column flex-sm-row align-items-center gap-2">
                                    <a href="{{ route('admins.edit') }}" class="btn btn-outline-secondary btn-sm rounded-pill" wire:navigate>Edit</a>
                                    <button class="btn btn-outline-danger btn-sm rounded-pill" wire:click="delete({{ $admin->id }})" wire:confirm="Are you sure you want to delete this store?? This is a permanent action." wire:loading.attr="disabled">Delete</button>
                                </div>
                            </td>
                            </tr>  
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted text-center py-4">No admins found.</td>
                            </tr>     
                        @endforelse


                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
