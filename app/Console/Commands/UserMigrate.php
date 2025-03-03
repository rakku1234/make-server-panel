<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Services\ServerApiService;
use App\Models\User;
use Exception;

class UserMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ユーザー移行';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apiService = new ServerApiService();
        $userlist = $apiService->getUserlist();
        if ($userlist === null) {
            $this->error('ユーザーリストの取得に失敗しました。');
            return;
        }
        $userlist = $userlist['data'];

        foreach ($userlist as $userData) {
            $attributes = $userData['attributes'];
            $randomPassword = Str::random(10);

            if (User::where('email', $attributes['email'])
                     ->where('name', $attributes['username'])
                     ->exists()) {
                continue;
            }

            DB::beginTransaction();

            try {
                $createdUser = User::create([
                    'panel_user_id'    => $attributes['id'],
                    'name'       => $attributes['username'],
                    'email'      => $attributes['email'],
                    'password'   => bcrypt($randomPassword),
                    'created_at' => $attributes['created_at'],
                    'updated_at' => $attributes['updated_at'],
                ]);
            } catch (Exception $e) {
                $this->info('ユーザー: ' . $attributes['username'] . ' の移行に失敗しました。');
                DB::rollBack();
                continue;
            }

            if ($attributes['root_admin']) {
                $createdUser->assignRole('admin');
            } else {
                $createdUser->assignRole('user');
            }

            DB::commit();

            $this->info('ユーザー: ' . $attributes['username'] . ' のパスワードは: ' . $randomPassword . ' です。');
        }

        $this->info('ユーザーの移行が完了しました。');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasPermissionTo('servers.import');
    }
}
