<?php

use Livewire\Component;
use Livewire\Attributes\Title;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\AdminRegistrationEmail;

new class extends Component
{
    #[Title('Add an Admin')]

    public string $name = '';
    public string $email = '';
    public bool $removeAlert = false;

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
                'required',
                'email',
                'unique:users,email',
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
       $key = 'create-admin:' . request()->ip();
       
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            $this->addError(
                'create',
                "Too many attempts. Please wait {$seconds} seconds before trying again."
            );
        return;
       }

        // Allow five requests every five minutes from each IP address.
        RateLimiter::hit($key, 60 * 5);

        // Validation
        $this->validate($this->rules());

        return DB::transaction(function () {
            $password = Str::random(8);
            $user = new User();
            $user->name = $this->name;
            $user->email = $this->email;
            $user->password = Hash::make($password);
            $user->role = 'admin';
            $user->save();

            Mail::to($this->email)->send(new AdminRegistrationEmail($password, $this->name));
            session()->flash('success','Admin registered successfully');
            return $this->redirect(route('admins'), navigate:true);
        });
    }
    
};
?>
<div class="dashboard-content">
        @php
            $rateLimitErrors = collect($errors->messages())
                ->except(['name', 'email'])
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
            <h5 class="fw-bold text-dark mb-1">Add New Admin</h5>
            <p class="text-muted small mb-0">Provide admin details to create a new admin.</p>
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
                            <label class="form-label fw-semibold text-dark small mb-2">Contact Email</label>
                            <input type="email" placeholder="owner@example.com" required wire:model.live.debounce.500ms="email" inputmode="email">
                            @error('email')
                                <div class="invalid-feedback mt-1 d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-sm-7 text-center mt-4">
                            <div class="row">
                                <div class="col-12 col-sm-6">
                                    <button type="submit" class="fill-btn border-0" wire:loading.attr="disabled">                        
                                            <span class="fill-btn-inner" wire:loading.remove wire:target="save">
                                                <span class="fill-btn-normal">Add Admin</span>
                                                <span class="fill-btn-hover">Add Admin</span>
                                            </span>

                                            <span class="fill-btn-inner" wire:loading wire:target="save">
                                                <span class="fill-btn-normal">Adding...</span>
                                                <span class="fill-btn-hover">Adding...</span>
                                            </span>
                                    </button>
                                </div>
                                <div class="col-12 col-sm-6 mt-4 mt-sm-0">
                                    <a href="{{ route('admins') }}" class="fill-btn-red" wire:navigate>
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