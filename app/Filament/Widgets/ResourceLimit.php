<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Node;
use App\Models\Server;
use App\Func\NumberConverter;

class ResourceLimit extends BaseWidget
{
    public static function canView(): bool
    {
        $controller = request()->route()?->getController();

        if ($controller && str_contains(get_class($controller), 'Dashboard')) {
            return false;
        }

        return true;
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $resourceLimits = $user->resource_limits ?? [];
        $stats = [];
        $unit = $user->unit;
        foreach ($resourceLimits as $limit) {
            $node = Node::where('node_id', $limit['node_key'])->first();
            if (!$node || (int)$node->public === 0) {
                continue;
            }
            $nodeName = $node->name ?? 'ノード '.$limit['node_key'];
            $servers = Server::where('user', $user->id)
                ->where('node', $limit['node_key'])
                ->get();
            $used_cpu = 0;
            $used_memory = 0;
            $used_disk = 0;
            foreach ($servers as $server) {
                $limits = $server->limits;
                $used_cpu += $limits['cpu'] ?? 0;
                $used_memory += $limits['memory'] ?? 0;
                $used_disk += $limits['disk'] ?? 0;
            }
            $stats[] = Stat::make("{$nodeName} - CPU", "利用中: ".NumberConverter::convertCpuCore($used_cpu)." コア")
                ->description("制限: ".($limit['max_cpu'] === -1 ? "無制限" : NumberConverter::convertCpuCore($limit['max_cpu'])." コア"))
                ->icon('heroicon-o-cpu-chip')
                ->color('');
            $stats[] = Stat::make("{$nodeName} - メモリ", "利用中: ".NumberConverter::convert($used_memory, 'MiB', $unit, true, 0))
                ->description("制限: ".($limit['max_memory'] === -1 ? "無制限" : NumberConverter::convert($limit['max_memory'], 'MiB', $unit, true, 2)))
                ->icon('heroicon-o-server')
                ->color('');
            $stats[] = Stat::make("{$nodeName} - ディスク", "利用中: " . NumberConverter::convert($used_disk, 'MiB', $unit, true, 0))
                ->description("制限: ".($limit['max_disk'] === -1 ? "無制限" : NumberConverter::convert($limit['max_disk'], 'MiB', $unit, true, 2)))
                ->icon('heroicon-o-circle-stack')
                ->color('');
        }
        return $stats;
    }

    protected static function shouldPoll(): bool
    {
        return true;
    }
}
