<?php

namespace App\Filament\Resources\UserResource\Pages;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\ServerApiService;
use Exception;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    public function mount(): void
    {
        if (!auth()->user()->can('user.create')) {
            abort(403);
        }
        parent::mount();
    }

    public function getTitle(): string
    {
        return 'ユーザー作成';
    }

    protected function handleRecordCreation(array $data): Model
    {
        DB::beginTransaction();
        /** @var User $user */
        $user = parent::handleRecordCreation($data);
        try {
            $res = (new ServerApiService())->CreateUser($user);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e);
            throw $e;
        }
        $user->panel_user_id = $res['attributes']['id'];
        $user->lang = $res['attributes']['language'];
        $user->save();
        DB::commit();
        activity()
            ->performedOn($user)
            ->causedBy(auth()->user())
            ->withProperties(['level' => 'info'])
            ->log('ユーザーを作成しました');
        Notification::make()
            ->title('ユーザーを作成しました')
            ->success()
            ->send();
        return $user;
    }

    public function create(bool $another = false): void
    {
        try {
            /** @var User $record */
            $record = $this->handleRecordCreation($this->data);
            $this->redirect(UserResource::getUrl('edit', ['record' => $record->id]));
        } catch (Exception $e) {
            Log::error($e);
            Notification::make()
                ->title('ユーザー作成に失敗しました')
                ->body($e->getMessage())
                ->danger()
                ->send();
            $this->redirect(UserResource::getUrl('index'));
        }
    }
}
