<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPriceManagementController extends InventoryController
{
    public function index(Request $request): View
    {
        return view('pages.admin.inventory.price-management', $this->inventoryViewData(
            $request,
            'pricing',
            'Price Management',
            'Maintain the active fuel prices used by future pump transactions.',
            'inventory.prices.index'
        ));
    }

    public function update(Request $request): JsonResponse
    {
        return $this->updatePrice($request);
    }
}
