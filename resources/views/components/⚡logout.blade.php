<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public function logout (){
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        session()->flash('logout-success', 'You have been logged out successfully.');
        return $this->redirect(route('login'));
    }
};
?>

<div class="mb-4">
    <a class="nav-link text-danger" wire:confirm="Are you sure you want to log out??" wire:click="logout" wire:loading.attr="disabled">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
        <span wire:loading.remove wire:target="logout">Log Out</span>
        <span wire:loading wire:target="logout">Logging out...</span>
    </a>
</div>