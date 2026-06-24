<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller


{

    // show sign in view
    public function showSignIn (){
        if(Auth::check()){
            return redirect()->route('dashboard');
        }
        session()->flash('from_verification_form', true);
        return view ('auth.signin');
    }

    //sign in
    public function signIn(Request  $request){
      $credentials =  $request->validate([
          'email' => 'required|email',
          'password' => 'required|string',
      ]);

      if (Auth::attempt($credentials)) {
          $request->session()->regenerate();
          return redirect()->intended(route('dashboard'))->with('loginSuccess', 'Login Successful');
      }

      return back()->withErrors([
          'login' => 'Invalid Credentials.',
      ]);
  }
}
