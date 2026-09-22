<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    #[Title('Edit a Category')]

    public Category $category;
    public string $name = '';
    public ?int $parent_id = null;
    public string $categoryTerm = '';
    public string $selectedCategoryTerm ='';
    public int $categoryLoadAmount = 3;
    public bool $removeAlert = false;


    public function mount($slug){
        $category = Category::with('parent')->where('slug' , $slug)->firstOrFail();
        $this->category = $category;
        if($category->parent_id){
            $this->parent_id = $category->parent_id;
            $this->selectedCategoryTerm = $category->parent->name;
        }
    }

    public function loadMoreCategories(){
        $this->categoryLoadAmount+=3;
    }

    public function updated($property)
    {
        $this->validateOnly($property, $this->rules());
    }

    public function setCategory(Category $category){
        $this->parent_id = $category->id;
        $this->selectedCategoryTerm = $category->name;
        $this->categoryTerm = '';
    }

    public function removeCategory()
    {
        $this->parent_id = null;
        $this->selectedCategoryTerm = '';
    }

    public function with(){
        $categories = collect();
        $categoriesTotal = 0;

        if(filled($this->categoryTerm)){
            $query = Category::select(['id','name'])->where('name', 'LIKE', '%' . trim($this->categoryTerm) . '%')->mainCategory();
            $categoriesTotal = $query->count();
            $categories = $query->take($this->categoryLoadAmount)->orderBy('name')->get();
        }

        return compact(
            'categories', 'categoriesTotal',
        );
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'nullable',
                'string',
                'min:3',
                'max:50',
                'unique:categories,name',
                'regex:/^[^<>]*$/',
            ],

            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id'
            ],
    ];
    }

    public function save(){
        // Authentication check
        if(!Auth::check()){
            $this->redirect(route('login'));
        }

        // Authorization check
        else if (!auth()->user()->can('super-admin')) {
            abort(403);
        }

       // Rate limiting 
       $key = 'update-category:' . request()->ip();
       
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError(
                'update',
                "Too many attempts. Please wait {$seconds} seconds before trying again."
            );
        return;
       }

        // Allow five requests every five minutes from each IP address.
        RateLimiter::hit($key, 60 * 5);

        // Validation
        $this->validate($this->rules());

        return DB::transaction(function () {
            if(filled($this->name)){
                $this->category->name = $this->name;
            }

            if($this->parent_id){
                $this->category->parent_id = $this->parent_id; 
            }
            $this->category->save();
            session()->flash('success','Category updated successfully');
            return $this->redirect(route('categories'), navigate:true);
        });
    }
    
};
?>
<div class="dashboard-content">
        @php
            $rateLimitErrors = collect($errors->messages())
                ->except(['name', 'parent_id'])
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
            <h5 class="fw-bold text-dark mb-1">Update Category</h5>
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

                       <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark small mb-2">Parent Category (Leave blank if there is none)</label>
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
                                @if($parent_id)
                                    <div class="social-badge" wire:transition>
                                        <div class="platform">
                                            <div class="username">{{ $selectedCategoryTerm }}</div>
                                        </div>
                                        <button type="button" class="remove-btn" aria-label="Remove {{ $selectedCategoryTerm }}" wire:click="removeCategory" wire:loading.attr="disabled">
                                            <span wire:loading.remove wire:target="removeCategory">&times;</span>
                                            <span wire:loading wire:target="removeCategory">...</span>
                                        </button>
                                    </div>
                                @endif
                            </div>

                            @error('parent_id')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-sm-7 text-center mt-4">
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <button type="submit" class="fill-btn border-0" wire:loading.attr="disabled">                        
                                            <span class="fill-btn-inner" wire:loading.remove wire:target="save">
                                                <span class="fill-btn-normal">Update Category</span>
                                                <span class="fill-btn-hover">Update Category</span>
                                            </span>

                                            <span class="fill-btn-inner" wire:loading wire:target="save">
                                                <span class="fill-btn-normal">Updating...</span>
                                                <span class="fill-btn-hover">Updating...</span>
                                            </span>
                                    </button>
                                </div>
                                <div class="col-12 col-sm-6 mt-4 mt-sm-0">
                                    <a href="{{ route('categories') }}" class="fill-btn-red" wire:navigate>
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