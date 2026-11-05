<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateExceptionRequest;
use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Services\ShipmentLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class ExceptionController extends Controller
{
    public function __construct(
        private readonly ShipmentLifecycleService $lifecycleService
    ) {}

    /**
     * GET /api/v1/shipments/{shipment}/exceptions
     */
    public function index(Request $request, Shipment $shipment): JsonResponse
    {
        Gate::authorize('view', $shipment);

        $exceptions = $shipment->exceptions()
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', strtoupper($status)))
            ->orderByDesc('detected_at')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json(['status' => 'ok', 'data' => $exceptions]);
    }

    /**
     * PATCH /api/v1/exceptions/{exception}
     * Move an exception through OPEN -> INVESTIGATING -> RESOLVED.
     * Admin and logistics operators only (role middleware).
     * Resolving stamps resolved_at with the server time.
     */
    public function update(UpdateExceptionRequest $request, ShipmentException $exception): JsonResponse
    {
        $validated = $request->validated();
        $targetStatus = $validated['status'];

        if (! $this->lifecycleService->canTransitionExceptionStatus($exception->status, $targetStatus)) {
            return response()->json([
                'status' => 'error',
                'message' => sprintf(
                    'An exception in %s can only move to: %s.',
                    $exception->status,
                    implode(', ', $this->lifecycleService->allowedNextExceptionStatuses($exception->status)) ?: 'nothing — it is resolved'
                ),
            ], 422);
        }

        $oldStatus = $exception->status;
        $exception->status = $targetStatus;

        if ($targetStatus === ShipmentException::STATUS_RESOLVED) {
            $exception->resolved_at = now();
        }

        if (! empty($validated['description'])) {
            $exception->description = $validated['description'];
        }

        $exception->save();

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'EXCEPTION_STATUS_CHANGED',
            'entity_type' => 'ShipmentException',
            'entity_id' => $exception->id,
            'changes' => [
                'status' => ['from' => $oldStatus, 'to' => $targetStatus],
            ],
        ]);

        return response()->json(['status' => 'ok', 'data' => $exception->fresh()]);
    }
}
