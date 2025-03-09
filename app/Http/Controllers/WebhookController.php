<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\ServerWebhook;

class WebhookController extends Controller
{
    private array $eventHandlers = [
        'eloquent.created: App\Models\Node'       => 'SyncNodeCreate',
        'eloquent.deleted: App\Models\Node'       => 'SyncNodeDelete',
        'eloquent.created: App\Models\Allocation' => 'SyncAllocationCreate',
        'eloquent.deleted: App\Models\Allocation' => 'SyncAllocationDelete',
        'eloquent.created: App\Models\Egg'        => 'SyncEggCreate',
        'eloquent.deleted: App\Models\Egg'        => 'SyncEggDelete',
        'eloquent.created: App\Models\Server'     => 'SyncServerCreate',
        'eloquent.deleted: App\Models\Server'     => 'SyncServerDelete',
        'eloquent.created: App\Models\User'       => 'SyncUserCreate',
        'eloquent.deleted: App\Models\User'       => 'SyncUserDelete',
    ];

    public function handleWebhook(Request $request): Response
    {
        $webhook = new ServerWebhook();
        $event = $request->header('x-webhook-event');
        if (isset($this->eventHandlers[$event])) {
            $method = $this->eventHandlers[$event];
            $webhook->$method($request->json()->all());
        }
        return response()->noContent();
    }
}
