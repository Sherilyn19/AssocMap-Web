<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class MembershipApplicationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly string $url,
    ) {
    }

    /**
     * This version uses the local database only.
     * No external API or notification provider is required.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * These values are available to the bell dropdown.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }
}