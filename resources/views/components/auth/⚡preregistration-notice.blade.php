<?php

use Livewire\Component;
use Livewire\Attributes\Validate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\PreRegistrationEmail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new class extends Component
{
    #[Layout('components.layouts.auth')] 
    #[Title('Preregistration Notice')]

    #[Validate('required|email')]
    public $email;

    #[Validate('required|string|size:6')]
    public $code;

    public function mount(){
        $this->email = session('email');
        if (!$this->email) {
            return $this->redirect(route('home'), navigate: true);
        } 
    }


    public function resendCode()
    {
        $oldCode = Cache::get("preregistration-email-code-{$this->email}");
        $userData = Cache::get("preregistration-email-for-{$oldCode}");

        if($oldCode && $userData){
            Cache::forget("preregistration-email-code-{$this->email}");
            Cache::forget("preregistration-email-for-{$oldCode}");
        }
        
        $newCode = Str::random(6);
        Cache::put("preregistration-email-for-{$newCode}", $userData, 15 * 60);
        Cache::put("preregistration-email-code-{$this->email}", $newCode, 15 * 60);
        Mail::to($this->email)->send(new PreRegistrationEmail($newCode));
        session()->flash('success', 'A new verification code has been sent to your inbox!');
    }  
    
    public function verifyCode(){
        $userData = Cache::get("preregistration-email-for-{$this->code}");

        if(!$userData || $userData['email'] !== $this->email){
            $this->addError('code', 'The verification code is invalid or has expired.');
            return;
        }

        $user = new User();   
        $user->name = $userData['name'];
        $user->email = $userData['email'];
        $user->password = Hash::make($userData['password']);
        $user->save();
        session()->flash('signup-success', 'Account successfully created. You can now sign in');
        $this->redirect(route('login'), navigate:true);
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

                <!-- Login Form -->
                <form class="needs-validation" wire:submit="verifyCode">
        
                    <!-- Code Input -->
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="code" class="form-label fw-semibold text-dark small mb-0">Verification Code</label>
                                <p wire:click="resendCode" class="text-color-1 text-decoration-none small fw-bold" wire:loading.attr="disabled" style="cursor: pointer;">
                                    <span wire:loading.remove wire:target="resendCode">Resend Code?</span>
                                    <span wire:loading wire:target="resendCode">Resending...</span>
                                </p>
                           
                        </div>
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
