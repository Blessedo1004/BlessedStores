<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\StoreApplication;
use App\Models\User;
use App\Models\Store;
use App\Models\Social;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\StoreRegistrationEmail;
use App\Mail\StoreRejectionEmail;
use Livewire\Attributes\Validate;

new class extends Component
{
    use WithPagination;
     #[Title('Store Applications')]
     public string $status = '';
     public string $searchTerm = '';
     public ?StoreApplication $storeInfo = null;
     public ?string $currentShowSlug = null;
    public bool $isLoadingInfo = false;
     public bool $showRejectionReason = false;
     #[Validate('required|string|min:10|max:255')]
     public string $rejection_reason = '';


     public function with() {
        $query = StoreApplication::with('applicationSocials')
        ->when($this->status , function($query){
            $query->where('status' , $this->status);
        })
        ->when($this->searchTerm, function ($query) {
            $search = '%' . trim($this->searchTerm) . '%';

            $query->where(function ($query) use ($search) {
                $query->where('slug', 'like', $search)
                    ->orWhere('store_name', 'like', $search)
                    ->orWhere('address', 'like', $search);
            });
        });

        $stores = (clone $query)
        ->latest()->paginate(10)->onEachSide(0);

        $count = (clone $query)->count();

        return compact('stores','count');
     }

    public function showApplicationInfo($slug){
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

        if (!auth()->user()->can('admin-or-super-admin')) {
            abort(403);
        }

        if ($this->isLoadingInfo && $this->currentShowSlug !== $slug) {
            return;
        }

        $this->isLoadingInfo = true;
        $this->currentShowSlug = $slug;

        $store = StoreApplication::with('applicationSocials')->where('slug', $slug)->firstOrFail();

        if ($this->currentShowSlug !== $slug) {
            return;
        }

        $this->storeInfo = $store;
        $this->isLoadingInfo = false;
    }

    public function closeApplicationInfo()
    {
        $this->storeInfo = null;
        $this->currentShowSlug = null;
        $this->isLoadingInfo = false;
        if ($this->rejection_reason) {
           $this->rejection_reason = false;
        }
    }

    public function approveApplication (){
        // Authentication check
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

        // Authorization check
        else if (!auth()->user()->can('admin-or-super-admin')) {
            abort(403);
        }

       // Rate limiting 
       $key = 'approve-application:' . request()->ip();
       
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError(
                'approve',
                "Too many requests. Please wait {$seconds} seconds before trying again."
            );
        return;
       }

        // Allow five requests every five minutes from each IP address.
        RateLimiter::hit($key, 60 * 5);

        return DB::transaction(function () {
            $userExists = User::where('email',$this->storeInfo->email)->firstOrFail();
            if(!$userExists){
                $password = Str::random(8);
                $user = new User();
                $user->name = $this->storeInfo->owner_name;
                $user->email = $this->storeInfo->email;
                $user->password = Hash::make($password);
                $user->role = 'store';
                $user->save();
            }

            $storeData = Store::create([
                'user_id' => $userExists ? $userExists->id : $user->id,
                'name' => $this->storeInfo->store_name,
                'phone_number' => $this->storeInfo->phone_number,
                'logo' => $this->storeInfo->logo,
                'description' => $this->storeInfo->description,
                'address' => $this->storeInfo->address
            ]);

            foreach ($this->storeInfo->applicationSocials as $social) {
                $socialEntry = new Social();
                $socialEntry->store_id = $storeData->id;
                $socialEntry->platform = $social['platform'];
                $socialEntry->user_name = $social['user_name'];
                $socialEntry->save();
            }

            $this->storeInfo->status = 'approved';
            $this->storeInfo->reviewed_by = auth()->user()->id;
            $this->storeInfo->reviewed_at = now();
            $this->storeInfo->save();

            $email = $this->storeInfo->email;
            $owner = $this->storeInfo->owner_name;
            $name = $this->storeInfo->store_name;

            $this->storeInfo = null;
            $this->currentShowSlug = null;
            $this->isLoadingInfo = false;
            if ($this->rejection_reason) {
               $this->rejection_reason = '';
            }

            if($userExists){
                Mail::to($email)->send(new StoreRegistrationEmail($owner, $name));
            }
            else{
                Mail::to($email)->send(new StoreRegistrationEmail($password, $owner, $name));
            }    
            session()->flash('success','Store registered successfully');
        });
    }

    public function rejectApplication (){
        // Authentication check
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

        // Authorization check
        else if (!auth()->user()->can('admin-or-super-admin')) {
            abort(403);
        }

       // Rate limiting 
       $key = 'reject-application:' . request()->ip();
       
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError(
                'reject',
                "Too many requests. Please wait {$seconds} seconds before trying again."
            );
        return;
       }

        // Allow five requests every five minutes from each IP address.
        RateLimiter::hit($key, 60 * 5);

        return DB::transaction(function () {
            $this->storeInfo->status = 'rejected';
            $this->storeInfo->reviewed_by = auth()->user()->id;
            $this->storeInfo->reviewed_at = now();
            $this->storeInfo->rejection_reason = $this->rejection_reason;
            $this->storeInfo->save();

            $email = $this->storeInfo->email;
            $owner = $this->storeInfo->owner_name;
            $name = $this->storeInfo->store_name;
            $rejection_reason = $this->rejection_reason;

            $this->storeInfo = null;
            $this->currentShowSlug = null;
            $this->isLoadingInfo = false;
            $this->showRejectionReason = false;
            if ($this->rejection_reason) {
               $this->rejection_reason = '';
            }

            Mail::to($email)->send(new StoreRejectionEmail($owner, $name, $rejection_reason));
            session()->flash('success','Store application rejected successfully');
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
        
        @php
            $rateLimitErrors = collect($errors->messages())
                ->except(['rejection_reason'])
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

        <div class="row d-flex align-items-center justify-content-between mb-4">
            <div class="col-12 col-lg-4 text-center text-lg-start">
                <h5 class="fw-bold text-dark mb-1">Store Applications</h5>
                <p class="text-muted small mb-0">Manage store applications — review, approve, or reject pending applications.</p>
            </div>

            <div class="col-12 col-lg-8">
                <div class="row d-flex justify-content-md-center justify-content-lg-end">
                    <div class="col-12 mt-4 mt-lg-0">
                        <input type="search" class="header-search-bar mx-auto d-block" placeholder="Search store applications by name or address" wire:model.live.debounce.500ms="searchTerm" inputmode="search">
                    </div>
                </div>

            </div>


        </div>

        <div class="custom-table-container">
            <div class="p-4 bg-white border-bottom">
                <label class="form-label fw-semibold text-dark small mb-2">Filter store applications</label>
                <select class="form-select" wire:model.live="status">
                    <option value="">All applications</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div class="p-4 bg-white border-bottom d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-dark mb-0">Store Applications</h6>
                <span class="text-muted small">Total: {{ $count}}</span>
            </div>

            <div class="table-responsive">
                <table class="table custom-table mb-0 w-100">
                    <thead>
                        <tr>
                            <th>Owner Name</th>
                            <th>Store Name</th>
                            <th>Phone Number</th>
                            <th>Logo</th>
                            <th>Description</th>
                            <th>Address</th>
                            <th>Date of Application</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stores as $store)
                        <tr wire:key="store-{{ $store->id }}" wire:transition>
                            <td class="fw-semibold text-dark">{{ Str::limit($store->owner_name, 30) }}</td>
                            <td class="fw-semibold text-dark">{{ Str::limit($store->store_name, 30) }}</td>
                            <td>{{ $store->phone_number }}</td>
                            <td>
                                <img src="{{ asset('storage/' . $store->logo) }}" alt="logo" class="rounded" style="height:40px; width:auto;">
                            </td>
                            <td>{{Str::limit($store->description, 50) }}</td>
                            <td>{{ Str::limit($store->address, 50) }}</td>
                            <td>{{ $store->created_at->format('M d,Y') }}</td>
                            <td>
                                <span class="status-badge {{ $store->status === 'pending' ? 'bg-warning' : ($store->status === 'approved' ? 'bg-success' : 'bg-danger')}}">
                                    {{ $store->status }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-column flex-sm-row align-items-center gap-2">
                                    <button class="btn btn-outline-info btn-sm rounded-pill" wire:click="showApplicationInfo(@js($store->slug))" wire:loading.attr="disabled" @disabled($isLoadingInfo)>View Info</button>
                                </div>
                            </td>
                            </tr>  
                        @empty
                            <tr>
                                <td colspan="7" class="text-muted text-center py-4">{{ $searchTerm ? "No results found for '{$searchTerm}'" : "No store applications found." }}</td>
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
    <div class="store-info-overlay {{ $storeInfo ? '' : 'd-none' }}" wire:loading.class.remove="d-none" wire:target="showApplicationInfo" wire:transition>
        <div class="store-info-panel">
            <div class="store-info-panel-header">
                <h5 class="store-info-panel-title">Store Information</h5>
                    <button type="button" class="store-info-close" wire:click="closeApplicationInfo" wire:loading.attr="disabled" onclick="this.closest('.store-info-overlay').classList.add('d-none')">×</button>
            </div>
            <div class="store-info-panel-body">
                <div class="store-info-card">
                    @if($storeInfo)
                        <div class="store-info-placeholder">
                            <p><strong>Store Owner:</strong> {{ $storeInfo->owner_name }}</p>
                            <p><strong>Email:</strong> {{ $storeInfo->email }}</p>
                            <p><strong>Store Name:</strong> {{ $storeInfo->store_name }}</p>
                            <p><strong>Address:</strong> {{ $storeInfo->address }}</p>
                            <p><strong>Logo:</strong> 
                                <img src="{{ asset('storage/' . $storeInfo->logo) }}" alt="Store Logo" class="img-fluid" style="max-height: 100px;">
                            </p>
                            <p><strong>Phone Number:</strong> {{ $storeInfo->phone_number }}</p>
                            <p><strong>Description:</strong> {{ $storeInfo->description }}</p>
                            <p><strong>Social Media:</strong></p>
                            <ul>
                                @foreach($storeInfo->applicationSocials as $social)
                                    <li><h6 class="platform-name">{{ $social->platform }}:</h6> <span class="username">{{ $social->user_name }}</span></li>
                                @endforeach
                            </ul>
                            <p class="mt-2"><strong>Date of Application:</strong> {{ $storeInfo->created_at->format('M d, Y') }}</p>
                            <p><strong>Status:</strong> {{ $storeInfo->status }}</p>
                            @if($storeInfo->reviewed_by)
                                <p><strong>Reviewed By:</strong> {{ $storeInfo->reviewer->name }}</p>
                            @endif
                             @if($storeInfo->reviewed_at)
                                <p><strong>Reviewed At:</strong> {{ $storeInfo->reviewed_at->format('M d, Y g:iA') }}</p>
                            @endif
                            @if($storeInfo->rejection_reason)
                                <p><strong>Reason for Rejection:</strong> {{ $storeInfo->rejection_reason }}</p>
                            @endif
                            <div class="row mt-4 justify-content-center d-flex">
                                @if($storeInfo->status === "pending")
                                    <div class="col-12 col-sm-6 text-center">
                                        <button class="fill-btn border-0" wire:click="approveApplication" wire:loading.attr="disabled">                        
                                            <span class="fill-btn-inner" wire:loading.remove wire:target="approveApplication">
                                                <span class="fill-btn-normal">Approve</span>
                                                <span class="fill-btn-hover">Approve</span>
                                            </span>

                                            <span class="fill-btn-inner" wire:loading wire:target="approveApplication">
                                                <span class="fill-btn-normal">Approving...</span>
                                                <span class="fill-btn-hover">Approving...</span>
                                            </span>
                                        </button>
                                    </div>
                                    <div class="col-12 col-sm-6 mt-4 mt-sm-0 text-center">
                                        <button class="fill-btn-red" wire:click="$set('showRejectionReason', true)" wire:loading.attr="disabled" @disabled($showRejectionReason)>
                                            <span class="fill-btn-inner" wire:loading.remove wire:target="showRejectionReason">
                                                <span class="fill-btn-normal">Reject</span>
                                                <span class="fill-btn-hover">Reject</span>
                                            </span>

                                            <span class="fill-btn-inner" wire:loading wire:target="showRejectionReason">
                                                <span class="fill-btn-normal">Please wait...</span>
                                                <span class="fill-btn-hover">Please wait...</span>
                                            </span>
                                        </button>
                                    </div>    
                                @endif
                                @if ($showRejectionReason)
                                    <p class="mt-4">
                                       <strong>Reason for rejection</strong>
                                    </p>
                                    <textarea rows="3" wire:model.live.debounce.500ms="rejection_reason" class="mt-2"></textarea>
                                    @error('rejection_reason')
                                        <div class="text-danger mt-1 d-block">{{ $message }}</div>
                                    @enderror
                                    <div class="col-12 col-lg-6 mt-4">
                                        <button class="fill-btn border-0" wire:click="rejectApplication" wire:loading.attr="disabled" >                        
                                            <span class="fill-btn-inner" wire:loading.remove wire:target="rejectApplication">
                                                <span class="fill-btn-normal">Send Rejection</span>
                                                <span class="fill-btn-hover">Send Rejection</span>
                                            </span>

                                            <span class="fill-btn-inner" wire:loading wire:target="rejectApplication">
                                                <span class="fill-btn-normal">Sending...</span>
                                                <span class="fill-btn-hover">Sending...</span>
                                            </span>
                                        </button>
                                    </div>     
                                @endif
                            </div>
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
