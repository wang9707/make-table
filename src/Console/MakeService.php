<?php

namespace Wang9707\MakeTable\Console;

use Illuminate\Console\Command;
use Wang9707\MakeTable\Console\Traits\Make;
use Wang9707\MakeTable\Support\Table;

class MakeService extends Command
{
    use Make;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wang:make:service
                            {table : table name or model class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create service';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $target = $this->resolveTarget($this->argument('table'));

        $model    = $this->modelName($target);
        $savePath = $this->pathFor('service') . DIRECTORY_SEPARATOR . "{$model}Service.php";

        if (!$this->check($target, $savePath)) {
            return;
        }

        $file = strtr($this->stub('Service'), [
            '{{serviceNamespace}}' => $this->namespaceFor('service'),
            '{{model}}'            => $model,
            '{{modelFqcn}}'        => $this->fqcn('model', $model),
        ]);

        $this->saveFile($savePath, $file);
    }
}
