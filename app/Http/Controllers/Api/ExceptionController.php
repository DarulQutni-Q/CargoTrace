<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateExceptionRequest;
use App\Models\ShipmentException;
use App\Services\ExceptionService;

class ExceptionController extends Controller
{
    public function __construct(private ExceptionService $exceptions) {}

    public function update(UpdateExceptionRequest $request, ShipmentException $exception)
    {
        $updated = $this->exceptions->update($exception, $request->validated(), $request->user());

        return response()->json(['data' => $updated->load('resolver')]);
    }
}
