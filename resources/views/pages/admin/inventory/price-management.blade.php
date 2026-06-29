@extends('layouts.app')

@section('content')
    @include('pages.admin.inventory.partials.screen', [
        'inventoryPage' => 'pricing',
        'inventoryTitle' => $inventoryTitle ?? 'Price Management',
        'inventoryDescription' => $inventoryDescription ?? 'Maintain the active fuel prices used by future pump transactions.',
        'filterRoute' => $filterRoute ?? route('inventory.prices.index'),
        'resetRoute' => $resetRoute ?? route('inventory.prices.index'),
    ])
@endsection
