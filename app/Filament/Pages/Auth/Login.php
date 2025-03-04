<?php

namespace App\Filament\Pages\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use App\Models\User;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.auth.Login';

    public function authenticate(): ?LoginResponse
    {
        $request = request();
        $data = $request->validate([
            'name'                  => 'required|string',
            'password'              => 'required|string',
            'cf-turnstile-response' => 'nullable|string',
            'remember'              => 'nullable|string',
        ]);

        $secret = config('services.turnstile.secret');
        if (!empty($secret)) {
            $cfTurnstileResponse = $data['cf-turnstile-response'];
            $verificationResponse = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret'   => $secret,
                'response' => $cfTurnstileResponse,
                'remoteip' => $request->ip(),
            ]);
            $verificationResult = $verificationResponse->json();
            if (!isset($verificationResult['success']) || !$verificationResult['success']) {
                Notification::make()
                    ->title('ログインに失敗しました')
                    ->body('セキュリティチェックに失敗しました。もう一度お試しください。')
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
        unset($data['cf-turnstile-response']);
        $remember = $data['remember'] ?? false;
        unset($data['remember']);
        $user = User::where('name', $data['name'])->first();

        if (Auth::guard(config('filament.auth.guard'))->attempt($data, $remember)) {
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
