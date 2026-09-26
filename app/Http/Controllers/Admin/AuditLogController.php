<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request, AuditLogService $service): View
    {
        return view('admin-pages.audit-logs.index', $service->listing($request->validated()));
    }
}
