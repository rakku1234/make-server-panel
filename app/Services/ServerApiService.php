<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Filament\Notifications\Notification;
use App\Models\Server;
use App\Models\Allocation;
use App\Models\User;
use Exception;

final class ServerApiService
{
    public function createServer(Server $server): array
    {
        $allocation = Allocation::find($server->allocation_id);
        if (!$allocation) {
            throw new Exception('Allocation not found');
        }

        $data = [
            /** @phpstan-ignore-next-line */
            "external_id" => $server?->external_id,
            "name"        => $server->name,
            /** @phpstan-ignore-next-line */
            "description" => $server?->description,
            "user"        => User::find($server->user)->panel_user_id,
            "egg"         => $server->egg,
            "slug"        => $server->slug,
            "environment" => $server->egg_variables,
            "docker_image"=> $server->docker_image,
            "oom_killer"  => $server->limits['oom_kill'] ?? true,
            "start_on_completion" => (bool)$server->start_on_completion,
            "allocation" => [
                "default" => $server->allocation_id,
            ],
            "limits" => [
                "memory" => $server->limits['memory'],
                "swap"   => $server->limits['swap'] ?? -1,
                "disk"   => $server->limits['disk'],
                "io"     => $server->limits['io'] ?? 500,
                "cpu"    => $server->limits['cpu'],
            ],
            "feature_limits" => [
                "databases"   => $server->feature_limits['databases'],
                "allocations" => $server->feature_limits['allocations'],
                "backups"     => $server->feature_limits['backups'],
            ],
        ];

        $response = Http::withToken(config('panel.api_application_token'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])
            ->post(config('panel.api_url').'/api/application/servers', $data);

        if ($response->successful()) {
            return [
                'success' => true,
                'server' => $response->json(),
            ];
        } else {
            throw new Exception('Server creation failed');
        }
    }

    public function updateServer(Server $server): void
    {
        Notification::make()
            ->title('まだこの機能は実装されていません。')
            ->danger()
            ->send();
    }

    public function deleteServer(int $serverid): bool
    {
        $server = Server::find($serverid);
        $apiUrl = config('panel.api_url')."/api/application/servers/{$serverid}";
        $response = Http::withToken(config('panel.api_application_token'))
            ->withHeaders(['Accept' => 'application/json'])
            ->delete($apiUrl);

        if ($response->successful()) {
            if ($server) {
                activity()
                    ->performedOn($server)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'name' => $server->name,
                        'allocation_id' => $server->allocation_id
                    ])
                    ->log('サーバーを削除しました');
            }
            Notification::make()
                ->title('サーバー削除に成功しました')
                ->success()
                ->send();
            return true;
        } else {
            Notification::make()
                ->title('サーバー削除に失敗しました')
                ->body($response->body())
                ->danger()
                ->send();
            return false;
        }
    }

    public function getUserlist(): ?array
    {
        $apiUrl = config('panel.api_url').'/api/application/users';
        $response = Http::withToken(config('panel.api_application_token'))
            ->withHeaders(['Accept' => 'application/json'])
            ->get($apiUrl);
        if ($response->successful()) {
            return $response->json();
        }
        return null;
    }

    public function getServer(string $uuid): ?array
    {
        $apiUrl = config('panel.api_url')."/api/application/servers";
        $response = Http::withToken(config('panel.api_application_token'))
            ->withHeaders(['Accept' => 'application/json'])
            ->get($apiUrl);
        if ($response->successful()) {
            $servers = $response->json()['data'];
            foreach ($servers as $server) {
                if ($server['attributes']['uuid'] === $uuid) {
                    return $server['attributes'];
                }
            }
        }
        return null;
    }
}
