<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Server;

class ServerStatus implements ShouldQueue
{
    use Dispatchable, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $uuids = Server::pluck('uuid');
        foreach ($uuids as $serverUuid) {
            $response = Http::withToken(config('panel.api_client_token'))
                ->withHeaders([
                    'Accept' => 'application/json',
                ])
                ->get(config('panel.api_url').'/api/client/servers/'.$serverUuid.'/resources');
            if ($response->successful()) {
                $status = $response->json()['attributes']['current_state'];
                Server::where('uuid', $serverUuid)->update(['status' => $status]);
            }
        }
    }
}
