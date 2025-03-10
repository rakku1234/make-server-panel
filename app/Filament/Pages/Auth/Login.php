<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Illuminate\Support\Facades\Auth;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Checkbox;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;
use Coderflex\FilamentTurnstile\Forms\Components\Turnstile;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.Login';
    public ?array $data = [];
    public bool $show2fa = false;

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        TextInput::make('name')
                            ->label('ユーザー名')
                            ->required()
                            ->autocomplete('username'),
                        TextInput::make('password')
                            ->label('パスワード')
                            ->password()
                            ->revealable()
                            ->required()
                            ->autocomplete('current-password'),
                        TextInput::make('one_time_password')
                            ->label('認証コード')
                            ->visible($this->show2fa)
                            ->reactive(),
                        Checkbox::make('remember')
                            ->label('ログイン状態を保持する'),
                        Turnstile::make('captcha')
                            ->theme('auto')
                            ->visible((bool)config('services.turnstile.enable')),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    public function authenticate(): ?LoginResponse
    {
        $this->validate();
        $user = User::where('name', $this->data['name'])->first();
        if (Auth::guard(config('filament.auth.guard'))->attempt([
            'name' => $this->data['name'],
            'password' => $this->data['password']
        ], $this->data['remember'])) {
            if ($user->google2fa_enabled) {
                if (!$this->show2fa) {
                    Auth::guard(config('filament.auth.guard'))->logout();
                    $this->show2fa = true;
                    $this->dispatch('reset-captcha');
                    return null;
                }
                if (empty($this->data['one_time_password'])) {
                    Auth::guard(config('filament.auth.guard'))->logout();
                    Notification::make()
                        ->title('認証コードが必要です')
                        ->danger()
                        ->send();
                    $this->dispatch('reset-captcha');
                    return null;
                }
                $valid = (new Google2FA())->verifyKey(
                    $user->google2fa_secret,
                    $this->data['one_time_password']
                );
                if (!$valid) {
                    Auth::guard(config('filament.auth.guard'))->logout();
                    Notification::make()
                        ->title('認証コードが無効です')
                        ->danger()
                        ->send();
                    $this->dispatch('reset-captcha');
                    return null;
                }
            }
            activity()
                ->causedBy($user)
                ->withProperties([
                    'level' => 'info',
                ])
                ->log('ログインしました');
            return app(LoginResponse::class);
        }

        Notification::make()
            ->title('ログインに失敗しました')
            ->body('ユーザー名またはパスワードが間違っています。')
            ->danger()
            ->send();

        $this->dispatch('reset-captcha');
        return null;
    }
}
