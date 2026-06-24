
<x-layouts.auth title="Sign In">
  <x-slot:slot>  
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

        <!-- Main Login Card -->
        <div class="card border-0 shadow-sm rounded-4 auth-card">
            <div class="card-body p-4 p-md-5">
                
                <!-- Brand Info Header -->
                <div class="text-center mb-4">
                    <a href="{{ route('home') }}">
                        <img src="{{ asset('imgs/logo/logo.png') }}" alt="logo" class="mb-3 logo">
                    </a>
                    <h1 class="h4 fw-bold text-dark mb-1" style="font-family: 'Sora', sans-serif;">Welcome Back</h1>
                    <p class="text-muted small mb-0">Enter your credentials to access your account</p>
                </div>

                <!-- Login Form -->
                <form method="POST" action="{{ route('auth.signin') }}" class="needs-validation" novalidate>
                    @csrf

                    <!-- Email Input -->
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold text-dark small mb-2">Email Address</label>
                        <input id="email" type="email" name="email" placeholder="name@example.com" value="{{ old('email') }}" required autofocus autocomplete="username">
                    </div>

                    <!-- Password Input -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label for="password" class="form-label fw-semibold text-dark small mb-0">Password</label>
                                <a href="/forgot-password" class="text-color-1 text-decoration-none small fw-bold">
                                    Forgot password?
                                </a>
                           
                        </div>
                        <div class="position-relative">
                            <input id="password" type="password" name="password" placeholder="••••••••" required autocomplete="current-password" style="padding-right: 50px;">
                            <button class="position-absolute end-0 top-50 translate-middle-y border-0 bg-transparent pe-4 text-muted" type="button" id="togglePasswordBtn" style="height: 100%; display: flex; align-items: center; z-index: 10;" aria-label="Toggle Password Visibility">
                                <!-- Eye Icon SVG -->
                                <svg id="eyeOpenIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <!-- Eye-Slash Icon SVG -->
                                <svg id="eyeClosedIcon" class="d-none" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me Checkbox -->
                    <div class="mb-4 form-check">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember_me">
                        <label class="form-check-label text-muted small" for="remember_me">
                            Keep me logged in
                        </label>
                    </div>

                    <!-- Action Button -->
                    <button type="submit" class="fill-btn w-100 border-0">
                        <span class="fill-btn-inner">
                            <span class="fill-btn-normal">Sign In</span>
                            <span class="fill-btn-hover">Sign In</span>
                        </span>
                    </button>
                </form>

                <!-- Signup Redirect Footer -->
                <div class="text-center mt-4 small text-muted">
                        <span>Don't have an account? <a href="{{ route('signUp') }}" class="text-color-1 text-decoration-none fw-bold" wire:navigate>Create one</a></span>
                </div>

            </div>
        </div>
    </div>

    <!-- Script for Password Toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.getElementById('togglePasswordBtn');
            const eyeOpen = document.getElementById('eyeOpenIcon');
            const eyeClosed = document.getElementById('eyeClosedIcon');

            if (toggleBtn && passwordInput && eyeOpen && eyeClosed) {
                toggleBtn.addEventListener('click', function() {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    
                    if (type === 'password') {
                        eyeOpen.classList.remove('d-none');
                        eyeClosed.classList.add('d-none');
                    } else {
                        eyeOpen.classList.add('d-none');
                        eyeClosed.classList.remove('d-none');
                    }
                });
            }
        });
    </script>
</x-slot:slot>
</x-layouts.auth>