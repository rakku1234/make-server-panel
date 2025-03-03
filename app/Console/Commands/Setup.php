<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class Setup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'setup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (File::exists(base_path('.env'))) {
            $this->info('.env ファイルは既に存在します。');
            return 0;
        }
        if (!File::exists(base_path('.env.example'))) {
            $this->error('.env.example ファイルが見つかりません。');
            return 1;
        }
        File::copy(base_path('.env.example'), base_path('.env'));
        $this->call('key:generate');
        $this->info('.env ファイルの生成が完了しました。');
        return 0;
    }
}
