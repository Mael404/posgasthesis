@extends('layouts.fullscreen-layout')

@section('content')
    @php
        $emailError = $errors->first('email');
        $authError = $emailError && (str_contains($emailError, 'credentials') || str_contains($emailError, 'active user account') || str_contains($emailError, 'account role'))
            ? $emailError
            : null;
        $showInlineEmailError = $errors->has('email') && ! $authError;
    @endphp

    <style>
        [x-cloak] {
            display: none !important;
        }

        .signin-system-font,
        .signin-system-font input,
        .signin-system-font button,
        .signin-system-font label {
            font-family: "Segoe UI", Arial, sans-serif;
        }

        .signin-system-font input[type="password"]::-ms-reveal,
        .signin-system-font input[type="password"]::-ms-clear {
            display: none;
            height: 0;
            width: 0;
        }
    </style>

    <main class="relative min-h-screen overflow-hidden bg-white text-[#102318]">
        <div class="grid min-h-screen grid-cols-1 overflow-hidden lg:grid-cols-[minmax(0,0.98fr)_minmax(460px,1.02fr)]">

            {{-- RIGHT LOGIN PANEL --}}
            <section class="signin-system-font relative flex min-h-screen items-center justify-center bg-white px-5 py-8 sm:px-8 md:px-10 lg:order-2 lg:border-l lg:border-[#EDF2EE] lg:px-12 xl:px-16">
                <div class="w-full max-w-[450px] -translate-y-3 sm:max-w-[470px] sm:-translate-y-4 lg:max-w-[450px] lg:-translate-y-6 xl:max-w-[480px]">

                        {{-- Logo --}}
                        <div class="mb-4 flex justify-center sm:mb-5">
                            <img src="{{ asset('images/logo/jrs.png') }}"
                                alt="JRS Fuel Station logo"
                                class="h-32 w-64 object-contain sm:h-36 sm:w-72 lg:h-[164px] lg:w-[328px]" />
                        </div>

                        {{-- Heading --}}
                        <div class="mb-7 text-center sm:mb-8">
                            <h1 class="text-3xl font-extrabold tracking-tight text-[#063B22] sm:text-[34px]">
                                Login to your account
                            </h1>
                            <div class="mx-auto mt-2.5 flex w-20 items-center justify-center gap-1.5">
                                <span class="h-1 w-10 rounded-full bg-[#16803A]"></span>
                                <span class="h-1 w-5 rounded-full bg-[#F6C343]"></span>
                            </div>
                            <p class="mt-3 text-sm font-medium leading-6 text-[#66756B] sm:text-base">
                                Kindly provide your account credentials to continue.
                            </p>
                        </div>

                        {{-- Session Status --}}
                        @if (session('status'))
                            <div class="mb-5 flex items-start gap-3 rounded-2xl border border-[#16803A]/20 bg-[#16803A]/8 px-4 py-3 text-sm font-medium text-[#063B22]">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#16803A] text-white">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.25 7.25a1 1 0 01-1.42 0L3.296 9.22a1 1 0 111.414-1.414l4.034 4.034 6.543-6.543a1 1 0 011.417-.007z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                                <p>{{ session('status') }}</p>
                            </div>
                        @endif

                        {{-- Auth Error --}}
                        @if ($authError)
                            <div class="mb-5 flex items-start gap-3 rounded-2xl border border-[#D62828]/20 bg-[#D62828]/8 px-4 py-3 text-sm font-medium text-[#8F1B1B]">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#D62828] text-xs font-bold text-white">
                                    !
                                </span>
                                <div>
                                    <p class="font-bold text-[#D62828]">Sign in failed</p>
                                    <p class="mt-0.5">{{ $authError }}</p>
                                </div>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login.store') }}" class="space-y-4 sm:space-y-5">
                            @csrf

                            {{-- Email --}}
                            <div>
                                <label for="email" class="mb-2 block text-sm font-bold text-[#163524]">
                                    Email <span class="text-[#D62828]">*</span>
                                </label>

                                <div class="relative">
                                    <span class="pointer-events-none absolute left-4 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center text-[#16803A]">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none">
                                            <path d="M4.75 6.75h14.5v10.5H4.75V6.75Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                            <path d="m5.25 7.25 6.75 5 6.75-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>

                                    <input type="email"
                                        id="email"
                                        name="email"
                                        value="{{ old('email') }}"
                                        placeholder="Enter your email address"
                                        autocomplete="email"
                                        required
                                        autofocus
                                        class="h-[52px] w-full rounded-[8px] border border-[#D9E5DA] bg-white py-3 pl-12 pr-4 text-sm font-semibold text-[#102318] shadow-[0_10px_26px_rgba(16,35,24,0.05)] outline-none transition placeholder:text-[#9AA89D] hover:border-[#A9C8AE] focus:border-[#16803A] focus:ring-4 focus:ring-[#16803A]/15 sm:h-14" />
                                </div>

                                @if ($showInlineEmailError)
                                    <p class="mt-2 text-sm font-semibold text-[#D62828]">{{ $emailError }}</p>
                                @endif
                            </div>

                            {{-- Password --}}
                            <div>
                                <label for="password" class="mb-2 block text-sm font-bold text-[#163524]">
                                    Password <span class="text-[#D62828]">*</span>
                                </label>

                                <div x-data="{ showPassword: false }" class="relative">
                                    <span class="pointer-events-none absolute left-4 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center text-[#16803A]">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none">
                                            <path d="M7.75 10.25V8.5a4.25 4.25 0 0 1 8.5 0v1.75" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                                            <path d="M6.75 10.25h10.5a1.5 1.5 0 0 1 1.5 1.5v6a1.5 1.5 0 0 1-1.5 1.5H6.75a1.5 1.5 0 0 1-1.5-1.5v-6a1.5 1.5 0 0 1 1.5-1.5Z" stroke="currentColor" stroke-width="1.8" />
                                        </svg>
                                    </span>

                                    <input :type="showPassword ? 'text' : 'password'"
                                        id="password"
                                        name="password"
                                        placeholder="Enter your password"
                                        autocomplete="current-password"
                                        required
                                        class="h-[52px] w-full rounded-[8px] border border-[#D9E5DA] bg-white py-3 pl-12 pr-14 text-sm font-semibold text-[#102318] shadow-[0_10px_26px_rgba(16,35,24,0.05)] outline-none transition placeholder:text-[#9AA89D] hover:border-[#A9C8AE] focus:border-[#16803A] focus:ring-4 focus:ring-[#16803A]/15 sm:h-14" />

                                    <button type="button"
                                        @click="showPassword = !showPassword"
                                        class="absolute right-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-[8px] text-[#66756B] transition hover:bg-[#16803A]/10 hover:text-[#063B22] focus:outline-none focus:ring-4 focus:ring-[#16803A]/15 sm:h-10 sm:w-10"
                                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                        :aria-pressed="showPassword.toString()">
                                        <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none">
                                            <path d="M2.75 12s3.25-5.75 9.25-5.75S21.25 12 21.25 12 18 17.75 12 17.75 2.75 12 2.75 12Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                            <path d="M12 14.75A2.75 2.75 0 1 0 12 9.25a2.75 2.75 0 0 0 0 5.5Z" stroke="currentColor" stroke-width="1.8" />
                                        </svg>
                                    </button>
                                </div>

                                @error('password')
                                    <p class="mt-2 text-sm font-semibold text-[#D62828]">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Options --}}
                            <div class="flex flex-col gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                                <label for="remember" class="flex cursor-pointer items-center gap-3 text-sm font-semibold text-[#485B4E]">
                                    <input type="checkbox"
                                        id="remember"
                                        name="remember"
                                        value="1"
                                        @checked(old('remember'))
                                        class="h-4 w-4 rounded border-[#B8C8BA] text-[#16803A] focus:ring-[#16803A]/20" />
                                    Remember me
                                </label>

                                @if (\Illuminate\Support\Facades\Route::has('password.request'))
                                    <a href="{{ route('password.request') }}"
                                        class="text-sm font-bold text-[#16803A] transition hover:text-[#063B22] hover:underline">
                                        Forgot password?
                                    </a>
                                @else
                                    <span class="h-1.5 w-10 rounded-full bg-[#F6C343]"></span>
                                @endif
                            </div>

                            {{-- Submit --}}
                            <button type="submit"
                                class="group flex h-[52px] w-full items-center justify-center gap-3 rounded-[8px] bg-[#16803A] px-5 text-sm font-extrabold text-white shadow-[0_14px_26px_rgba(22,128,58,0.22)] transition hover:-translate-y-0.5 hover:bg-[#126D32] hover:shadow-[0_18px_32px_rgba(22,128,58,0.28)] focus:outline-none focus:ring-4 focus:ring-[#16803A]/25 sm:h-14">
                                <span>Sign In</span>
                                <svg class="h-5 w-5 transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none">
                                    <path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </form>

                </div>
            </section>

            {{-- LEFT HERO PANEL --}}
            <section class="relative hidden min-h-screen overflow-hidden bg-[#063B22] lg:order-1 lg:flex"
                style="background-image: url('{{ asset('images/logo/jrsloginbg.jpg') }}'); background-position: center; background-size: cover;">

                {{-- Hero overlays --}}
                <div class="absolute inset-0 bg-gradient-to-br from-[#021E13]/95 via-[#063B22]/82 to-[#16803A]/48"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-black/75 via-black/20 to-black/25"></div>
                <div class="absolute inset-y-0 left-0 w-1/3 bg-gradient-to-r from-[#021E13]/70 to-transparent"></div>

                {{-- Subtle dots --}}
                <div class="absolute right-10 top-12 grid grid-cols-7 gap-3 opacity-25">
                    @for ($i = 0; $i < 49; $i++)
                        <span class="h-1.5 w-1.5 rounded-full bg-white/30"></span>
                    @endfor
                </div>

                <div class="relative z-10 flex w-full flex-col justify-center p-10 xl:p-14 2xl:p-16">
                    {{-- Hero content --}}
                    <div class="max-w-2xl">
                        <div class="mb-6 h-1.5 w-20 rounded-full bg-[#F6C343]"></div>

                        <h2 class="text-5xl font-black leading-tight tracking-tight text-white xl:text-6xl 2xl:text-7xl">
                            JRM Fuel Station
                        </h2>

                        <p class="mt-5 text-xl font-extrabold text-[#F6C343] xl:text-2xl">
                            Fuel Station Management System
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </main>
@endsection
