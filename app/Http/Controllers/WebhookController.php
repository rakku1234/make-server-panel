<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ServerWebhook;

class WebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $serverWebhook = new ServerWebhook();
        if ($request->header('x-webhook-event') === 'eloquent.created: App\Models\Server') {
            $serverWebhook->SyncCreate($request->json()->all());
        }
        if ($request->header('x-webhook-event') === 'eloquent.updated: App\Models\Server') {
            //$serverWebhook->SyncUpdate($request->json()->all());
        }
        if ($request->header('x-webhook-event') === 'eloquent.deleted: App\Models\Server') {
            $serverWebhook->SyncDelete($request->json()->all());
        }
        return response()->noContent();
    }
}
