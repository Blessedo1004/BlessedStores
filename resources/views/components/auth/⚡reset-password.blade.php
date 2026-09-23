<?php

use Livewire\Component;
use Livewire\Attributes\Validate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetEmail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\RateLimiter;

new class extends Component
{
    #[Layout('components.layouts.auth')] 
    #[Title('Reset Password')]

    #[Validate('required|email')]
    public $email;

    #[Validate('required|string|size:60')]
    public $token;

    public string $password = '';

    public string $password_confirmation = '';

    public $showPassword = false;
    
    public $showConfirmPassword = false;

    public bool $removeAlert = false;

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
        if ($property !== 'removeAlert') {
            $this->removeAlert = false;
        }

        $this->validateOnly($property, $this->rules(), $this->messages);
        if ($property === 'password_confirmation' || ($property === 'password' && $this->password_confirmation !== '')) {
            $this->validateOnly('password_confirmation', $this->rules(), $this->messages);
        }
    }

    protected function rules(): array
    {
        return [
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
        ];
    }    



    public function mount(){
        $email = session('email');
        $token = Cache::get("forgot-password-token-{$email}");
        $this->email = $email;
        $this->token = $token;

        if(!$email || !$token){
            $this->redirect(route('login'));
        }

    }

    public function resetPassword (){
        $this->removeAlert = false;
        $key = 'reset-password-key:' . $this->email . ':' . $this->token;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError(
                'email',
                "Too many requests. Please wait {$seconds} seconds before trying again."
            );

            return;
        }

        // Allow only five attempts every 60 seconds.
        RateLimiter::hit($key, 60);

        $this->validate();

        $cachedToken = Cache::get("forgot-password-token-{$this->email}");

        if (!$cachedToken || $cachedToken !== $this->token) {
            session()->flash('failure', 'Reset password failed. Please try again.');
            $this->redirect(route('login'));
        }

        $user = User::where('email', $this->email)->first();

        if (!$user) {
            $this->addError('email', 'The provided email does not exist in our records.');
            return;
        }

        $user->password = Hash::make($this->password);
        $user->save();

        // Clear the cached token after successful password reset
        Cache::forget("forgot-password-token-{$this->email}");
        Cache::forget("forgot-password-email-{$this->token}");
        Mail::to($this->email)->send(new PasswordResetEmail());
        session()->flash('success', 'Your password has been reset successfully. You can now log in with your new password.');
        $this->redirect(route('login'));
    }
};
?>

<div class="w-100" style="max-width: 500px;">
        @php
            $nonPasswordErrors = collect($errors->messages())
                ->except(['password', 'password_confirmation'])
                ->flatten();
        @endphp

            @foreach ($nonPasswordErrors as $error)
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

        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center position-fixed">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0 mt-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{session('success')}}</span>
            </div>
            
        @endif

        <!-- Main Reset Password Card -->
        <div class="card border-0 shadow-sm rounded-4 auth-card">
            <div class="card-body p-4 p-md-5">
                
                <!-- Brand Info Header -->
                <div class="text-center mb-4">
                    <a href="{{ route('home') }}">
                        <img src="{{ asset('imgs/logo/logo.png') }}" alt="logo" class="mb-3 logo">
                    </a>
                    <h1 class="h4 fw-bold text-dark mb-1" style="font-family: 'Sora', sans-serif;">Reset Password</h1>
                    <p class="text-muted small mb-0">Enter your new password below</p>
                </div>

                <!-- Reset Password Form -->
                <form class="needs-validation" wire:submit="resetPassword">
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
                                    <svg id="eyeOpenIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" wire:click="$set('showPassword', true)">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    @else
                                    
                                    <!-- Eye-Slash Icon SVG -->
                                    <svg id="eyeClosedIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" wire:click="$set('showPassword', false)">
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
                                    <svg id="eyeOpenIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" wire:click="$set('showConfirmPassword', true)">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                @else
                                    <!-- Eye-Slash Icon SVG -->
                                    <svg id="eyeClosedIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" wire:click="$set('showConfirmPassword', false)">
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
                    <button type="submit" class="fill-btn w-100 border-0" wire:loading.attr="disabled">
                        <span class="fill-btn-inner" wire:loading.remove wire:target="resetPassword">
                            <span class="fill-btn-normal">Reset Password</span>
                            <span class="fill-btn-hover">Reset Password</span>
                        </span>
                        <span class="fill-btn-inner" wire:loading wire:target="resetPassword">
                            <span>Resetting...</span>
                        </span>
                    </button>
                </form>

            </div>
        </div>
    </div>
