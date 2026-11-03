<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    /**
     * Everyone authenticated can reach the tracking list; how much of
     * it they see is decided by the visibleTo query scope.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin() || $user->isOperator() || $shipment->customer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function update(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin() || $user->isOperator();
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin();
    }
}
