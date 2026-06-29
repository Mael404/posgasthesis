@extends('layouts.app')

@section('content')
    @include('pages.admin.inventory.partials.screen', [
        'inventoryPage' => 'tanks',
        'inventoryTitle' => $inventoryTitle ?? 'Tank Management',
        'inventoryDescription' => $inventoryDescription ?? 'Current tank levels, connected pump assignments, and pump transaction history.',
        'filterRoute' => $filterRoute ?? route('inventory.tanks.index'),
        'resetRoute' => $resetRoute ?? route('inventory.tanks.index'),
    ])
@endsection
