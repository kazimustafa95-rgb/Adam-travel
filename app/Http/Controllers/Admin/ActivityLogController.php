<?php

namespace App\Http\Controllers\Admin;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends BaseAdminController
{
    public function index(Request $request): View
    {
        $logs = ActivityLog::query()
            ->with('admin')
            ->when($request->string('q')->toString() !== '', function ($query) use ($request): void {
                $search = $request->string('q')->toString();
                $query->where(function ($builder) use ($search): void {
                    $builder->where('description', 'like', '%'.$search.'%')
                        ->orWhere('action', 'like', '%'.$search.'%')
                        ->orWhere('target_label', 'like', '%'.$search.'%');
                });
            })
            ->when($request->string('action')->toString() !== '', fn ($query) => $query->where('action', $request->string('action')->toString()))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.activity.index', [
            'logs' => $logs,
            'filters' => $request->only(['q', 'action']),
        ]);
    }
}
