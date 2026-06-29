@extends('layouts.app')

@section('content')
    @include('pages.admin.inventory.partials.screen', [
        'inventoryPage' => 'stock',
        'inventoryTitle' => $inventoryTitle ?? 'Stock In',
        'inventoryDescription' => $inventoryDescription ?? 'Record new fuel deliveries and review recent tank replenishment logs.',
        'filterRoute' => $filterRoute ?? route('inventory.stock.index'),
        'resetRoute' => $resetRoute ?? route('inventory.stock.index'),
    ])
@endsection
