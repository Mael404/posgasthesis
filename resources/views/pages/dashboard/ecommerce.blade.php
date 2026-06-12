@extends('layouts.app')

@section('content')
  @php
    $user = auth()->user();
    $roleName = $roleName ?? $user?->roleLabel() ?? 'Dashboard';
    $roleDescription = $roleDescription ?? 'You are signed in to your assigned dashboard.';
  @endphp

  @if (session('error'))
    <div class="mb-4 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
      {{ session('error') }}
    </div>
  @endif

  <div class="mb-6 rounded-lg border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="mb-1 text-xs font-medium uppercase text-brand-500 dark:text-brand-400">
          {{ $roleName }} Access
        </p>
        <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">
          Welcome, {{ $user?->name ?? 'User' }}
        </h1>
        <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
          {{ $roleDescription }}
        </p>
      </div>
      <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm dark:bg-white/[0.04]">
        <span class="block text-xs text-gray-500 dark:text-gray-400">Current role</span>
        <span class="font-semibold text-gray-800 dark:text-white/90">{{ $roleName }}</span>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-12 gap-4 md:gap-6">
    <div class="col-span-12 space-y-6 xl:col-span-7">
      <x-ecommerce.ecommerce-metrics />
      <x-ecommerce.monthly-sale />
    </div>
    <div class="col-span-12 xl:col-span-5">
        <x-ecommerce.monthly-target />
    </div>

    <div class="col-span-12">
      <x-ecommerce.statistics-chart />
    </div>

    <div class="col-span-12 xl:col-span-5">
      <x-ecommerce.customer-demographic />
    </div>

    <div class="col-span-12 xl:col-span-7">
      <x-ecommerce.recent-orders />
    </div>
  </div>
@endsection
