<?php

use Livewire\Livewire;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Mail;
use App\Mail\PreRegistrationEmail;

test('preregistration notice resend code rate limit and mail sending', function () {
    Mail::fake();

    $email = 'test@example.com';
    RateLimiter::clear("resend-code:{$email}");
    $userData = [
        'name' => 'Test User',
        'email' => $email,
        'password' => 'Password123!',
    ];

    // Put initial data in cache
    $initialCode = '111111';
    Cache::put("preregistration-email-for-{$initialCode}", $userData, 15 * 60);
    Cache::put("preregistration-email-code-{$email}", $initialCode, 15 * 60);

    // Start a session with the email
    session(['email' => $email]);

    // Livewire test
    $component = Livewire::test('auth.⚡preregistration-notice');

    // Initially, there shouldn't be any error
    $component->assertHasNoErrors();
    $component->assertSet('countdown', 60);

    // Call resendCode
    $component->call('resendCode');

    // Mail should be queued.
    Mail::assertQueued(PreRegistrationEmail::class);

    // It should restart the browser countdown.
    $component->assertDispatched('resend-countdown', seconds: 60);

    // Try to resend again immediately
    $component->call('resendCode');

    // It should have validation error for too many attempts
    $component->assertHasErrors(['code']);
});
