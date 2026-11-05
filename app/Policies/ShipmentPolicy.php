<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    /**
     * Determine whether the user can view any shipments.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a specific shipment.
     * Customers can only view shipments assigned to their customer_id.
     */
    public function view(User $user, Shipment $shipment): bool
    {
        if ($user->canOperateCorridor()) {
            return true;
        }

        return (int) $shipment->customer_id === (int) $user->id;
    }

    /**
     * Determine whether the user can create a shipment.
     * Customers can request new shipments (forced to CREATED under their own customer_id).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update or append operational checkpoints to the shipment.
     */
    public function operate(User $user, Shipment $shipment): bool
    {
        return $user->canOperateCorridor();
    }

    /**
     * Determine whether the user can archive the shipment.
     */
    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->canOperateCorridor();
    }
}
