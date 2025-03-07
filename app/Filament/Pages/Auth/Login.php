<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use Illuminate\Support\Facades\Auth;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Form;
use App\Models\User;
use PragmaRX\Google2FA\Google2FA;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.Login';

    public ?string $name = null;

    public ?string $password = null;

    public ?string $one_time_password = null;

    public bool $remember = false;

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('ユーザー名')
                ->required()
                ->autocomplete('username'),
            TextInput::make('password')
                ->label('パスワード')
                ->password()
                ->required()
                ->autocomplete('current-password'),
            TextInput::make('one_time_password')
                ->label('認証コード')
                ->visible(fn () => User::where('name', $this->name)->first()?->google2fa_enabled)
                ->reactive(),
            Checkbox::make('remember')
                ->label('ログイン状態を保持する'),
        ]);
    }

    public function mount(): void
    {
        parent::mount();
        
        $this->form->fill([
            'name' => $this->name,
            'password' => $this->password,
            'one_time_password' => $this->one_time_password,
            'remember' => $this->remember,
        ]);
    }

    public function onSubmit($data)
    {
        $this->name = $data['name'];
        $this->password = $data['password'];
        $this->one_time_password = $data['one_time_password'] ?? null;
        $this->remember = $data['remember'] ?? false;
    }

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        
        $this->name = $data['name'];
        $this->password = $data['password'];
        $this->one_time_password = $data['one_time_password'] ?? null;
        $this->remember = $data['remember'] ?? false;
        unset($data['remember']);

        $this->validate();
        $user = User::where('name', $data['name'])->first();

        if (Auth::guard(config('filament.auth.guard'))->attempt(['name' => $data['name'], 'password' => $data['password']], $this->remember)) {
            if ($user->google2fa_enabled) {
                if (empty($this->one_time_password)) {
                    return null;
                }
                $google2fa = new Google2FA();
                $valid = $google2fa->verifyKey(
                    $user->google2fa_secret,
                    $this->one_time_password
                );
                if (!$valid) {
                    Auth::guard(config('filament.auth.guard'))->logout();
                    Notification::make()
                        ->title('認証コードが無効です')
                        ->danger()
                        ->send();
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
        } else {
            Notification::make()
                ->title('ログインに失敗しました')
                ->body('ユーザー名またはパスワードが間違っています。')
                ->danger()
                ->send();
            return new class('/admin/login') implements LoginResponse {
                protected string $redirectUrl;

                public function __construct(string $url)
                {
                    $this->redirectUrl = $url;
                }

                public function toResponse($request)
                {
                    return redirect($this->redirectUrl);
                }
            };
        }
    }
}
