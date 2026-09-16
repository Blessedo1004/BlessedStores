<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\CartService;

class AuthController extends Controller


{

    // show sign in view
    public function showSignIn (){
        if(Auth::check()){
            return redirect()->route('dashboard');
        }
        return view ('auth.signin');
    }

    //sign in
        public function signIn(Request $request, CartService $cartService){
            $cartToken = $request->session()->get('cart_token');
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);
            $lowerCaseEmail = strtolower($request->input('email'));
            $credentials = [
                'email' => $lowerCaseEmail,
                'password' => $request->input('password'),
                ];
            if (Auth::attempt($credentials)) {
                $cartService->mergeGuestCart(Auth::user(), $cartToken);
                $request->session()->regenerate();
                // if(Auth::user()->role === 'customer'){
                //     return redirect()->intended(route('home'))->with('login-success', 'Login Successful');
                // }
                return redirect()->intended(route('dashboard'))->with('login-success', 'Login Successful');
            }

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'login' => 'Invalid Credentials.',
                ]);
  }
}
