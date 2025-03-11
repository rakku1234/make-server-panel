<?php

namespace App\Jobs;

use Illuminate\Support\Facades\DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ServerApiService;
use App\Models\Server;
use App\Models\Allocation;

class DeleteServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $uuid;

    /**
     * Create a new job instance.
     *
     * @param string $uuid
     * @return void
     */
    public function __construct(string $uuid)
    {
        $this->uuid = $uuid;
    }

    /**
     * Execute the job.
     *
     * @param \App\Services\ServerApiService $serverApiService
     * @return void
     */
    public function handle(ServerApiService $serverApiService)
    {
        DB::transaction(function () use ($serverApiService) {
            $server = $serverApiService->getServer($this->uuid);
            if (isset($server['id'])) {
                $serverApiService->DeleteServer($server['id']);
                $serverModel = Server::where('uuid', $this->uuid)->first();
                if ($serverModel) {
                    Allocation::where('id', $serverModel->allocation_id)->update(['assigned' => 0]);
                    $serverModel->delete();
                }
            }
        });
    }
}
