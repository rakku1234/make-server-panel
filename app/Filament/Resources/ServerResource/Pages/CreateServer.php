<?php

namespace App\Filament\Resources\ServerResource\Pages;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Allocation;
use App\Models\Server;
use App\Services\ServerApiService;
use App\Filament\Resources\ServerResource;
use Spatie\DiscordAlerts\DiscordAlert;
use Exception;

class CreateServer extends CreateRecord
{
    protected static string $resource = ServerResource::class;

    public function mount(): void
    {
        if (!auth()->user()->can('server.create')) {
            abort(403);
        }
        parent::mount();
    }

    public function getTitle(): string
    {
        return 'サーバー作成';
    }

    protected function handleRecordCreation(array $data): Model
    {
        DB::beginTransaction();
        /** @var Server $record */
        $record = parent::handleRecordCreation($data);
        try {
            $res = (new ServerApiService())->CreateServer($record);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
        Allocation::query()->where('id', $record->getAttribute('allocation_id'))->update(['assigned' => 1]);
        $record->update([
            'uuid' => $res['server']['attributes']['uuid'],
            'status' => $res['server']['attributes']['status'],
        ]);
        DB::commit();
        activity()
            ->performedOn($record)
            ->causedBy(auth()->user())
            ->withProperties(['level' => 'info'])
            ->log('サーバーを作成しました');
        Notification::make()
            ->title('サーバー作成に成功しました')
            ->success()
            ->send();
        if (config('discord-alerts.webhook_urls.default')) {
            (new DiscordAlert())->to(config('discord-alerts.webhook_urls.default'))
            ->message("", [
                [
                    "title" => "サーバー作成に成功しました",
                    "description" => "{$record->name} を作成しました\nインストール完了までお待ち下さい",
                    "color" => 0x00ff00,
                ]
            ]);
        }
        return $record;
    }

    public function create(bool $another = false): void
    {
        $data = $this->form->getState();
        $data['start_on_completion'] = isset($data['start_on_completion']) ? 1 : 0;
        try {
            $record = $this->handleRecordCreation($data);
        } catch (Exception $e) {
            Log::error($e);
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();
            $this->redirect(ServerResource::getUrl('index'));
            return;
        }
        if (auth()->user()->can('server.edit')) {
            /** @var Server $record */
            $this->redirect(ServerResource::getUrl('edit', ['record' => $record->slug]));
        } else {
            $this->redirect(ServerResource::getUrl('index'));
        }
    }
}
