<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;

class FirebaseMessagingService
{
    protected $messaging;

    public function __construct()
    {
        $factory = (new Factory)
            ->withServiceAccount(config('firebase.credentials'))
            ->withDatabaseUri(config('firebase.database_url'));

        $this->messaging = $factory->createMessaging();
    }

    /**
     * Send a notification to a specific device
     *
     * @param string $token Device token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data to send
     * @return bool
     */
    public function sendToDevice(string $token, string $title, string $body, array $data = []): bool
    {
        try {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);
            return true;
        } catch (MessagingException $e) {
            \Log::error('Firebase messaging error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a notification to multiple devices
     *
     * @param array $tokens Array of device tokens
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data to send
     * @return bool
     */
    public function sendToMultipleDevices(array $tokens, string $title, string $body, array $data = []): bool
    {
        try {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->sendMulticast($message, $tokens);
            return true;
        } catch (MessagingException $e) {
            \Log::error('Firebase messaging error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send a notification to a topic
     *
     * @param string $topic Topic name
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data to send
     * @return bool
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): bool
    {
        try {
            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);
            return true;
        } catch (MessagingException $e) {
            \Log::error('Firebase messaging error: ' . $e->getMessage());
            return false;
        }
    }
} 