@if ($trail ?? false)
    <nav class="mb-4 flex items-center gap-2 text-theme-sm font-medium text-gray-500 dark:text-gray-400" aria-label="Breadcrumb">
        <span>Admin</span>
        <svg class="size-4 text-gray-400 dark:text-gray-600" viewBox="0 0 20 20" fill="none" aria-hidden="true">
            <path d="m7.5 4.5 5 5.5-5 5.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <span class="text-gray-700 dark:text-gray-300">{{ $page }}</span>
    </nav>
@else
    <header class="mb-4">
        <p class="text-theme-xs font-medium uppercase text-gray-500 dark:text-gray-400">Admin</p>
        <h1 class="mt-1 text-title-sm font-semibold text-gray-800 dark:text-white/90">{{ $page }}</h1>
    </header>
@endif
