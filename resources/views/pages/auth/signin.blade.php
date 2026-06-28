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
    </style>

    <main class="relative min-h-screen overflow-hidden bg-[#F4F8F3] text-[#102318]">
        {{-- Brand top strip --}}
        <div class="absolute inset-x-0 top-0 z-30 h-2 bg-gradient-to-r from-[#16803A] via-[#F6C343] to-[#D62828]"></div>

        <div class="grid min-h-screen grid-cols-1 overflow-hidden lg:grid-cols-[minmax(0,0.98fr)_minmax(460px,1.02fr)]">

            {{-- RIGHT LOGIN PANEL --}}
            <section class="signin-system-font relative flex min-h-screen items-center justify-center overflow-hidden px-5 py-8 sm:px-8 lg:order-2 lg:px-12">
                {{-- Soft decorative background --}}
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(22,128,58,0.12),transparent_32%),radial-gradient(circle_at_bottom_right,rgba(246,195,67,0.12),transparent_28%)]"></div>

                <div class="absolute -left-24 top-16 h-72 w-72 rounded-full border border-[#16803A]/10"></div>
                <div class="absolute -left-14 top-28 h-80 w-80 rounded-full border border-[#16803A]/10"></div>
                <div class="absolute -bottom-20 left-0 h-72 w-72 rounded-full bg-[#16803A]/8 blur-3xl"></div>

                <div class="absolute left-8 top-16 hidden grid-cols-6 gap-3 opacity-40 sm:grid">
                    @for ($i = 0; $i < 36; $i++)
                        <span class="h-1.5 w-1.5 rounded-full bg-[#16803A]/30"></span>
                    @endfor
                </div>

                <div class="absolute bottom-16 right-10 hidden grid-cols-7 gap-3 opacity-30 md:grid">
                    @for ($i = 0; $i < 42; $i++)
                        <span class="h-1.5 w-1.5 rounded-full bg-[#16803A]/35"></span>
                    @endfor
                </div>

                <div class="relative z-10 w-full max-w-[500px]">
                    <div class="rounded-[2rem] border border-white/80 bg-white/90 px-6 py-8 shadow-[0_24px_70px_rgba(6,59,34,0.16)] backdrop-blur-xl sm:px-8 sm:py-10 lg:px-10">

                        {{-- Logo --}}
                        <div class="mb-7 flex justify-center">
                            <img src="{{ asset('images/logo/jrs.png') }}"
                                alt="JRS Fuel Station logo"
                                class="h-24 w-44 object-contain sm:h-28 sm:w-52" />
                        </div>

                        {{-- Heading --}}
                        <div class="mb-7 text-center">
                            <h1 class="text-4xl font-extrabold tracking-tight text-[#063B22] sm:text-5xl">
                                Welcome back
                            </h1>
                            <div class="mx-auto mt-3 h-1.5 w-14 rounded-full bg-[#F6C343]"></div>
                            <p class="mt-4 text-sm font-medium text-[#66756B] sm:text-base">
                                Sign in to continue to your dashboard.
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

                        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
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
                                        class="h-14 w-full rounded-2xl border border-[#D9E5DA] bg-white py-3 pl-12 pr-4 text-sm font-semibold text-[#102318] shadow-sm outline-none transition placeholder:text-[#9AA89D] hover:border-[#A9C8AE] focus:border-[#16803A] focus:ring-4 focus:ring-[#16803A]/15" />
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
                                        class="h-14 w-full rounded-2xl border border-[#D9E5DA] bg-white py-3 pl-12 pr-14 text-sm font-semibold text-[#102318] shadow-sm outline-none transition placeholder:text-[#9AA89D] hover:border-[#A9C8AE] focus:border-[#16803A] focus:ring-4 focus:ring-[#16803A]/15" />

                                    <button type="button"
                                        @click="showPassword = !showPassword"
                                        class="absolute right-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-xl text-[#66756B] transition hover:bg-[#16803A]/10 hover:text-[#063B22] focus:outline-none focus:ring-4 focus:ring-[#16803A]/15"
                                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                        :aria-pressed="showPassword.toString()">

                                        <svg x-show="!showPassword" class="h-5 w-5 fill-current" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M10.0002 13.8619C7.23361 13.8619 4.86803 12.1372 3.92328 9.70241C4.86804 7.26761 7.23361 5.54297 10.0002 5.54297C12.7667 5.54297 15.1323 7.26762 16.0771 9.70243C15.1323 12.1372 12.7667 13.8619 10.0002 13.8619ZM10.0002 4.04297C6.48191 4.04297 3.49489 6.30917 2.4155 9.4593C2.3615 9.61687 2.3615 9.78794 2.41549 9.94552C3.49488 13.0957 6.48191 15.3619 10.0002 15.3619C13.5184 15.3619 16.5055 13.0957 17.5849 9.94555C17.6389 9.78797 17.6389 9.6169 17.5849 9.45932C16.5055 6.30919 13.5184 4.04297 10.0002 4.04297ZM9.99151 7.84413C8.96527 7.84413 8.13333 8.67606 8.13333 9.70231C8.13333 10.7286 8.96527 11.5605 9.99151 11.5605H10.0064C11.0326 11.5605 11.8646 10.7286 11.8646 9.70231C11.8646 8.67606 11.0326 7.84413 10.0064 7.84413H9.99151Z" />
                                        </svg>

                                        <svg x-show="showPassword" x-cloak class="h-5 w-5 fill-current" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M4.63803 3.57709C4.34513 3.2842 3.87026 3.2842 3.57737 3.57709C3.28447 3.86999 3.28447 4.34486 3.57737 4.63775L4.85323 5.91362C3.74609 6.84199 2.89363 8.06395 2.4155 9.45936C2.3615 9.61694 2.3615 9.78801 2.41549 9.94558C3.49488 13.0957 6.48191 15.3619 10.0002 15.3619C11.255 15.3619 12.4422 15.0737 13.4994 14.5598L15.3625 16.4229C15.6554 16.7158 16.1302 16.7158 16.4231 16.4229C16.716 16.13 16.716 15.6551 16.4231 15.3622L4.63803 3.57709ZM12.3608 13.4212L10.4475 11.5079C10.3061 11.5423 10.1584 11.5606 10.0064 11.5606H9.99151C8.96527 11.5606 8.13333 10.7286 8.13333 9.70237C8.13333 9.5461 8.15262 9.39434 8.18895 9.24933L5.91885 6.97923C5.03505 7.69015 4.34057 8.62704 3.92328 9.70247C4.86803 12.1373 7.23361 13.8619 10.0002 13.8619C10.8326 13.8619 11.6287 13.7058 12.3608 13.4212ZM16.0771 9.70249C15.7843 10.4569 15.3552 11.1432 14.8199 11.7311L15.8813 12.7925C16.6329 11.9813 17.2187 11.0143 17.5849 9.94561C17.6389 9.78803 17.6389 9.61696 17.5849 9.45938C16.5055 6.30925 13.5184 4.04303 10.0002 4.04303C9.13525 4.04303 8.30244 4.17999 7.52218 4.43338L8.75139 5.66259C9.1556 5.58413 9.57311 5.54303 10.0002 5.54303C12.7667 5.54303 15.1323 7.26768 16.0771 9.70249Z" />
                                        </svg>
                                    </button>
                                </div>

                                @error('password')
                                    <p class="mt-2 text-sm font-semibold text-[#D62828]">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Options --}}
                            <div class="flex items-center justify-between gap-4">
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
                                class="group flex h-14 w-full items-center justify-center gap-3 rounded-2xl bg-[#16803A] px-5 text-sm font-extrabold text-white shadow-[0_18px_34px_rgba(22,128,58,0.28)] transition hover:-translate-y-0.5 hover:bg-[#126D32] hover:shadow-[0_22px_40px_rgba(22,128,58,0.34)] focus:outline-none focus:ring-4 focus:ring-[#16803A]/25">
                                <span>Sign In</span>
                                <svg class="h-5 w-5 transition group-hover:translate-x-1" viewBox="0 0 24 24" fill="none">
                                    <path d="M5 12h14m-6-6 6 6-6 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </form>

                    </div>
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
                            JRS Fuel Station
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
