<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\SyncWebhookService;

class SyncWebhook implements ShouldQueue
{
    use Queueable;

    protected array $data;

    protected string $type;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data, string $type)
    {
        $this->data = $data;
        $this->type = $type;
    }

    /**
     * Execute the job.
     */
    public function handle(SyncWebhookService $webhook): void
    {
        match ($this->type) {
            'node.create' => $webhook->SyncNodeCreate($this->data),
            'node.delete' => $webhook->SyncNodeDelete($this->data),
            'allocation.create' => $webhook->SyncAllocationCreate($this->data),
            'allocation.delete' => $webhook->SyncAllocationDelete($this->data),
            'egg.create' => $webhook->SyncEggCreate($this->data),
            'egg.delete' => $webhook->SyncEggDelete($this->data),
            'server.create' => $webhook->SyncServerCreate($this->data),
            'server.delete' => $webhook->SyncServerDelete($this->data),
            'user.create' => $webhook->SyncUserCreate($this->data),
            'user.delete' => $webhook->SyncUserDelete($this->data),
            default => null,
        };
    }
}
