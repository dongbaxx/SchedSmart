<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use function Livewire\Volt\{state, mount, rules, layout, title};

$user = Auth::user();

$role = strtolower($user->role ?? '');

$layoutFile = match($role) {
    'registrar' => 'layouts.registrar-shell',
    'dean'      => 'layouts.dean-shell',
    'head'      => 'layouts.head-shell',
    'faculty'   => 'layouts.faculty-shell',
    default     => abort(403, 'Unknown role, layout not found.'),
};

layout($layoutFile);
title('Profile Settings');

// component state
state([
    'name' => '',
    'email' => '',
    'current_password' => '',
    'new_password' => '',
    'new_password_confirmation' => '',
]);

mount(function () {
    $user = Auth::user();
    $this->name = $user->name;
    $this->email = $user->email;
});

// validation rules for name/email
rules([
    'name'  => ['required', 'string', 'max:255'],
    'email' => ['required', 'email', 'max:255', Rule::unique('users','email')->ignore(Auth::id())],
]);

$updateProfile = function () {
    $this->validate();

    $user = Auth::user();
    $user->update([
        'name' => $this->name,
        'email' => $this->email,
    ]);

    session()->flash('success', 'Profile updated successfully!');
};

$updatePassword = function () {
    $this->validate([
        'current_password' => ['required', 'current_password'],
        'new_password' => ['required', 'min:8', 'confirmed'],
    ]);

    $user = Auth::user();
    $user->password = Hash::make($this->new_password);
    $user->save();

    $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
    session()->flash('success', 'Password updated successfully!');
};
?>

<div class="max-w-xl mx-auto space-y-8">
    @if (session('success'))
        <div class="p-3 text-green-800 bg-green-100 rounded">
            {{ session('success') }}
        </div>
    @endif

    {{-- Profile Info --}}
    <form wire:submit.prevent="updateProfile" class="space-y-3">
        <h2 class="text-lg font-semibold">Profile Information</h2>

        <input type="text" wire:model="name" placeholder="Name" class="w-full p-2 border rounded">
        @error('name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror

        <input type="email" wire:model="email" placeholder="Email" class="w-full p-2 border rounded">
        @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror

        <button class="px-4 py-2 text-white bg-emerald-700 rounded hover:bg-emerald-800">
            Save Changes
        </button>
    </form>

    <hr>

    {{-- Password Change --}}
    <form wire:submit.prevent="updatePassword" class="space-y-3">
        <h2 class="text-lg font-semibold">Change Password</h2>

        <input type="password" wire:model="current_password" placeholder="Current Password"
               class="w-full p-2 border rounded">
        @error('current_password') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror

        <input type="password" wire:model="new_password" placeholder="New Password"
               class="w-full p-2 border rounded">
        <input type="password" wire:model="new_password_confirmation" placeholder="Confirm New Password"
               class="w-full p-2 border rounded">
        @error('new_password') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror

        <button class="px-4 py-2 text-white bg-emerald-700 rounded hover:bg-emerald-800">
            Update Password
        </button>
    </form>
</div>
