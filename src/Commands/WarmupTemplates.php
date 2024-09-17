<?php declare(strict_types=1);

namespace Schnoop\RemoteTemplate\Commands;

use Exception;
use Illuminate\Console\Command;

class WarmupTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'remote-template:warmup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warmup all configured templates.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): int
    {
        $fileViewFinder = app('view.finder');

        foreach (config('remote-view.hosts') as $host => $config) {
            foreach ($config['mapping'] as $name => $url) {
                try {
                    $result = $fileViewFinder->find('remote:'.$host.'::'.$name);
                    $this->comment($result);
                } catch (Exception) {
                    $this->error('Das war nix.');
                }
            }
        }

        return 0;
    }
}
