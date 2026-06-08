<?php

declare(strict_types=1);

namespace App\Notification\Sms;

/**
 * Stub seam for a future SMS provider (e.g. Twilio).
 *
 * NOT IMPLEMENTED THIS SESSION. When SMS is added, provide a Twilio-backed
 * implementation and inject it into App\Notification\Channel\SmsChannel, then
 * add 'sms' to OrderPlacedNotification::via().
 */
interface SmsSenderInterface
{
    /**
     * Send an SMS message to an E.164 / local phone number.
     */
    public function send(string $toPhone, string $message): void;
}
