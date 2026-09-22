<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    #[Title('Change Password')]

    public User $user;

    public string $currentPassword = '';

    public string $password = '';

    public string $password_confirmation = '';

    public $showCurrentPassword = false;

    public $showPassword = false;
    
    public $showConfirmPassword = false;
    public bool $removeAlert = false;

    public function mount(){
        $this->user = auth()->user();
    }

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
            'currentPassword' => [
                'required',
                'string',
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

    ];
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
        $this->validate($this->rules(), $this->messages);

        if (!Hash::check($this->currentPassword , $this->user->password)) {
            return $this->addError('currentPassword' , 'Wrong Password');
        }
        
        $user = Auth::user();

        $user->password = Hash::make($this->password);
        $user->save();

        Auth::logoutOtherDevices($this->password);

        request()->session()->regenerate();
        request()->session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            Auth::guard()->hashPasswordForCookie($user->getAuthPassword())
        );
        session()->flash('success','Password updated successfully');
        $this->redirect(route('profile'), navigate:true);
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
                ->except(['currentPassword', 'password', 'password_confirmation'])
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
            <h5 class="fw-bold text-dark mb-1">Change Password</h5>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-md-5">

                <form wire:submit="save" class="needs-validation" novalidate>


                    <div class="row g-3 justify-content-center">

                    <div class="mb-3">
                        <label for="current-password" class="form-label fw-semibold text-dark small mb-0">Current Password</label>
                        <div class="position-relative">
                            <input id="current_password" type="{{ $showCurrentPassword ? 'text' : 'password' }}" placeholder="••••••••" class="@error('currentPassword') is-invalid @enderror" required style="padding-right: 50px;" wire:model="currentPassword">
                            <button class="position-absolute end-0 top-50 translate-middle-y border-0 bg-transparent pe-4 text-muted" type="button" id="togglePasswordBtn" style="height: 100%; display: flex; align-items: center; z-index: 10;" aria-label="Toggle Password Visibility">
                                <!-- Eye Icon SVG (Visible by default) -->
                                @if(!$showCurrentPassword)
                                    <svg id="eyeOpenIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" wire:click="$set('showCurrentPassword', true)" wire:loading.attr="disabled">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                @else
                                    <!-- Eye-Slash Icon SVG -->
                                    <svg id="eyeClosedIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" wire:click="$set('showCurrentPassword', false)" wire:loading.attr="disabled">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>  
                                @endif
                            </button>
                        </div>
                        @error('currentPassword')
                            <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <!-- Password Input -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2 flex-column">
                            <label for="password" class="form-label fw-semibold text-dark small mb-0">New Password</label>
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
                        <label for="password_confirmation" class="form-label fw-semibold text-dark small mb-0">Confirm New Password</label>
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

                        <div class="col-12 col-sm-7 text-center mt-4">
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <button type="submit" class="fill-btn border-0" wire:loading.attr="disabled">                        
                                            <span class="fill-btn-inner" wire:loading.remove wire:target="save">
                                                <span class="fill-btn-normal">Confirm</span>
                                                <span class="fill-btn-hover">Confirm</span>
                                            </span>

                                            <span class="fill-btn-inner" wire:loading wire:target="save">
                                                <span class="fill-btn-normal">Confirming...</span>
                                                <span class="fill-btn-hover">Confirming...</span>
                                            </span>
                                    </button>
                                </div>   
                            </div> 
                       </div>

                    </div>

                </form>

            </div>
        </div>

</div>
