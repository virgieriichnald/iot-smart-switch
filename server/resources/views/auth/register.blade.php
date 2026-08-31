@extends('layouts.app')

@section('title', 'Daftar Akun')

@section('content')
<div class="max-w-md mx-auto my-12 bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-2xl shadow-black/50">
    <div class="text-center mb-8">
        <div class="inline-flex w-14 h-14 rounded-2xl bg-indigo-600/20 text-indigo-400 items-center justify-center mb-3 border border-indigo-500/30">
            <i class="fa-solid fa-user-plus text-2xl"></i>
        </div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Daftar Akun Baru</h1>
        <p class="text-xs text-slate-400 mt-1">Buat akun untuk mengelola perangkat ESP32</p>
    </div>

    <form method="POST" action="{{ route('register.post') }}" class="space-y-4">
        @csrf
        <div>
            <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Nama Lengkap</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus
                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition"
                   placeholder="Admin IoT">
            @error('name')
                <p class="text-xs text-rose-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Email Address</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition"
                   placeholder="admin@example.com">
            @error('email')
                <p class="text-xs text-rose-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Password</label>
            <input type="password" name="password" id="password" required
                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition"
                   placeholder="Minimal 6 karakter">
            @error('password')
                <p class="text-xs text-rose-400 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Konfirmasi Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required
                   class="w-full bg-slate-950 border border-slate-700 rounded-xl px-4 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition"
                   placeholder="Ketik ulang password">
        </div>

        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-medium py-2.5 rounded-xl shadow-lg shadow-indigo-600/30 transition text-sm flex items-center justify-center space-x-2 mt-2">
            <i class="fa-solid fa-user-check"></i>
            <span>Daftar & Masuk</span>
        </button>
    </form>

    <div class="mt-6 text-center text-xs text-slate-400 border-t border-slate-800/80 pt-5">
        Sudah punya akun? <a href="{{ route('login') }}" class="text-indigo-400 hover:text-indigo-300 font-semibold">Login Sekarang</a>
    </div>
</div>
@endsection
