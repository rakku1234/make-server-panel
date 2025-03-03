<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\EditProfile as BaseProfile;
use Filament\Notifications\Notification;
use Filament\Pages\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Symfony\Component\Intl\Languages;
use DateTimeZone;

class Profile extends BaseProfile implements HasForms
{
    use InteractsWithForms;

    public $name;

    public $email;

    public $password;

    public $timezone;

    public $unit;

    public $lang;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.auth.profile';

    protected function getActions(): array
    {
        return [
            Action::make('back')
                ->label('ダッシュボードに戻る')
                ->url('/admin')
                ->icon('heroicon-o-arrow-left'),
        ];
    }

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->timezone = $user->timezone;
        $this->unit = $user->unit;
        $this->lang = $user->lang;
    }

    protected function getFormSchema(): array
    {
        return [
            Tabs::make('Profile')->tabs([
                Tab::make('基本情報')->schema([
                    TextInput::make('name')
                        ->label('ユーザー名')
                        ->required(),
                    TextInput::make('email')
                        ->label('メールアドレス')
                        ->email()
                        ->required(),
                    TextInput::make('password')
                        ->label('パスワード')
                        ->password()
                        ->dehydrated(fn ($state) => filled($state)),
                ]),
                Tab::make('表示設定')->schema([
                    ToggleButtons::make('unit')
                        ->label('表示単位')
                        ->options([
                            'auto' => 'MB・GB',
                            'iauto' => 'MiB・GiB',
                        ])
                        ->default('auto')
                        ->inline(),
                    Select::make('lang')
                        ->label('言語')
                        ->options(
                            collect(Languages::getNames())->mapWithKeys(fn ($name, $code) => [$code => $name])
                        )
                        ->default($this->lang),
                    Select::make('timezone')
                        ->label('タイムゾーン')
                        ->options(collect(DateTimeZone::listIdentifiers())->mapWithKeys(fn ($timezone) => [$timezone => $timezone]))
                        ->default($this->timezone),
                ]),
            ]),
        ];
    }

    public function submit()
    {
        $data = $this->form->getState();
        $user = auth()->user();
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);
        activity()
            ->causedBy($user)
            ->withProperties([
                'level' => 'info',
            ])
            ->log('プロフィールを更新しました');
        Notification::make()
            ->title('プロフィールが更新されました。')
            ->success()
            ->send();
    }
}
