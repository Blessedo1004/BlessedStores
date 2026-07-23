<?php

use Livewire\Component;
use Livewire\Attributes\Validate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\ForgotPasswordEmail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\RateLimiter;

new class extends Component
{
    #[Layout('components.layouts.auth')] 
    #[Title('Forgot Password')]

    #[Validate('required|email')]
    public $email;

    public function sendCode()
    {
        $this->validate();

        $key = 'forgot-password-send-code-ip:' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 2)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError(
                'email',
                "You can only request a verification code twice per minute. Please wait {$seconds} seconds before trying again."
            );

            return;
        }

        // Allow two code requests every 60 seconds from each IP address.
        RateLimiter::hit($key, 60);

        $emailExists = User::where('email', $this->email)->exists();
        if (!$emailExists){
            // $this->redirect(route('forgot-password-verify'), navigate: true);
            $this->addError('email', 'The provided email does not exist in our records.');
            return;
        }

        $lowerCaseEmail = strtolower($this->email);
        $oldCode = Cache::get("forgot-password-email-code-{$lowerCaseEmail}");

        if ($oldCode) {
            Cache::forget("forgot-password-email-code-{$lowerCaseEmail}");
            Cache::forget("forgot-password-email-for-{$oldCode}");
        }

        $newCode = Str::random(6);

        Cache::put("forgot-password-email-for-{$newCode}", $lowerCaseEmail, 15 * 60);
        Cache::put("forgot-password-email-code-{$lowerCaseEmail}", $newCode, 15 * 60);

        Mail::to($lowerCaseEmail)->send(new ForgotPasswordEmail($newCode));
        session()->flash('email', $this->email);
        RateLimiter::hit('resend-code:' . $this->email, 60);
        $this->redirect(route('forgot-password-verify'), navigate: true);
    }
    
};
?>

<div class="w-100" style="max-width: 500px;">
        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center" role="alert">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0 mt-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <ul class="mb-0 ps-2 list-unstyled">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Main Forgot Password Card -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">
                
                <!-- Brand Info Header -->
                <div class="text-center mb-4">
                    <a href="{{ route('home') }}">
                        <img src="{{ asset('imgs/logo/logo.png') }}" alt="logo" class="mb-3 logo">
                    </a>
                    <h1 class="h4 fw-bold text-dark mb-1" style="font-family: 'Sora', sans-serif;">Type in your registered email below.</h1>
                </div>

                <!-- Verify Code Form -->
                <form class="needs-validation" wire:submit="sendCode">
        
                    <!-- Resend code button -->
                    <div
                        class="d-flex justify-content-between align-items-center mb-2 row"
                    >
                        <label for="email" class="form-label fw-semibold text-dark small mb-0 col-6">
                            Email
                        </label>
                    </div>
                        <!-- Email Input -->
                        <input id="email" type="email" placeholder="Enter email" required wire:model="email" class="mb-3">
                           

                    <!-- Action Button -->
                    <button type="submit" class="fill-btn w-100 border-0" wire:loading.attr="disabled">
                        <span class="fill-btn-inner" wire:loading.remove wire:target="sendCode">
                            <span class="fill-btn-normal">Send Code</span>
                            <span class="fill-btn-hover">Send Code</span>
                        </span>
                        <span class="fill-btn-inner" wire:loading wire:target="sendCode">
                            <span>Please wait...</span>
                        </span>
                    </button>
                </form>
            </div>
        </div>
</div>    
