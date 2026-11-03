<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /**
     * The operations home. Live metrics land with the dashboard
     * milestone; for now it orients every role in the shell.
     */
    public function __invoke(): View
    {
        return view('dashboard');
    }
}
