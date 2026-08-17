<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\StorePreApplicationEmail;

new class extends Component
{
    use WithFileUploads;
   #[Layout('components.layouts.auth')] 
    #[Title('Store Application')]

    public string $owner_name = '';
    public string $email = '';
    public string $store_name = '';
    public string $phone_number = '';
    public $logo;
    public string $description = '';
    public string $address = '';
    public string $platform = '';
    public string $user_name = '';
    public array $social_media = [];

    public function updated($property)
    {
        $this->validateOnly($property, $this->rules());
    }

    protected function rules(): array
    {
        return [
            'owner_name' => [
                'required',
                'string',
                'min:3',
                'max:20',
                'regex:/^[^<>]*$/',
            ],

            'email' => [
                'required',
                'email',
                'unique:users,email',
            ],
            'store_name' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'unique:stores,name',
                'regex:/^[^<>]*$/',
            ],
            'phone_number' => [
                'required',
                'string',
                'size:11',
                'regex:/^0[0-9]{10}$/'
            ],
             'logo' => [
                'required',
                'image',
                'max:2048'
            ],
            'description' => [
                'required',
                'string',
                'min:10',
                'max:100',
                'regex:/^[^<>]*$/',
            ],
            'address' => [
                'required',
                'string',
                'min:10',
                'max:100',
                'regex:/^[^<>]*$/',
            ],
            'social_media' => [
                'required',
                'array',
                'min:1',
                'max:5'
            ],

    ];
    }

    public function addSocialMedia()
    {
        if (count($this->social_media) >= 5) {
            $this->addError('social_media', 'You may only add up to 5 social media information.');
            return;
        }

        // Validate the platform and user_name fields
        $this->validate([
            'platform' => 'required|string',
            'user_name' => 'required|string',
        ]);

        // Add the social media platform and user_name to the social_media array
        $this->social_media[] = [
            'platform' => $this->platform,
            'user_name' => $this->user_name,
        ];

        $this->platform = '';
        $this->user_name = '';
    }

    public function removeSocialMedia($index)
    {
        // Remove the social media entry at the specified index
        unset($this->social_media[$index]);
        // Re-index the array to maintain proper indices
        $this->social_media = array_values($this->social_media);
    }

    public function verify(){
       // Rate limiting 
       $key = 'submit-application:' . request()->ip();
       
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

        $userData = $this->validate($this->rules());

        $userData['logo'] = $this->logo->store('logos', 'public');

        $existingCode = Cache::get("preapplication-email-code-{$this->email}");

        if($existingCode){
            Cache::forget("preapplication-email-for-{$existingCode}");
            Cache::forget("preapplication-email-code-{$this->email}");
       }

        $code = Str::random(6);
        Cache::put("preapplication-email-for-{$code}", $userData, 15 * 60);
        Cache::put("preapplication-email-code-{$this->email}", $code, 15 * 60);
        Mail::to($this->email)->send(new StorePreApplicationEmail($code));
        RateLimiter::hit('resend-preapplication-code:' . $this->email, 60);
        session()->flash('email', $this->email);
        $this->redirect(route('store-preapplication-notice'), navigate: true);
    }

    public function mount()
    {
        if (Auth::check()) {
            redirect()->route('dashboard');
        }
    }
}
?>

<div class="w-100" style="max-width: 500px;">
        @php
            $rateLimitErrors = collect($errors->messages())
                ->except(['owner_name', 'email', 'store_name', 'phone_number', 'logo', 'description', 'address', 'social_media', 'platform', 'user_name'])
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

        @if(session('failure'))
            <div class="alert alert-danger border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center position-fixed">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0 mt-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{session('failure')}}</span>
            </div>
            
        @endif

         <!-- Main Signup Card -->
        <div class="card border-0 shadow-sm rounded-4 auth-card">
            <div class="card-body p-4 p-md-5">
                
                <!-- Brand Info Header -->
                <div class="text-center mb-4">
                    <a href="{{ route('home') }}">
                        <img src="{{ asset('imgs/logo/logo.png') }}" alt="logo" class="mb-3 logo">
                    </a>
                    <h1 class="h4 fw-bold text-dark mb-1" style="font-family: 'Sora', sans-serif;">Store Application</h1>
                    <p class="text-muted small mb-0">Fill in the details below to send an application.</p>
                </div>

                <!-- Signup Form -->
                <form class="needs-validation" wire:submit="verify" novalidate>
            
                    <!-- Owner Name -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small mb-2">Owner Name</label>
                        <input type="text" placeholder="John Doe" required wire:model.live.debounce.500ms="owner_name" autofocus>
                        @error('owner_name')
                            <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Store Name -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small mb-2">Store Name</label>
                        <input type="text" placeholder="My Awesome Store" required wire:model.live.debounce.500ms="store_name">
                        @error('store_name')
                            <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small mb-2">Contact Email</label>
                        <input type="email" placeholder="owner@example.com" required wire:model.live.debounce.500ms="email" inputmode="email">
                        @error('email')
                            <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Phone Number-->
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small mb-2">Phone Number</label>
                        <input type="tel" placeholder="08012345678" wire:model.live.debounce.500ms="phone_number" minlength="11" maxlength="11" inputmode="numeric">
                        @error('phone_number')
                            <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small mb-2">Logo</label>
                        <input type="file" wire:model="logo" accept="image/*" required>
                        @error('logo')
                            <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                        @enderror
                        @if($logo)
                            <div class="mt-2" wire:transition>
                                <img src="{{ $logo->temporaryUrl() }}" alt="Logo Preview" class="rounded" style="height: 80px; max-width: 100%;">
                            </div>
                        @endif

                        <div class="mt-2" wire:loading wire:target="logo">
                                Uploading...
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small mb-2">Description</label>
                        <textarea rows="3" placeholder="Short description about the store" wire:model.live.debounce.500ms="description"></textarea>
                        @error('description')
                            <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark small mb-2">Address</label>
                        <textarea rows="3" placeholder="Store address" wire:model.live.debounce.500ms="address"></textarea>
                        @error('address')
                            <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                        @enderror
                    </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small mb-2">Social Media Platform</label>
                            <select id="social_platform"  wire:model="platform">
                                <option value="">Select platform</option>
                                <option value="x">X</option>
                                <option value="instagram">Instagram</option>
                                <option value="youtube">YouTube</option>
                                <option value="tiktok">TikTok</option>
                                <option value="facebook">Facebook</option>
                                <option value="linkedin">LinkedIn</option>
                            </select>
                            @error('platform')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small mb-2">Social Media Username</label>
                            <div class="d-flex gap-2 align-items-center">
                                <input type="text" id="social_username" placeholder="yourusername" wire:model="user_name">
                                <button type="button" id="addSocialBtn" class="btn btn-outline-secondary rounded-pill px-3" wire:click="addSocialMedia" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="addSocialMedia">Add</span>  
                                    <span wire:loading wire:target="addSocialMedia">Adding...</span> 
                                </button>
                            </div>
                            @error('user_name')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror 
                        </div>

                            <div class="mb-3">
                                <div id="social-badges" class="d-flex flex-wrap gap-2 mt-3 justify-content-center">
                                    @foreach ($this->social_media as $social)
                                        <div class="social-badge" wire:key="social-{{ $loop->index }}" wire:transition>
                                            <div class="platform">
                                                <div class="platform-name">{{ $social['platform'] }}</div>
                                                <div class="username">{{ $social['user_name'] }}</div>
                                            </div>
                                        <button type="button" class="remove-btn" aria-label="Remove" wire:click="removeSocialMedia({{ $loop->index }})" wire:loading.attr="disabled">
                                           <span wire:loading.remove wire:target="removeSocialMedia({{ $loop->index }})">
                                                &times;
                                           </span>
                                           <span wire:loading wire:target="removeSocialMedia({{ $loop->index }})">
                                                ...
                                           </span>
                                        </button>
                                    </div>
                                    @endforeach
                                </div>
                                @error('social_media')
                                    <div class="invalid-feedback mt-1 d-block text-center">{{ $message }}</div>
                                @enderror
                            </div>

                    <!-- Action Button -->
                    <button type="submit" class="fill-btn w-100 border-0" wire:loading.attr="disabled">
                        <span class="fill-btn-inner" wire:loading.remove wire:target="verify">
                            <span class="fill-btn-normal">Send Application</span>
                            <span class="fill-btn-hover">Send Application</span>
                        </span>
                        <span class="fill-btn-inner" wire:loading wire:target="verify">
                            <span>Please wait...</span>
                        </span>
                    </button>
                </form>

                <!-- Signup Redirect Footer -->
                <div class="text-center mt-4 small text-muted">
                        <span>Already have an account? <a href="{{ route('login') }}" class="text-color-1 text-decoration-none fw-bold">Sign in</a></span>
                </div>

                <div class="text-center mt-4 small text-muted">
                        <span>Want to sign up as a customer? <a href="{{ route('signUp') }}" class="text-color-1 text-decoration-none fw-bold" wire:navigate>Sign Up</a></span>
                </div>

            </div>
        </div>
    </div>
