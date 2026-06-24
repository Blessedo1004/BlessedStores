<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} | {{ config('app.name', 'Blessed Store') }}</title>

    <link rel="shortcut icon" type="image/x-icon" href="{{asset('imgs/logo/favicon.png')}}">

    <!-- Google Fonts - Sora -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{ asset('css/bootstrap.min.css') }}">
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="{{ asset('css/main.css') }}">
    
    <!-- Custom CSS-->
    @vite(['resources/css/app.css', 'resources/js/app.js'])    
    @livewireStyles
</head>
<body class="bg-light min-vh-100 d-flex align-items-center justify-content-center p-3">
     <!-- preloader start -->
   <div id="preloader">
      <div class="bd-loader-inner">
         <div class="bd-loader">
            <span class="bd-loader-item"></span>
            <span class="bd-loader-item"></span>
            <span class="bd-loader-item"></span>
            <span class="bd-loader-item"></span>
            <span class="bd-loader-item"></span>
            <span class="bd-loader-item"></span>
            <span class="bd-loader-item"></span>
            <span class="bd-loader-item"></span>
         </div>
      </div>
   </div>
   <!-- preloader end -->
  {{ $slot }}

     <script src="{{asset('js/jquery-3.6.0.min.js')}}"></script>
     <script src="{{asset('js/main.js')}}"></script>
    @livewireScripts
</body>
</html>    