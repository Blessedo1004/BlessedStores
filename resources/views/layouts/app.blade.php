<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{asset('imgs/logo/favicon.png')}}">

    <!-- Google Fonts - Sora -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    
    <!-- Main Project CSS -->
    <link rel="stylesheet" href="{{ asset('css/main.css') }}">

    <!-- Custom CSS-->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>

    @if(session('loginSuccess'))
        <div class="alert alert-success border-0 shadow-sm mb-4 p-3 d-flex gap-2 small justify-content-center col-9 col-md-5 position-fixed">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="flex-shrink-0 mt-0.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            <span>{{session('loginSuccess')}}</span>
        </div>
        
    @endif

    <div class="dashboard-layout">
        
        <!-- Sidebar Navigation -->
        <aside class="dashboard-sidebar d-flex flex-col justify-between" id="dashboardSidebar">
            <div>
                <!-- Brand Brand/Logo Header -->
                <div class="sidebar-brand d-flex align-items-center justify-content-between">
                    <a href="{{ route('home') }}" class="d-flex align-items-center gap-2">
                        <img src="{{ asset('imgs/logo/logo.png') }}" alt="logo" class="mb-3 logo">
                    </a>
                    <button class="btn btn-link text-white p-0 sidebar-toggle-btn d-lg-none" id="closeSidebarBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Nav Links -->
                <nav class="nav flex-column mt-4">
                    <a href="{{ route('dashboard')}}" class="nav-link  {{ request()->routeIs('dashboard') ? 'active' : ''}}" wire:navigate>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                        </svg>
                        <span>Overview</span>
                    </a>
                    
                    @can('store')
                      <a class="nav-link" href="#" wire:navigate>
                          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                          </svg>
                          <span>Products</span>
                      </a>
                    @endcan
                    
                    @if(Gate::allows('customer') || Gate::allows('store'))
                          <a class="nav-link" href="#" wire:navigate>
                              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                              </svg>
                              <span>Orders</span>
                          </a>
                    @endif

                    {{-- <a class="nav-link" href="#">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span>Customers</span>
                    </a> --}}

                    <a class="nav-link" href="#" wire:navigate>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10a2 2 0 01-2 2h-2a2 2 0 01-2-2zm9-1v-6a2 2 0 00-2-2h-2a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2z" />
                        </svg>
                        <span>Analytics</span>
                    </a>

                    @can('super-admin')
                      <a class="nav-link" href="#" wire:navigate>
                          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                          </svg>
                          <span>Admins</span>
                      </a>
                    @endcan

                    @if (Gate::allows('super-admin') || Gate::allows('admin'))
                          <a class="nav-link" href="#" wire:navigate>
                              <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21V16.5h-3V21m-6.213-9.103V18.75a.75.75 0 0 0 .75.75h14.25a.75.75 0 0 0 .75-.75v-6.853m-15.75 0a.75.75 0 0 1 .462-.692l7.5-3a.75.75 0 0 1 .576 0l7.5 3a.75.75 0 0 1 .462.692M3.75 12h16.5" />
                              </svg>
                              <span>Stores</span>
                          </a>
                    @endif
                </nav>
            </div>

            <!-- Sidebar Footer / Logout -->
            {{-- <div class="mb-4">
                <form method="POST" action="{{ route('logout') }}" id="logoutForm" class="d-none">
                    @csrf
                </form>
                <a class="nav-link text-danger" href="#" onclick="event.preventDefault(); document.getElementById('logoutForm').submit();">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Log Out</span>
                </a>
            </div> --}}
        </aside>

        <!-- Main Dashboard View Container -->
        <main class="dashboard-main d-flex flex-column">
            
            <!-- Top Dashboard Header -->
            <header class="dashboard-header d-flex align-items-center justify-content-between">
              @if(Gate::allows('customer') || Gate::allows('store'))
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-link text-dark p-0 sidebar-toggle-btn d-lg-none" id="openSidebarBtn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="position-relative d-none d-md-block">
                        <span class="position-absolute start-0 top-50 translate-middle-y ps-3 text-muted">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input type="text" class="header-search-bar" placeholder="Search orders, products...">
                    </div>
                </div> 
              @endif


                <!-- User Profile & Notifications dropdown -->
                <div class="d-flex align-items-center gap-3">
                    <!-- Notifications -->
                    <button class="btn btn-light rounded-circle p-2 position-relative" style="width: 40px; height: 40px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-secondary">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-white rounded-circle"></span>
                    </button>

                    <!-- Divider -->
                    <div class="vr bg-secondary opacity-25" style="height: 24px;"></div>

                    <!-- User Profile Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-link text-decoration-none d-flex align-items-center gap-2 p-0 text-dark" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                            {{-- <div class="rounded-circle d-flex align-items-center justify-content-center bg-color-1 text-white fw-bold shadow-sm" style="width: 40px; height: 40px; background-color: var(--primary-color);">
                                
                            </div> --}}
                            <div class="text-start d-none d-sm-block">
                                <h5 class="mb-0">{{ Auth::user()->name }}</h5>
                                <h6 class="text-muted">{{ Auth::user()->email }}</h6>
                            </div>
                        </button>
                    </div>
                </div>
            </header>
          {{ $slot }}  
        </main>
    </div>

    <!-- Scripts for responsive menu toggling -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('dashboardSidebar');
            const openBtn = document.getElementById('openSidebarBtn');
            const closeBtn = document.getElementById('closeSidebarBtn');

            if (sidebar && openBtn && closeBtn) {
                openBtn.addEventListener('click', function() {
                    sidebar.classList.add('show');
                });

                closeBtn.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                });
            }
        });
    </script>
    @livewireScripts
</body>
</html>
          
          
          
