<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTankManagementController extends InventoryController
{
    public function index(Request $request): View
    {
        return view('pages.admin.inventory.tank-management', $this->inventoryViewData(
            $request,
            'tanks',
            'Tank Management',
            'Current tank levels, connected pump assignments, and pump transaction history.',
            'inventory.tanks.index'
        ));
    }
}
