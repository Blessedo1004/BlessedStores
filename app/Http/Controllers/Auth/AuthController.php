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
          $request->session()->regenerate();
          return redirect()->intended(route('dashboard'))->with('login-success', 'Login Successful');
      }

      return back()
          ->withInput($request->only('email'))
          ->withErrors([
              'login' => 'Invalid Credentials.',
          ]);
  }
}
