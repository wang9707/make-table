<?php

namespace Wang9707\MakeTable\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Wang9707\MakeTable\Tests\Fixtures\User;
use Wang9707\MakeTable\Tests\TestCase;

class MakeCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/make-table-' . uniqid();

        config([
            'make-table.model.path'      => $this->dir . '/Models',
            'make-table.service.path'    => $this->dir . '/Services',
            'make-table.controller.path' => $this->dir . '/Http/Controllers',
            'make-table.request.path'    => $this->dir . '/Http/Requests',
            'make-table.filter.path'     => $this->dir . '/Models/Filter',
        ]);

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->tinyInteger('status');
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);

        parent::tearDown();
    }

    public function test_make_all_generates_files()
    {
        $this->artisan('wang:make:all', ['table' => 'users'])->run();

        $this->assertFileExists($this->dir . '/Models/Users.php');
        $this->assertFileExists($this->dir . '/Services/UsersService.php');
        $this->assertFileExists($this->dir . '/Http/Controllers/UsersController.php');
        $this->assertFileExists($this->dir . '/Http/Requests/UsersRequest.php');
        $this->assertFileExists($this->dir . '/Models/Filter/UsersFilter.php');

        $model = file_get_contents($this->dir . '/Models/Users.php');

        $this->assertStringContainsString('namespace App\\Models;', $model);
        $this->assertStringContainsString("protected \$table = 'users';", $model);
        $this->assertStringContainsString('use HasFactory;', $model);
        // 表中没有 deleted_at，不使用软删除
        $this->assertStringNotContainsString('SoftDeletes', $model);
        // filter 作用域的 @method 注解，方便 IDE
        $this->assertStringContainsString('@method static \Illuminate\Database\Eloquent\Builder<Users> filter(', $model);

        $service = file_get_contents($this->dir . '/Services/UsersService.php');
        $this->assertStringContainsString('public $model = \App\Models\Users::class;', $service);
        $this->assertStringContainsString('@extends Service<\App\Models\Users>', $service);

        $controller = file_get_contents($this->dir . '/Http/Controllers/UsersController.php');
        $this->assertStringContainsString('use App\\Http\\Requests\\UsersRequest;', $controller);
        $this->assertStringContainsString('public function store(UsersRequest $request)', $controller);
    }

    public function test_soft_deletes_added_when_column_exists()
    {
        Schema::table('users', function ($table) {
            $table->softDeletes();
        });

        $this->artisan('wang:make:model', ['table' => 'users'])->run();

        $model = file_get_contents($this->dir . '/Models/Users.php');

        $this->assertStringContainsString('use HasFactory, SoftDeletes;', $model);
    }

    public function test_filter_style_none_by_default()
    {
        $this->artisan('wang:make:filter', ['table' => 'users'])->run();

        $filter = file_get_contents($this->dir . '/Models/Filter/UsersFilter.php');

        $this->assertStringContainsString('class UsersFilter extends QueryFilter', $filter);
        $this->assertStringNotContainsString('#[Filter', $filter);
        $this->assertStringNotContainsString('public function', $filter);
    }

    public function test_filter_style_methods()
    {
        config(['make-table.filter.style' => 'methods']);

        $this->artisan('wang:make:filter', ['table' => 'users'])->run();

        $filter = file_get_contents($this->dir . '/Models/Filter/UsersFilter.php');

        $this->assertStringContainsString('public function name($name)', $filter);
        $this->assertStringContainsString("where('name', \$name)", $filter);
    }

    public function test_filter_style_attributes()
    {
        config(['make-table.filter.style' => 'attributes']);

        $this->artisan('wang:make:filter', ['table' => 'users'])->run();

        $filter = file_get_contents($this->dir . '/Models/Filter/UsersFilter.php');

        $this->assertStringContainsString('use Wang9707\\MakeTable\\Attributes\\Filter;', $filter);
        $this->assertStringContainsString("#[Filter('name', 'like')]", $filter);
        $this->assertStringContainsString('public ?string $name = null;', $filter);
        $this->assertStringContainsString("#[Filter('status', '=')]", $filter);
        $this->assertStringContainsString('public ?int $status = null;', $filter);
        // 日期列使用 Carbon
        $this->assertStringContainsString('use Illuminate\\Support\\Carbon;', $filter);
        $this->assertStringContainsString('public ?Carbon $createdAt = null;', $filter);
    }

    public function test_request_rules_follow_nullable_and_type()
    {
        $this->artisan('wang:make:request', ['table' => 'users'])->run();

        $request = file_get_contents($this->dir . '/Http/Requests/UsersRequest.php');

        $this->assertStringContainsString("'name' => 'required|string'", $request);
        $this->assertStringContainsString("'email' => 'nullable|string'", $request);
        $this->assertStringContainsString("'status' => 'required|integer'", $request);
        // 自增主键和时间戳不生成规则
        $this->assertStringNotContainsString("'id' =>", $request);
        $this->assertStringNotContainsString("'created_at' =>", $request);
    }

    public function test_accepts_model_class()
    {
        $this->artisan('wang:make:model', ['table' => User::class])->run();

        $this->assertFileExists($this->dir . '/Models/User.php');

        $model = file_get_contents($this->dir . '/Models/User.php');
        $this->assertStringContainsString("protected \$table = 'users';", $model);
    }

    public function test_does_not_overwrite_existing_file()
    {
        $this->artisan('wang:make:model', ['table' => 'users'])->run();

        $path     = $this->dir . '/Models/Users.php';
        $original = file_get_contents($path);

        $this->artisan('wang:make:model', ['table' => 'users'])
            ->expectsOutputToContain('file already exist')
            ->run();

        $this->assertSame($original, file_get_contents($path));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
