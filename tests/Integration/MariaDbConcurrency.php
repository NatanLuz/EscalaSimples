<?php

// Execução manual: DB_DATABASE=escalasimples_test php tests/Integration/MariaDbConcurrency.php
// Insere somente dados fictícios identificados; não remove nem altera registros existentes.
use App\Http\Controllers\EmployeeImportController;
use App\Http\Controllers\SchedulingController;
use App\Models\EmployeeWorkPattern;
use App\Models\User;
use App\Models\WorkPattern;
use App\Services\AssignWorkPattern;
use App\Services\EmployeeCsvImport;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
    throw new RuntimeException('Este teste exige exclusivamente o banco escalasimples_test na conexão mysql.');
}

if (($argv[1] ?? '') === 'worker') {
    $args = json_decode(base64_decode($argv[2]), true, flags: JSON_THROW_ON_ERROR);
    $user = User::findOrFail($args['user']);
    Auth::guard()->setUser($user);
    file_put_contents($args['ready'], (string) DB::selectOne('select connection_id() as id')->id);
    try {
        if ($args['action'] === 'assign') {
            app(AssignWorkPattern::class)->assign($user->company_id, $args['employees'], $args['pattern'], '2024-01-01', $args['start'], $args['end'] ?? null);
        } elseif ($args['action'] === 'end') {
            $request = Request::create('/atribuicoes', 'POST', ['ends_on' => '2024-01-09', 'reason' => 'Teste de concorrência fictício']);
            $request->setUserResolver(fn () => $user);
            app(SchedulingController::class)->end($request, $args['assignment']);
        } else {
            $file = new UploadedFile($args['csv'], 'teste.csv', 'text/csv', null, true);
            $request = Request::create('/colaboradores/importar/confirmar', 'POST', ['token' => $args['token']], [], ['file' => $file]);
            $request->setUserResolver(fn () => $user);
            app(EmployeeImportController::class)->confirm($request, app(EmployeeCsvImport::class));
        }
        echo 'accepted';
    } catch (ValidationException) {
        echo 'rejected';
    }
    exit;
}

$tag = (string) Str::ulid();
$user = User::factory()->create(['email' => 'concorrencia-'.strtolower($tag).'@example.test']);
$company = $user->company;
$department = $company->departments()->create(['name' => 'Teste '.$tag, 'is_active' => true]);
$pattern = new WorkPattern(['name' => 'Teste '.$tag, 'is_active' => true]);
$pattern->company_id = $company->id;
$pattern->save();
$version = $pattern->versions()->create(['company_id' => $company->id, 'kind' => 'cycle', 'sequence' => [true, false]]);
$dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'escala-concurrency-'.$tag;
if (! mkdir($dir, 0700, true)) {
    throw new RuntimeException('Não foi possível criar o diretório temporário.');
}
$csv = $dir.DIRECTORY_SEPARATOR.'import.csv';
file_put_contents($csv, "nome,setor\nPessoa ficticia,$department->name\n");
$token = (string) Str::ulid();
DB::table('employee_imports')->insert(['public_id' => $token, 'company_id' => $company->id, 'file_hash' => hash_file('sha256', $csv), 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]);

$run = function (string $label, callable $lock, array $operations) use ($user, $dir): array {
    DB::beginTransaction();
    $lock();
    $processes = [];
    try {
        foreach ($operations as $i => $operation) {
            $ready = $dir.DIRECTORY_SEPARATOR.$label.'-'.$i.'.ready';
            $args = base64_encode(json_encode([...$operation, 'user' => $user->id, 'ready' => $ready], JSON_THROW_ON_ERROR));
            $pipes = [];
            $proc = proc_open([PHP_BINARY, __FILE__, 'worker', $args], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (! is_resource($proc)) {
                throw new RuntimeException('Falha ao iniciar processo independente.');
            }
            fclose($pipes[0]);
            $processes[] = [$proc, $pipes, $ready];
        }
        $deadline = microtime(true) + 20;
        do {
            $ready = count(array_filter($processes, fn ($process) => is_file($process[2])));
            $waiting = 0;
            if ($ready === 2) {
                $connections = array_map(fn ($process) => (int) file_get_contents($process[2]), $processes);
                $waiting = (int) DB::selectOne('select count(distinct trx.trx_mysql_thread_id) as total from information_schema.INNODB_LOCK_WAITS waits join information_schema.INNODB_TRX trx on trx.trx_id = waits.requesting_trx_id where trx.trx_mysql_thread_id in (?, ?)', $connections)->total;
            }
            if ($ready === 2 && $waiting >= 2) {
                break;
            }
            usleep(100000);
        } while (microtime(true) < $deadline);
        if ($ready !== 2 || $waiting < 2) {
            throw new RuntimeException("Não foi possível observar duas transações simultaneamente bloqueadas em $label.");
        }
    } finally {
        DB::rollBack();
    }
    $outcomes = [];
    foreach ($processes as [$proc, $pipes]) {
        $stdout = trim(stream_get_contents($pipes[1]));
        $stderr = trim(stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($proc);
        if ($status !== 0 || ! in_array($stdout, ['accepted', 'rejected'], true)) {
            throw new RuntimeException("Falha em processo $label: $stdout $stderr");
        }
        $outcomes[] = $stdout;
    }
    echo "$label: dois processos bloqueados simultaneamente; ".implode(', ', $outcomes)."\n";

    return $outcomes;
};
$assert = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$result = $run('csv', fn () => DB::table('employee_imports')->where('public_id', $token)->lockForUpdate()->first(), [
    ['action' => 'import', 'token' => $token, 'csv' => $csv], ['action' => 'import', 'token' => $token, 'csv' => $csv],
]);
sort($result);
$assert($result === ['accepted', 'rejected'] && $company->employees()->where('name', 'Pessoa ficticia')->count() === 1 && DB::table('employee_imports')->where('public_id', $token)->whereNotNull('completed_at')->count() === 1, 'Importação concorrente inconsistente.');
$employees = [];
for ($i = 0; $i < 4; $i++) {
    $employees[] = $company->employees()->create(['name' => "Concorrência $tag $i", 'is_active' => true]);
}
$operation = fn (array $ids, string $start = '2024-01-01') => ['action' => 'assign', 'employees' => $ids, 'pattern' => $pattern->public_id, 'start' => $start];
$result = $run('atribuicoes', fn () => DB::table('employees')->where('id', $employees[0]->id)->lockForUpdate()->first(), [$operation([$employees[0]->public_id]), $operation([$employees[0]->public_id])]);
sort($result);
$assert($result === ['accepted', 'rejected'] && EmployeeWorkPattern::where('employee_id', $employees[0]->id)->count() === 1, 'Atribuição concorrente inconsistente.');
$result = $run('individual-lote', fn () => DB::table('employees')->where('id', $employees[1]->id)->lockForUpdate()->first(), [$operation([$employees[1]->public_id]), $operation([$employees[1]->public_id, $employees[2]->public_id])]);
$batchAccepted = $result[1] === 'accepted';
sort($result);
$assert($result === ['accepted', 'rejected'] && EmployeeWorkPattern::where('employee_id', $employees[1]->id)->count() === 1 && EmployeeWorkPattern::where('employee_id', $employees[2]->id)->count() === (int) $batchAccepted, 'Atribuição em lote inconsistente.');
$existing = EmployeeWorkPattern::create(['company_id' => $company->id, 'employee_id' => $employees[3]->id, 'work_pattern_version_id' => $version->id, 'reference_date' => '2024-01-01', 'starts_on' => '2024-01-01']);
$result = $run('encerramento', fn () => DB::table('employees')->where('id', $employees[3]->id)->lockForUpdate()->first(), [['action' => 'end', 'assignment' => $existing->public_id], $operation([$employees[3]->public_id], '2024-01-10')]);
$assert(in_array('accepted', $result, true) && $existing->fresh()->ends_on === '2024-01-09' && EmployeeWorkPattern::where('employee_id', $employees[3]->id)->where('starts_on', '>=', '2024-01-10')->count() <= 1 && DB::table('assignment_changes')->where('employee_work_pattern_id', $existing->id)->count() === 1, 'Encerramento concorrente inconsistente.');
echo "Verificações finais aprovadas; empresa fictícia $tag permanece no banco de testes.\n";
