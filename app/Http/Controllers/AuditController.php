<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;

/**
 * The change trail, visible to administrators only [S5, S10].
 */
class AuditController extends Controller
{
    public function index(): View
    {
        return view('audit.index', [
            'logs' => AuditLog::query()->with('actor')->orderByDesc('created_at')->orderByDesc('id')->paginate(25),
        ]);
    }
}
