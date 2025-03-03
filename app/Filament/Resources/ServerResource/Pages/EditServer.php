<?php

namespace App\Filament\Resources\ServerResource\Pages;

use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\EditRecord;
use App\Services\ServerApiService;
use App\Models\Server;
use App\Filament\Resources\ServerResource;

class EditServer extends EditRecord
{
    protected static string $resource = ServerResource::class;

    public function mount($record): void
    {
        if (!auth()->user()->can('servers.edit')) {
            abort(403);
        }
        parent::mount($record);
    }

    public function getTitle(): string
    {
        return 'サーバー編集';
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Server $record */
        $apiService = new ServerApiService();
        $apiService->updateServer($record);
        return $record;
    }

    public function update(bool $another = false): void
    {
        $data = $this->form->getState();
        $record = $this->handleRecordUpdate($this->record, $data);
        redirect()->to(ServerResource::getUrl('edit', ['record' => $record->getKey()]));
    }
}
