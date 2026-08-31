@extends('layouts.app')

@section('title', 'Login')

@section('content')
<div class="max-w-md mx-auto my-12 bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl shadow-black/50">
    <div class="text-center mb-8">
        <div class="inline-flex w-14 h-14 rounded-2xl bg-blue-600/20 text-blue-400 items-center justify-center mb-3 border border-blue-500/30">
            <i class="fa-solid fa-lock text-2xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Login ke IoT Hub</h1>
        <p class="text-xs text-slate-400 mt-1">Masuk untuk mengontrol saklar dan melihat perangkat</p>
    </div>

    <form method="POST" action="{{ route('login.post') }}" class="space-y-5">
        @csrf
        <div>
            <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Email Address</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                    <i class="fa-solid fa-envelope"></i>
                </span>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                       class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-10 pr-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                       placeholder="admin@example.com">
            </div>
            @error('email')
                <p class="text-xs text-rose-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Password</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                    <i class="fa-solid fa-key"></i>
                </span>
                <input type="password" name="password" id="password" required
                       class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-10 pr-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
                       placeholder="••••••••">
            </div>
            @error('password')
                <p class="text-xs text-rose-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between text-xs">
            <label class="flex items-center text-slate-400 cursor-pointer">
                <input type="checkbox" name="remember" class="rounded bg-slate-800 border-slate-700 text-blue-600 focus:ring-0 mr-2">
                Ingat saya
            </label>
        </div>

        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-medium py-2.5 rounded-xl shadow-lg shadow-blue-600/30 transition text-sm flex items-center justify-center space-x-2">
            <i class="fa-solid fa-right-to-bracket"></i>
            <span>Masuk ke Dashboard</span>
        </button>
    </form>

    <div class="mt-6 text-center text-xs text-slate-400 border-t border-slate-800/80 pt-5">
        Belum punya akun? <a href="{{ route('register') }}" class="text-blue-400 hover:text-blue-300 font-semibold">Daftar Akun Baru</a>
    </div>
</div>
@endsection
