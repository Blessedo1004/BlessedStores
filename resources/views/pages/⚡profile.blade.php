<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use Illuminate\Support\Facades\RateLimiter;

new class extends Component
{
    #[Title('Profile')]

    public User $user;
    public string $name = '';
    public string $email = '';
    public string $status = '';
    public bool $showModal = false;
    public string $categoryTerm ='';
    public array $selectedCategories = [];
    public array $selectedCategoryTerms = [];
    public int $categoryLoadAmount = 3;
    public bool $removeAlert = false;

    public function mount(){
        $this->user = auth()->user();
        $this->name = auth()->user()->name;
        $this->status = auth()->user()->status;
        if(auth()->user()->categories){
            foreach(auth()->user()->categories as $category){
                $this->selectedCategories[] = $category->id;
                $this->selectedCategoryTerms[] = $category->name;
            }
        }
    }

    public function with(){
        $categories = collect();
        $categoriesTotal = 0;

        if(filled($this->categoryTerm)){
            $query = Category::select(['id','name'])->where('name', 'LIKE', '%' . trim($this->categoryTerm) . '%');
            $categoriesTotal = $query->count();
            $categories = $query->take($this->categoryLoadAmount)->get(['id', 'name']);
        }

        return compact(
            'categories', 'categoriesTotal'
        );
    }

    public function updated($property)
    {
        $this->validateOnly($property, $this->rules());
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[^<>]*$/',
            ],

            'email' => [
                'nullable',
                'email',
                'unique:users,email',
            ],
            'status' => [
                'required',
                'in:active,suspended',
            ],
            'selectedCategories' => [
             'nullable',
             'array',
             'max:5']

    ];
    }

    public function loadMoreCategories(){
        $this->categoryLoadAmount+=3;
    }

    public function setCategory(Category $category){
        if (count($this->selectedCategories) >= 5) {
            $this->addError('categories', 'You can select a maximum of 5 categories.');
            return;
        }
        if (!in_array($category->id, $this->selectedCategories, true)) {
            $this->selectedCategories[] = $category->id;
            $this->selectedCategoryTerms[] = $category->name;
        }
        $this->categoryTerm = '';
    }

    public function removeCategory($index)
    {
        unset($this->selectedCategories[$index], $this->selectedCategoryTerms[$index]);
        $this->selectedCategories = array_values($this->selectedCategories);
        $this->selectedCategoryTerms = array_values($this->selectedCategoryTerms);
    }

    public function showDeleteAccountModal()
    {
        if (!Auth::check()) {
            return $this->redirect(route('login'));
        }

        $this->showModal = true;
    }

    public function save(){
        // Authentication check
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

       // Rate limiting
        $key = 'update-profile:' . $this->user->id . ':' . request()->ip();
       
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError(
                'update',
                "Too many requests. Please wait {$seconds} seconds before trying again."
            );
        return;
       }

        // Allow five requests every five minutes from each IP address.
        RateLimiter::hit($key, 60 * 5);

        // Validation
        $this->validate($this->rules());

        return DB::transaction(function () {
            $this->user->name = $this->name;
            if(filled($this->email)){
               $this->user->email = $this->email;
            }
            if($this->selectedCategories){
                auth()->user()->categories()->sync($this->selectedCategories);
            }
            $this->user->status = $this->status;
            $this->user->save();
            session()->flash('success','Profile updated successfully');
        });
    }
    
};
?>
<div class="dashboard-content">
        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center position-fixed">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{session('success')}}</span>
            </div>    
        @endif

        @php
            $rateLimitErrors = collect($errors->messages())
                ->except(['name', 'email','selectedCategories'])
                ->flatten();
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
            <h5 class="fw-bold text-dark mb-1">User Profile</h5>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">

                <form wire:submit="save" class="needs-validation" novalidate>


                    <div class="row g-3 justify-content-center">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Name</label>
                            <input type="text" placeholder="John Doe" required wire:model.live.debounce.500ms="name">
                            @error('name')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Email</label>
                            <input type="email" placeholder="Leave blank if you want it unchanged" wire:model.live.debounce.500ms="email" inputmode="email">
                            @error('email')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6 d-flex gap-2">
                            <label class="form-label fw-semibold text-dark small mb-2">Status:</label>
                            <p>{{ $status }}</p>
                        </div>
                        
                        @can('customer')
                            @island
                                <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold text-dark small mb-2">Categories</label>
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

                                @error('categories')
                                    <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                                @enderror
                                </div>
                            @endisland
                        @endcan


                        <div class="col-12 col-sm-7 text-center mt-4">
                            <div class="row d-flex justify-content-center">
                                <div class="col-12 col-sm-6 col-lg-4">
                                    <button type="submit" class="fill-btn border-0" wire:loading.attr="disabled">                        
                                            <span class="fill-btn-inner" wire:loading.remove wire:target="save">
                                                <span class="fill-btn-normal">Update Profile</span>
                                                <span class="fill-btn-hover">Update Profile</span>
                                            </span>

                                            <span class="fill-btn-inner" wire:loading wire:target="save">
                                                <span class="fill-btn-normal">Updating...</span>
                                                <span class="fill-btn-hover">Updating...</span>
                                            </span>
                                    </button>
                                </div>

                                <div class="col-12 col-sm-6 col-lg-4 mt-4 mt-sm-0">
                                    <a href="{{ route('change-password') }}" class="fill-btn border-0" wire:navigate>
                                        <span class="fill-btn-inner">
                                            <span class="fill-btn-normal">Change Password</span>
                                            <span class="fill-btn-hover">Change Password</span>
                                        </span>
                                    </a>
                                </div>
                                
                                <div class="col-12 col-sm-6 col-lg-4 mt-4 mt-lg-0">
                                    <button type="button" class="fill-btn-red" wire:click="showDeleteAccountModal" wire:confirm="Are you sure you want to delete your account?? This is a permanent action.">
                                        <span class="fill-btn-inner">
                                            <span class="fill-btn-normal">Delete Account</span>
                                            <span class="fill-btn-hover">Delete Account</span>
                                        </span>
                                    </button>
                                </div>
                            </div> 
                       </div>
   

                           
                       


                    </div>

                </form>

            </div>
        </div>

    <div class="store-info-overlay {{ $showModal ? '' : 'd-none' }}" wire:loading.class.remove="d-none" wire:target="showDeleteAccountModal" wire:transition>
        <div class="store-info-panel">
            <div class="store-info-panel-header">
                <h5 class="store-info-panel-title">Delete Account</h5>
                <button type="button" class="store-info-close" wire:click="$set('showModal', false)" wire:loading.attr="disabled">×</button>
            </div>
            <div class="store-info-panel-body">
                <div class="store-info-card">
                    @if ($showModal)
                        <livewire:delete-account />
                    
                    @else
                        <div class="store-info-loading">Loading...</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

</div>