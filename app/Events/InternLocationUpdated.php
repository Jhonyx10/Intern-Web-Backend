<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class InternLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public int $internId,
        public ?string $internName,
        public int $companyId,
        public float $latitude,
        public float $longitude,
        public ?float $accuracyMeters,
        public string $recordedAt,
    ) {}

    /**
     * Broadcast to a private, per-company channel — only supervisors
     * scoped to that company can subscribe (see routes/channels.php).
     * ShouldBroadcastNow fires synchronously — no queue worker, no DB
     * write, no persistence at all. This event exists purely to relay
     * a coordinate from the intern's phone to a live dashboard.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("company.{$this->companyId}.locations"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'intern_id' => $this->internId,
            'intern_name' => $this->internName,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy_meters' => $this->accuracyMeters,
            'recorded_at' => $this->recordedAt,
        ];
    }
}