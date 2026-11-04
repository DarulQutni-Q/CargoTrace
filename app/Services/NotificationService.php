<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;

/**
 * In-app notifications for the people a shipment change concerns [S10].
 * The customer of a shipment is always the primary recipient; email
 * delivery stays a documented future option [S10 advanced].
 */
class NotificationService
{
    public function notifyCustomer(Shipment $shipment, string $type, string $title, string $body): void
    {
        $shipment->customer->notifications()->create([
            'shipment_id' => $shipment->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
        ]);
    }

    public function notifyUser(User $user, ?Shipment $shipment, string $type, string $title, string $body): void
    {
        $user->notifications()->create([
            'shipment_id' => $shipment?->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
        ]);
    }
}
