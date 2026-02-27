<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\AdminActionRequiredNotification;
use App\Notifications\ClientRequestStatusNotification;

class NotificationDispatcher
{
    public static function notifyAdmins(
        string $eventType,
        string $title,
        string $message,
        array $meta = []
    ): void {
        $admins = User::query()
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', ['admin', 'Admin']);
            })
            ->get();

        foreach ($admins as $admin) {
            $admin->notify(new AdminActionRequiredNotification(
                eventType: $eventType,
                title: $title,
                message: $message,
                meta: $meta
            ));
        }
    }

    public static function notifyClient(
        ?User $client,
        string $eventType,
        string $title,
        string $message,
        array $meta = []
    ): void {
        if (!$client) {
            return;
        }

        $client->notify(new ClientRequestStatusNotification(
            eventType: $eventType,
            title: $title,
            message: $message,
            meta: $meta
        ));
    }
}
