@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Printed Transactions" />

    <section class="min-h-[320px] rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] lg:p-6">
        <div class="border-b border-gray-200 pb-4 dark:border-gray-800">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                Printed Transactions
            </h3>
        </div>

        @isset($batch)
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                Printed transaction batch {{ $batch }} is ready for migrated content.
            </p>
        @else
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                Paste migrated Printed Transactions content here.
            </p>
        @endisset
    </section>
@endsection
