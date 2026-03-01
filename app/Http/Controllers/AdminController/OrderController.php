<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use App\Services\OrderAdminService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        protected OrderAdminService $orderAdminService
    ) {
    }

    public function create()
    {
        return $this->orderAdminService->create();
    }

    public function store(Request $request)
    {
        return $this->orderAdminService->store($request);
    }

    public function sourceInventoryOptions(Request $request)
    {
        return $this->orderAdminService->sourceInventoryOptions($request);
    }

    public function index()
    {
        return $this->orderAdminService->index();
    }

    public function updateStatus(Request $request, $id)
    {
        return $this->orderAdminService->updateStatus($request, $id);
    }

    public function print($id)
    {
        return $this->orderAdminService->print($id);
    }
}
