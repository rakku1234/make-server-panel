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
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Symfony\Component\Intl\Languages;
use DateTimeZone;
use PragmaRX\Google2FA\Google2FA;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\HtmlString;

class Profile extends BaseProfile implements HasForms
{
    use InteractsWithForms;

    public $name;

    public $email;

    public $password;

    public $timezone;

    public $unit;

    public $lang;

    public $google2fa_enabled;

    public $google2fa_secret;

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
        $this->google2fa_enabled = $user->google2fa_enabled;
        if (!$user->google2fa_secret) {
            $google2fa = new Google2FA();
            $this->google2fa_secret = $google2fa->generateSecretKey();
        } else {
            $this->google2fa_secret = $user->google2fa_secret;
        }
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
                        ->default($this->lang)
                        ->dehydrated(fn ($state) => filled($state)),
                    Select::make('timezone')
                        ->label('タイムゾーン')
                        ->options(collect(DateTimeZone::listIdentifiers())->mapWithKeys(fn ($timezone) => [$timezone => $timezone]))
                        ->default($this->timezone),
                ]),
                Tab::make('2要素認証')->schema([
                    Toggle::make('google2fa_enabled')
                        ->label('2要素認証を有効にする')
                        ->default($this->google2fa_enabled)
                        ->live(),
                    Placeholder::make('qr_code')
                        ->label('QRコード')
                        ->visible(fn () => $this->google2fa_enabled)
                        ->content(function () {
                            $google2fa = new Google2FA();
                            $qrCodeUrl = $google2fa->getQRCodeUrl(
                                config('app.name'),
                                auth()->user()->email,
                                $this->google2fa_secret
                            );
                            $qrCode = new QrCode($qrCodeUrl);
                            $writer = new PngWriter();
                            $result = $writer->write($qrCode);
                            return new HtmlString(
                        '<div class="space-y-2">
                                    <div class="flex justify-center">
                                        <img src="'.$result->getDataUri().'" alt="2FA QR Code" class="max-w-[200px]">
                                    </div>
                                </div>'
                            );
                        }),
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
        if ($data['google2fa_enabled'] && !$user->google2fa_secret) {
            $data['google2fa_secret'] = $this->google2fa_secret;
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
