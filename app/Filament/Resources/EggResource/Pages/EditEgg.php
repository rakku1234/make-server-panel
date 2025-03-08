<?php

namespace App\Filament\Resources\EggResource\Pages;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Filament\Resources\EggResource;
use Exception;

class EditEgg extends EditRecord
{
    protected static string $resource = EggResource::class;

    public function mount($record): void
    {
        if (!auth()->user()->can('egg.edit')) {
            abort(403);
        }
        parent::mount($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     *
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (isset($data['egg_url'])) {
            try {
                $response = Http::get($data['egg_url']);
                if ($response->successful()) {
                    $eggData = $response->json();
                    $data['egg_variables'] = $eggData['variables'];
                }
            } catch (Exception $e) {
                Notification::make()
                    ->title('エラーが発生しました')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
                return $record;
            }
        }
        $record->update($data);
        return $record;
    }

    public function getTitle(): string
    {
        return 'Egg編集';
    }
}
