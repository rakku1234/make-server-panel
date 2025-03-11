<?php

namespace App\Filament\Pages;

use Illuminate\Support\Facades\File;
use Filament\Pages\Page;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

class Settings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.settings';
    protected static ?string $navigationLabel = '設定';
    protected static ?string $navigationGroup = 'パネル管理';
    protected static ?int $navigationSort = 3;
    public ?array $data = [];

    public function getTitle(): string
    {
        return '設定';
    }

    public function mount(): void
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403);
        }
        $env = File::get(base_path('.env'));
        $lines = explode("\n", $env);
        foreach ($lines as $line) {
            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $this->data[trim($key)] = trim($value);
            }
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('APP_NAME')
                    ->label('アプリケーション名'),
                TextInput::make('APP_URL')
                    ->label('アプリケーションURL'),
                TextInput::make('PANEL_API_URL')
                    ->label('パネルAPI URL'),
                TextInput::make('PANEL_API_APPLICATION_TOKEN')
                    ->label('パネルAPIアプリケーショントークン')
                    ->password()
                    ->revealable(),
                TextInput::make('PANEL_API_CLIENT_TOKEN')
                    ->label('パネルAPIクライアントトークン')
                    ->password()
                    ->revealable(),
                Toggle::make('TURNSTILE_ENABLE')
                    ->label('Cloudflare Turnstileを有効にする')
                    ->live(),
                TextInput::make('TURNSTILE_SITE_KEY')
                    ->label('Cloudflare Turnstileサイトキー')
                    ->password()
                    ->revealable()
                    ->visible($this->data['TURNSTILE_ENABLE']),
                TextInput::make('TURNSTILE_SECRET_KEY')
                    ->label('Cloudflare Turnstileシークレットキー')
                    ->password()
                    ->revealable()
                    ->visible($this->data['TURNSTILE_ENABLE']),
                Select::make('TRANSLATOR_SERVICE')
                    ->label('翻訳サービス')
                    ->options([
                        'Microsoft' => 'Microsoft',
                        'DeepL' => 'DeepL',
                    ])
                    ->live(),
                TextInput::make('TRANSLATOR_KEY')
                    ->label('翻訳キー')
                    ->password()
                    ->revealable()
                    ->visible($this->data['TRANSLATOR_SERVICE']),
                TextInput::make('TRANSLATOR_REGION')
                    ->label('翻訳リージョン')
                    ->visible($this->data['TRANSLATOR_SERVICE'] === 'Microsoft'),
                TextInput::make('DISCORD_WEBHOOK_URL')
                    ->label('Discord Webhook URL')
                    ->password(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $env = File::get(base_path('.env'));
        foreach ($this->data as $key => $value) {
            $env = preg_replace(
                "/^{$key}=.*/m",
                "{$key}={$value}",
                $env
            );
        }
        File::put(base_path('.env'), $env);
        Notification::make()
            ->success()
            ->title('設定を保存しました')
            ->send();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('admin');
    }
}
