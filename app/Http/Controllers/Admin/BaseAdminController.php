<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Support\Facades\Auth;

abstract class BaseAdminController extends Controller
{
    protected function admin(): Admin
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        return $admin;
    }
}
