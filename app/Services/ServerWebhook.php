<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\Server;
use Spatie\DiscordAlerts\DiscordAlert;
use Exception;

class ServerWebhook
{
    public function SyncCreate($data)
    {
        if (!empty($data) && array_is_list($data)) {
            Log::error("データがリストではありません");
            return;
        }
        try {
            $data['name'] = $data[0]['name'];
            $data['description'] = $data[0]['description'] ?? null;
            $data['uuid'] = $data[0]['uuid'];
            $data['allocation_id'] = $data[0]['allocation_id'];
            $data['user'] = $data[0]['owner_id'];
            $data['node'] = $data[0]['node_id'];
            $data['slug'] = isset($data[0]['external_id'])
                ? Str::slug($data[0]['external_id'])
                : Str::random(10);
            $data['limits'] = [
                'cpu'      => $data[0]['cpu'] * 100,
                'memory'   => (int) round($data[0]['memory'] * 1024 / 1000),
                'swap'     => $data[0]['swap'],
                'disk'     => (int) round($data[0]['disk'] * 1024 / 1000),
                'io'       => $data[0]['io'],
                'threads'  => $data[0]['threads'],
                'oom_kill' => true,
            ];
            $data['feature_limits'] = [
                'databases'   => $data[0]['database_limit'],
                'allocations' => $data[0]['allocation_limit'],
                'backups'     => $data[0]['backup_limit'],
            ];
            $data['egg']                = $data[0]['egg_id'];
            $data['docker_image']       = $data[0]['image'];
            $data['egg_variables']      = [];
            $data['egg_variables_meta'] = [];
            Server::create($data);
        } catch (Exception $e) {
            (new DiscordAlert())->to(config('discord-alerts.webhook_urls.default'))
            ->message('サーバー作成エラー', [
                'title' => 'サーバー作成エラー',
                'description' => $e->getMessage(),
                'color' => 0x0000FF,
            ]);
            Log::error($e->getMessage());
        }
    }

    public function SyncDelete($data)
    {
        $server = Server::where('uuid', $data[0]['uuid'])->first();
        if ($server) {
            $server->delete();
        } else {
            Log::error('サーバーが見つかりません');
        }
    }
}
