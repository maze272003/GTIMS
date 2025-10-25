<?php

namespace App\Http\Controllers\AdminController;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ManageaccountController extends Controller
{
    public function showManageaccount(){
        return view('admin.manageaccount');
    }
}
