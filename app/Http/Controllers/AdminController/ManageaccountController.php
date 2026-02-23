<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use App\Services\ManageAccountAdminService;
use Illuminate\Http\Request;

class ManageaccountController extends Controller
{
    public function __construct(
        protected ManageAccountAdminService $manageAccountAdminService
    ) {
    }

    public function showManageaccount(Request $request)
    {
        return $this->manageAccountAdminService->showManageaccount($request);
    }

    public function store(Request $request)
    {
        return $this->manageAccountAdminService->store($request);
    }

    public function update(Request $request, $id)
    {
        return $this->manageAccountAdminService->update($request, $id);
    }

    public function verifyAccount($id)
    {
        return $this->manageAccountAdminService->verifyAccount($id);
    }
}

