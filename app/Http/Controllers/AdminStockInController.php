<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminStockInController extends InventoryController
{
    public function index(Request $request): View
    {
        return view('pages.admin.inventory.stock-in', $this->inventoryViewData(
            $request,
            'stock',
            'Stock In',
            'Record new fuel deliveries and review recent tank replenishment logs.',
            'inventory.stock.index'
        ));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->stockIn($request);
    }
}
