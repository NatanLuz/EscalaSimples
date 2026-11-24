<?php

use App\Models\EmployeeWorkPattern;
use App\Models\ScheduleOverride;
use App\Models\User;
use App\Models\WorkPattern;
use App\Services\ScheduleCalculator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $exception): void {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage().PHP_EOL);
    exit(1);
});
if (DB::getDefaultConnection() !== 'mysql' || ! in_array(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost'], true)
    || DB::connection()->getConfig('url') || DB::connection()->getConfig('read') || DB::connection()->getConfig('write')
    || DB::selectOne('select database() as db')->db !== 'escalasimples_test') {
    throw new RuntimeException('Este teste exige o MariaDB local e o banco escalasimples_test.');
}
$check = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$reject = function (callable $operation, string $message): void {
    try {
        $operation();
    } catch (QueryException) {
        return;
    }
    throw new RuntimeException($message);
};
$tag = (string) Str::ulid();
DB::beginTransaction();
try {
    $one = User::factory()->create(['email' => 'constraints-'.strtolower($tag).'@example.test']);
    $two = User::factory()->create(['email' => 'constraints-outro-'.strtolower($tag).'@example.test']);
    $employee = $one->company->employees()->create(['name' => 'Pessoa fictícia', 'is_active' => true]);
    $foreign = $two->company->employees()->create(['name' => 'Pessoa externa fictícia', 'is_active' => true]);
    $pattern = new WorkPattern(['name' => 'Padrão fictício', 'is_active' => true]);
    $pattern->company_id = $one->company_id;
    $pattern->save();
    $version = $pattern->versions()->create(['company_id' => $one->company_id, 'kind' => 'cycle', 'sequence' => [true, false], 'starts_at' => '19:00', 'ends_at' => '07:00']);
    $otherPattern = new WorkPattern(['name' => 'Padrão externo fictício', 'is_active' => true]);
    $otherPattern->company_id = $two->company_id;
    $otherPattern->save();
    $otherVersion = $otherPattern->versions()->create(['company_id' => $two->company_id, 'kind' => 'cycle', 'sequence' => [true, false]]);
    $assignment = EmployeeWorkPattern::create(['company_id' => $one->company_id, 'employee_id' => $employee->id, 'work_pattern_version_id' => $version->id, 'reference_date' => '2024-02-28', 'starts_on' => '2024-02-28']);
    $reject(fn () => EmployeeWorkPattern::create(['public_id' => $assignment->public_id, 'company_id' => $one->company_id, 'employee_id' => $employee->id, 'work_pattern_version_id' => $version->id, 'reference_date' => '2024-02-28', 'starts_on' => '2024-02-28']), 'ULID duplicado foi aceito.');
    $reject(fn () => EmployeeWorkPattern::create(['company_id' => $one->company_id, 'employee_id' => $foreign->id, 'work_pattern_version_id' => $version->id, 'reference_date' => '2024-02-28', 'starts_on' => '2024-02-28']), 'Colaborador externo foi aceito.');
    $reject(fn () => EmployeeWorkPattern::create(['company_id' => $one->company_id, 'employee_id' => $employee->id, 'work_pattern_version_id' => 999999999, 'reference_date' => '2024-02-28', 'starts_on' => '2024-02-28']), 'Versão inexistente foi aceita.');
    $reject(fn () => EmployeeWorkPattern::create(['company_id' => $one->company_id, 'employee_id' => $employee->id, 'work_pattern_version_id' => $otherVersion->id, 'reference_date' => '2024-02-28', 'starts_on' => '2024-02-28']), 'Versão de outra empresa foi aceita.');
    $reject(fn () => ScheduleOverride::create(['company_id' => $one->company_id, 'employee_id' => $foreign->id, 'date' => '2024-02-28', 'is_work' => false, 'reason' => 'Teste']), 'Exceção entre empresas foi aceita.');
    $pattern->versions()->create(['company_id' => $one->company_id, 'kind' => 'cycle', 'sequence' => [false, true]]);
    ScheduleOverride::create(['company_id' => $one->company_id, 'employee_id' => $employee->id, 'date' => '2024-02-29', 'is_work' => true, 'reason' => 'Teste']);
    $result = app(ScheduleCalculator::class)->calculate($one->company_id, [$employee->id], '2024-02-28', '2024-02-29')[$employee->id];
    $check($result['2024-02-28']['status'] === 'work' && $result['2024-02-28']['ends_next_day'] && $result['2024-02-29']['status'] === 'work', 'Cálculo ou versionamento inconsistente.');
} finally {
    DB::rollBack();
}
$check(! User::where('email', 'constraints-'.strtolower($tag).'@example.test')->exists(), 'Rollback não removeu os dados fictícios.');
echo "Unicidade, FKs, isolamento, versão, atribuição, exceção e rollback: aprovados.\n";
