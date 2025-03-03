<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SelfUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'self:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '最新コードに更新します';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('アップデートを開始します...');

        if (!$this->runProcess(
            ['chown', '-R', 'ichiru:ichiru', '/var/www/panel_ctrl'],
            'chownに失敗しました',
            'chownが成功しました'
        )) {
            return 1;
        }

        if (!$this->runProcess(
            ['su', 'ichiru', '-c', 'php artisan down'],
            'メンテナンスモードに失敗しました',
            'メンテナンスモードに成功しました'
        )) {
            return 1;
        }

        if (!$this->runProcess(
            ['su', 'ichiru', '-c', 'git pull'],
            'Git pullに失敗しました',
            'Git pullが成功しました'
        )) {
            return 1;
        }

        if (!$this->runProcess(
            ['su', 'ichiru', '-c', 'composer install --no-dev --optimize-autoloader'],
            'Composer installに失敗しました',
            'Composer installが成功しました'
        )) {
            return 1;
        }

        if (!$this->runProcess(
            ['su', 'ichiru', '-c', 'php artisan config:clear'],
            'config:clearに失敗しました',
            'config:clearが成功しました'
        )) {
            return 1;
        }

        if (!$this->runProcess(
            ['su', 'ichiru', '-c', 'php artisan config:cache'],
            'config:cacheに失敗しました',
            'config:cacheが成功しました'
        )) {
            return 1;
        }

        if (!$this->runProcess(
            ['su', 'ichiru', '-c', 'php artisan migrate --force'],
            'データベースのマイグレーションに失敗しました',
            'データベースのマイグレーションが成功しました'
        )) {
            return 1;
        }

        if (!$this->runProcess(
            ['su', 'ichiru', '-c', 'php artisan optimize:clear'],
            'optimize:clearに失敗しました',
            'optimize:clearが成功しました'
        )) {
            return 1;
        }

        if (!$this->runProcess(
            ['chown', '-R', 'nginx:nginx', '/var/www/panel_ctrl'],
            'chownに失敗しました',
            'chownが成功しました'
        )) {
            return 1;
        }

        if (!$this->runProcess(
            ['php', 'artisan', 'up'],
            'メンテナンスモード解除に失敗しました',
            'メンテナンスモード解除が成功しました'
        )) {
            return 1;
        }

        $this->info('アップデートが完了しました。');
        return 0;
    }

    /**
     * @param array  $command
     * @param string $errorMessage
     * @param string $successMessage
     * @return bool
     */
    private function runProcess(array $command, string $errorMessage, string $successMessage): bool
    {
        $process = new Process($command);
        $process->setWorkingDirectory(base_path());
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error($errorMessage . ': ' . $process->getErrorOutput());
            return false;
        }

        $this->info($successMessage);
        return true;
    }
}
