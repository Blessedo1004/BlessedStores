<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Mail\PreRegistrationEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\Category;
use Illuminate\Support\Str;

new class extends Component
{
   #[Layout('components.layouts.auth')] 
   #[Title('Sign Up')]

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public $showPassword = false;
    
    public $showConfirmPassword = false;

    public int $step = 1;

    public string $categoryTerm ='';
    public array $selectedCategories = [];
    public array $selectedCategoryTerms = [];
    public int $categoryLoadAmount = 3;

    // Custom messages only for password fields
    protected $messages = [
        'password.required' => 'Password is required',
        'password.min' => 'Password must be at least 8 characters',
        'password.regex' => 'Password must be a minimum of 8 characters, with at least one uppercase and lowercase letter, number and special character',
        'password.confirmed' => 'Passwords do not match',
        'password_confirmation.required' => 'Please confirm your password',
        'password_confirmation.same' => 'Passwords do not match',
    ];

    public function updated($property)
    {
        $this->validateOnly($property, $this->rules(), $this->messages);
        if ($property === 'password_confirmation' || ($property === 'password' && $this->password_confirmation !== '')) {
            $this->validateOnly('password_confirmation', $this->rules(), $this->messages);
        }
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
                'required',
                'email',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*#?&]/',
                'confirmed',
            ],
            'password_confirmation' => [
                'required',
                'same:password',
            ],
            'selectedCategories' => [
            'nullable',
            'array',
            'max:5',
            ]
        ];
    }

    public function next(){
        $this->validate($this->rules(), $this->messages);
        $this->step = 2;

    }

    public function signup()
    {
       // Rate limiting 
       $key = 'signup:' . request()->ip();
       
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError(
                'create',
                "Too many requests. Please wait {$seconds} seconds before trying again."
            );
        return;
       }

        // Allow five requests every five minutes from each IP address.
        RateLimiter::hit($key, 60 * 5);

        $userData = $this->validate($this->rules(), $this->messages);

        $existingCode = Cache::get("preregistration-email-code-{$this->email}");

        if($existingCode){
            Cache::forget("preregistration-email-for-{$existingCode}");
            Cache::forget("preregistration-email-code-{$this->email}");
       }

        $code = Str::random(6);
        Cache::put("preregistration-email-for-{$code}", $userData, 15 * 60);
        Cache::put("preregistration-email-code-{$this->email}", $code, 15 * 60);
        Mail::to($this->email)->send(new PreRegistrationEmail($code));
        RateLimiter::hit('resend-code:' . $this->email, 60);
        session()->flash('email', $this->email);
        $this->redirect(route('preregistration-notice'), navigate: true);
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

    public function mount()
    {
        if (Auth::check()) {
            redirect()->route('dashboard');
        }
    }

    public function with(){
        $categories = collect();
        $categoriesTotal = 0;

        if(filled($this->categoryTerm)){
            $query = Category::select(['id','name'])->where('name', 'LIKE', '%' . trim($this->categoryTerm) . '%');
            $categoriesTotal = $query->count();
            $categories = $query->take($this->categoryLoadAmount)->orderBy('parent_id')->orderBy('name', 'asc')->get();
        }

        return compact(
            'categories', 'categoriesTotal',
        );
    }
}
?>

<div class="w-100" style="max-width: 500px;">
        @php
            $rateLimitErrors = collect($errors->messages())
                ->except(['name','email', 'password', 'password_confirmation', 'selectedCategories'])
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

         <!-- Main Signup Card -->
        <div class="card border-0 shadow-sm rounded-4 auth-card">
            <div class="card-body p-4 p-md-5">
                
                <!-- Brand Info Header -->
                <div class="text-center mb-4">
                    <a href="{{ route('home') }}">
                        <img src="{{ asset('imgs/logo/logo.png') }}" alt="logo" class="mb-3 logo">
                    </a>
                    <h1 class="h4 fw-bold text-dark mb-1" style="font-family: 'Sora', sans-serif;">Create an Account</h1>
                    <p class="text-muted small mb-0">{{ $step === 1 ? 'Fill in the details below to get started' : 'Select product categories you would like to follow (Optional)' }}</p>
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <p class="step {{ $step === 1 ? 'active' : ''}}">1</p>
                        <p class="step {{ $step === 2 ? 'active' : ''}}">2</p>
                    </div>
                </div>

                <!-- Signup Form -->
                <form class="needs-validation" wire:submit="signup">
                    <div class="{{ $step === 2 ? 'd-none'  : ''}}" wire:transition>
                        <!-- Name Input -->
                        <div class="mb-3">
                            <label for="name" class="form-label fw-semibold text-dark small mb-2">Full Name</label>
                            <input id="name" type="text" placeholder="John Doe" class="@error('name') is-invalid @enderror" required autofocus wire:model.live.debounce.500ms="name">
                            @error('name')
                                <div class="invalid-feedback mt-1 ">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Email Input -->
                        <div class="mb-3">
                            <label for="email" class="form-label fw-semibold text-dark small mb-2">Email Address</label>
                            <input id="email" type="email" placeholder="name@example.com" class="@error('email') is-invalid @enderror" required wire:model.live.debounce.500ms="email">
                            @error('email')
                                <div class="invalid-feedback mt-1 ">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Password Input -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2 flex-column">
                                <label for="password" class="form-label fw-semibold text-dark small mb-0">Password</label>
                                <div class="text-muted small">Minimum of 8 characters with at least one uppercase letter, one lowercase letter,one number and one special character</div>
                            </div>
                            <div class="position-relative">
                                <input id="password" name="password" type="{{ $showPassword ? 'text' : 'password' }}" placeholder="••••••••" class="@error('password') is-invalid @enderror" required style="padding-right: 50px;" wire:model.live.debounce.500ms="password">
                                <button class="position-absolute end-0 top-50 translate-middle-y border-0 bg-transparent pe-4 text-muted" type="button" id="togglePasswordBtn" style="height: 100%; display: flex; align-items: center; z-index: 10;" aria-label="Toggle Password Visibility">
                                    <!-- Eye Icon SVG (Visible by default) -->
                                    @if(!$showPassword)
                                        <svg id="eyeOpenIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" wire:click="$set('showPassword', true)" wire:loading.attr="disabled">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        @else

                                        <!-- Eye-Slash Icon SVG -->
                                        <svg id="eyeClosedIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" wire:click="$set('showPassword', false)" wire:loading.attr="disabled">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>  
                                    @endif  
                                </button>
                            </div>

                            @php
                                $passwordHasMinLength = strlen($password) >= 8;
                                $passwordHasLowercase = (bool) preg_match('/[a-z]/', $password);
                                $passwordHasUppercase = (bool) preg_match('/[A-Z]/', $password);
                                $passwordHasNumber = (bool) preg_match('/[0-9]/', $password);
                                $passwordHasSymbol = (bool) preg_match('/[@$!%*#?&]/', $password);
                            @endphp
                            <div class="mt-3 px-3 py-2 rounded-3 border border-1 border-muted bg-light">
                                <div class="small text-muted mb-2">Password must contain:</div>
                                <div class="d-flex align-items-center gap-2 small mb-1 {{ $passwordHasMinLength ? 'text-success' : 'text-muted' }}">
                                    <span style="width: 18px; display:inline-flex; justify-content:center; align-items:center;">{{ $passwordHasMinLength ? '✓' : '' }}</span>
                                    <span>At least 8 characters</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 small mb-1 {{ $passwordHasLowercase ? 'text-success' : 'text-muted' }}">
                                    <span style="width: 18px; display:inline-flex; justify-content:center; align-items:center;">{{ $passwordHasLowercase ? '✓' : '' }}</span>
                                    <span>Lowercase letter</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 small mb-1 {{ $passwordHasUppercase ? 'text-success' : 'text-muted' }}">
                                    <span style="width: 18px; display:inline-flex; justify-content:center; align-items:center;">{{ $passwordHasUppercase ? '✓' : '' }}</span>
                                    <span>Uppercase letter</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 small mb-1 {{ $passwordHasNumber ? 'text-success' : 'text-muted' }}">
                                    <span style="width: 18px; display:inline-flex; justify-content:center; align-items:center;">{{ $passwordHasNumber ? '✓' : '' }}</span>
                                    <span>Number</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 small mb-0 {{ $passwordHasSymbol ? 'text-success' : 'text-muted' }}">
                                    <span style="width: 18px; display:inline-flex; justify-content:center; align-items:center;">{{ $passwordHasSymbol ? '✓' : '' }}</span>
                                    <span>Special character (@$!%*#?&)</span>
                                </div>
                            </div>
                        </div>

                        <!-- Password Confirmation Input -->
                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label fw-semibold text-dark small mb-0">Confirm Password</label>
                            <div class="position-relative">
                                <input id="password_confirmation" type="{{ $showConfirmPassword ? 'text' : 'password' }}" placeholder="••••••••" class="@error('password_confirmation') is-invalid @enderror" required style="padding-right: 50px;" wire:model.live.debounce.500ms="password_confirmation">
                                <button class="position-absolute end-0 top-50 translate-middle-y border-0 bg-transparent pe-4 text-muted" type="button" id="togglePasswordBtn" style="height: 100%; display: flex; align-items: center; z-index: 10;" aria-label="Toggle Password Visibility">
                                    <!-- Eye Icon SVG (Visible by default) -->
                                    @if(!$showConfirmPassword)
                                        <svg id="eyeOpenIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" wire:click="$set('showConfirmPassword', true)" wire:loading.attr="disabled">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    @else
                                        <!-- Eye-Slash Icon SVG -->
                                        <svg id="eyeClosedIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" wire:click="$set('showConfirmPassword', false)" wire:loading.attr="disabled">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                        </svg>  
                                    @endif
                                </button>
                            </div>
                            @error('password_confirmation')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Action Button -->
                        <button type="button" class="fill-btn w-100 border-0" wire:loading.attr="disabled" wire:click="next">
                            <span class="fill-btn-inner" wire:loading.remove wire:target="next">
                                <span class="fill-btn-normal">Next Step</span>
                                <span class="fill-btn-hover">Next Step</span>
                            </span>
                            <span class="fill-btn-inner" wire:loading wire:target="next">
                                <span>Please wait...</span>
                            </span>
                        </button>
                    </div>

                    <div class="{{ $step === 1 ? 'd-none'  : ''}}" wire:transition>
                        <div class="col-12">
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

                            @error('categories')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>
                        <!-- Action Button -->
                        <button type="submit" class="fill-btn w-100 border-0 mt-4" wire:loading.attr="disabled">
                            <span class="fill-btn-inner" wire:loading.remove wire:target="signup">
                                <span class="fill-btn-normal">Sign Up</span>
                                <span class="fill-btn-hover">Sign Up</span>
                            </span>
                            <span class="fill-btn-inner" wire:loading wire:target="signup">
                                <span>Signing Up...</span>
                            </span>
                        </button>
                    </div>
                </form>

                <!-- Signup Redirect Footer -->
                <div class="text-center mt-4 small text-muted">
                        <span>Already have an account? <a href="{{ route('login') }}" class="text-color-1 text-decoration-none fw-bold">Sign in</a></span>
                </div>

                <div class="text-center mt-4 small text-muted">
                        <span>Want to register a store? <a href="{{ route('register-store') }}" class="text-color-1 text-decoration-none fw-bold" wire:navigate>Register Store</a></span>
                </div>

            </div>
        </div>
    </div>
