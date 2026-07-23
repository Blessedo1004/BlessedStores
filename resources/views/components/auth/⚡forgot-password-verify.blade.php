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

    #[Validate('required|string|size:6')]
    public $code;

    public $countdown = 0;

    public function mount()
    {
        $this->email = session('email');
        if (!$this->email) {
            return $this->redirect(route('login'));
        }

        $key = 'resend-code:' . $this->email;

        $this->countdown = RateLimiter::availableIn($key);
    }


    public function resendCode()
    {
        $key = 'resend-code:' . $this->email;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            $this->countdown = $seconds;

            $this->addError(
                'code',
                "Please wait {$seconds} seconds before requesting another code."
            );

            return;
        }

        // Allow only 1 resend every 60 seconds
        RateLimiter::hit($key, 60);

        $oldCode = Cache::get("forgot-password-email-code-{$this->email}");
        $email= Cache::get("forgot-password-email-for-{$oldCode}");

        if ($oldCode && $email) {
            Cache::forget("forgot-password-email-code-{$this->email}");
            Cache::forget("forgot-password-email-for-{$oldCode}");
        }

        $newCode = Str::random(6);

        Cache::put("forgot-password-email-for-{$newCode}", $this->email, 15 * 60);
        Cache::put("forgot-password-email-code-{$this->email}", $newCode, 15 * 60);

        Mail::to($this->email)->send(new ForgotPasswordEmail($newCode));

        $this->countdown = RateLimiter::availableIn($key);
        $this->dispatch('resend-countdown', seconds: $this->countdown);

        session()->flash('success', 'A new verification code has been sent.');
    }
    
    public function verifyCode()
    {
        $key = 'verify-code:' . $this->email;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError(
                'code',
                "Too many verification attempts. Please wait {$seconds} seconds and try again."
            );

            return;
        }

        // Allow five verification attempts every 60 seconds for each email.
        RateLimiter::hit($key, 60);

        $this->validate();
        $email = Cache::get("forgot-password-email-for-{$this->code}");

        if(!$email || $email !== $this->email){
            $this->addError('code', 'The verification code is invalid or has expired.');
            return;
        }

        Cache::forget("forgot-password-email-for-{$this->code}");
        Cache::forget("forgot-password-email-code-{$this->email}");

        $token = Str::random(60);
        Cache::put("forgot-password-token-{$this->email}", $token, 2 * 60);
        Cache::put("forgot-password-email-{$token}", $this->email, 2 * 60);
        RateLimiter::clear($key);
        // session()->flash('forgot-password-token', $token);
        session()->flash('success', 'Email verified successfully. You can now reset your password.');
        session()->flash('email', $this->email);
        $this->redirect(route('reset-password'), navigate: true);
    }
};
?>

<div class="w-100" style="max-width: 500px;">
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

        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center position-fixed">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0 mt-0.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{session('success')}}</span>
            </div>
            
        @endif        


        <!-- Main Preregistration Card -->
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">
                
                <!-- Brand Info Header -->
                <div class="text-center mb-4">
                    <a href="{{ route('home') }}">
                        <img src="{{ asset('imgs/logo/logo.png') }}" alt="logo" class="mb-3 logo">
                    </a>
                    <h1 class="h4 fw-bold text-dark mb-1" style="font-family: 'Sora', sans-serif;">A code has been sent to your email. Please check your inbox or spam folder and input the code below.</h1>
                </div>

                <!-- Verify Code Form -->
                <form class="needs-validation" wire:submit="verifyCode">
        
                    <!-- Resend code button -->
                    <div
                        class="d-flex justify-content-between align-items-center mb-2 row"
                        x-data="resendCooldown({{ $countdown }})"
                        @resend-countdown.window="restart($event.detail.seconds)"
                    >
                        <label for="code" class="form-label fw-semibold text-dark small mb-0 col-6">
                            Verification Code
                        </label>

                        <button
                            type="button"
                            class="fill-btn border-0 col-6"
                            @click="send()"
                            :disabled="remaining > 0 || sending"
                            style="cursor:pointer;"
                        >
                            <span x-show="!sending" class="fill-btn-inner">
                                <span x-text="remaining > 0 ? `Resend (${remaining}s)` : 'Resend Code'"></span>
                            </span>
                            <span x-cloak x-show="sending">
                                <span>Resending...</span>
                            </span>
                        </button>
                    </div>
                        <!-- Code Input -->
                        <input id="code" type="text" placeholder="Enter verification code" required wire:model="code" class="mb-3">
                           

                    <!-- Action Button -->
                    <button type="submit" class="fill-btn w-100 border-0" wire:loading.attr="disabled">
                        <span class="fill-btn-inner" wire:loading.remove wire:target="verifyCode">
                            <span class="fill-btn-normal">Verify Code</span>
                            <span class="fill-btn-hover">Verify Code</span>
                        </span>
                        <span class="fill-btn-inner" wire:loading wire:target="verifyCode">
                            <span>Verifying...</span>
                        </span>
                    </button>
                </form>

            </div>
        </div>
</div>    
