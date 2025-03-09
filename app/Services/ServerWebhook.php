<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Models\Server;
use App\Models\Node;
use App\Models\User;
use App\Models\Allocation;
use App\Models\Egg;
use App\Func\NumberConverter;
use Exception;

final class ServerWebhook
{
    public function SyncNodeCreate(array$data): void
    {
        try {
            $data['uuid'] = $data[0]['uuid'];
            $data['node_id'] = $data[0]['id'];
            $data['name'] = $data[0]['name'];
            $data['slug'] = Str::slug($data[0]['name']);
            $data['public'] = $data[0]['public'];
            $data['maintenance_mode'] = $data[0]['maintenance_mode'];
            Node::create($data);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function SyncNodeDelete(array $data): void
    {
        try {
            $node = Node::where('uuid', $data[0]['uuid'])->firstOrFail();
            $node->deleteOrFail();
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function SyncAllocationCreate(array $data): void
    {
        try {
            $data['id'] = $data[0]['id'];
            $data['node_id'] = $data[0]['node_id'];
            $data['alias'] = $data[0]['ip_alias'];
            $data['port'] = $data[0]['port'];
            $data['public'] = true;
            $data['assigned'] = false;
            Allocation::create($data);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function SyncAllocationDelete(array $data): void
    {
        try {
            $allocation = Allocation::where('node_id', $data[0]['node_id'])->where('id', $data[0]['id'])->firstOrFail();
            $allocation->deleteOrFail();
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function SyncEggCreate(array $data): void
    {
        try {
            $data['uuid'] = $data[0]['uuid'];
            $data['egg_id'] = $data[0]['id'];
            $data['name'] = $data[0]['name'];
            $data['description'] = $data[0]['description'];
            $data['docker_images'] = $data[0]['docker_images'];
            $data['public'] = true;
            Egg::create($data);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function SyncEggDelete(array $data): void
    {
        try {
            $egg = Egg::where('egg_id', $data[0]['id'])->firstOrFail();
            $egg->deleteOrFail();
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function SyncServerCreate(array $data): void
    {
        try {
            $data['name'] = $data[0]['name'];
            $data['description'] = $data[0]['description'] ?? null;
            $data['uuid'] = $data[0]['uuid'];
            $data['allocation_id'] = $data[0]['allocation_id'];
            $data['user'] = $data[0]['owner_id'];
            $data['node'] = $data[0]['node_id'];
            $data['slug'] = Str::random(10);
            $data['limits'] = [
                'cpu'      => NumberConverter::convertCpuCore($data[0]['cpu'], false),
                'memory'   => NumberConverter::convert($data[0]['memory'], 'MB', 'MiB'),
                'swap'     => $data[0]['swap'],
                'disk'     => NumberConverter::convert($data[0]['disk'], 'MB', 'MiB'),
                'io'       => $data[0]['io'],
                'threads'  => $data[0]['threads'],
                'oom_kill' => true,
            ];
            $data['feature_limits'] = [
                'databases'   => $data[0]['database_limit'],
                'allocations' => $data[0]['allocation_limit'],
                'backups'     => $data[0]['backup_limit'],
            ];
            $data['egg']                = Egg::where('egg_id', $data[0]['egg_id'])->firstOrFail();
            $data['docker_image']       = $data[0]['image'];
            $data['egg_variables']      = [];
            $data['egg_variables_meta'] = [];
            Server::create($data);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function SyncServerDelete(array $data): void
    {
        try {
            $server = Server::where('uuid', $data[0]['uuid'])->firstOrFail();
            $server->deleteOrFail();
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function SyncUserCreate(array $data): void
    {
        try {
            $data['panel_user_id'] = $data[0]['id'];
            $data['name'] = $data[0]['username'];
            $data['email'] = $data[0]['email'];
            $data['password'] = bcrypt(Str::random(10));
            $data['lang'] = $data[0]['language'];
            $data['timezone'] = $data[0]['timezone'];
            User::create($data);
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }

    public function SyncUserDelete(array $data): void
    {
        try {
            $user = User::where('panel_user_id', $data[0]['id'])->firstOrFail();
            $user->deleteOrFail();
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }
    }
}
