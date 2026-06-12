@php
    use App\Helpers\MenuHelper;

    $user = auth()->user();
@endphp

@if ($user)
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
        <p class="mb-1 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
            Signed in as
        </p>
        <p class="text-sm font-semibold text-gray-800 dark:text-white/90">
            {{ $user->roleLabel() }}
        </p>
        <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
            {{ $user->name }}{{ $user->employee_id ? ' - '.$user->employee_id : '' }}
        </p>
        <a href="{{ MenuHelper::getDashboardPath($user->role) }}"
            class="mt-3 inline-flex w-full items-center justify-center rounded-lg bg-brand-500 px-3 py-2 text-xs font-medium text-white transition hover:bg-brand-600">
            Open Dashboard
        </a>
    </div>
@endif
