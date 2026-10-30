<?php

namespace Wang9707\MakeTable\Console;

use Illuminate\Console\Command;
use Wang9707\MakeTable\Console\Traits\Make;
use Wang9707\MakeTable\Support\Table;

class MakeController extends Command
{
    use Make;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wang:make:controller
                            {table : table name or model class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'create controller';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $target = $this->resolveTarget($this->argument('table'));

        $model    = $this->modelName($target);
        $savePath = $this->pathFor('controller') . DIRECTORY_SEPARATOR . "{$model}Controller.php";

        if (!$this->check($target, $savePath)) {
            return;
        }

        $file = strtr($this->stub('Controller'), [
            '{{controllerNamespace}}' => $this->namespaceFor('controller'),
            '{{model}}'               => $model,
            '{{filterFqcn}}'          => $this->fqcn('filter', $model . 'Filter'),
            '{{requestFqcn}}'         => $this->fqcn('request', $model . 'Request'),
            '{{serviceFqcn}}'         => $this->fqcn('service', $model . 'Service'),
        ]);

        $this->saveFile($savePath, $file);
    }
}
