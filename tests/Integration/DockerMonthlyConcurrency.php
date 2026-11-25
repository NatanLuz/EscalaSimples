<?php

use App\Http\Controllers\ScheduleEditorController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

ob_start();
require __DIR__.'/DockerMySqlGuard.php';
ob_end_clean();

if (($argv[1] ?? '') === 'worker') {
    $data = json_decode(base64_decode($argv[2]), true, flags: JSON_THROW_ON_ERROR);
    $user = User::findOrFail($data['user']);
    Auth::guard()->setUser($user);
    $request = Request::create('/minhas-escalas', 'POST', [
        'month' => '2024-02', 'revision' => 0, 'operation_id' => $data['operation'],
        'model' => '6x1', 'days_off' => [6],
        'employees' => [['id' => null, 'name' => $data['name'], 'days' => array_fill(0, 29, 'work')]],
    ]);
    $request->setUserResolver(fn () => $user);
    file_put_contents($data['ready'], (string) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
    try {
        app(ScheduleEditorController::class)->store($request);
        echo 'accepted';
    } catch (ValidationException) {
        echo 'rejected';
    }
    exit;
}

$tag = (string) Str::ulid();
$user = User::factory()->create(['email' => 'monthly-'.strtolower($tag).'@example.test']);
$directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'escala-monthly-'.$tag;
if (! mkdir($directory, 0700, true)) {
    throw new RuntimeException('Não foi possível criar diretório temporário.');
}
$processes = [];
DB::beginTransaction();
try {
    DB::table('companies')->where('id', $user->company_id)->lockForUpdate()->first();
    for ($i = 0; $i < 2; $i++) {
        $ready = $directory.DIRECTORY_SEPARATOR.$i.'.ready';
        $data = ['user' => $user->id, 'ready' => $ready, 'operation' => (string) Str::ulid(), 'name' => 'Pessoa fictícia '.$i];
        $process = proc_open([PHP_BINARY, __FILE__, 'worker', base64_encode(json_encode($data, JSON_THROW_ON_ERROR))], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Não foi possível iniciar processo independente.');
        }
        fclose($pipes[0]);
        $processes[] = [$process, $pipes, $ready];
    }
    $deadline = microtime(true) + 120;
    $waiting = 0;
    do {
        if (count(array_filter($processes, fn ($item) => is_file($item[2]))) === 2) {
            $ids = array_map(fn ($item) => (int) file_get_contents($item[2]), $processes);
            $waiting = count(array_filter(DB::select('SHOW PROCESSLIST'), fn ($item) => in_array((int) $item->Id, $ids, true) && str_contains(strtolower($item->Info ?? ''), 'for update')));
        }
        if ($waiting === 2) {
            break;
        }
        usleep(200000);
    } while (microtime(true) < $deadline);
    if ($waiting !== 2) {
        throw new RuntimeException('Não foi possível observar duas operações simultâneas aguardando o bloqueio.');
    }
} finally {
    DB::rollBack();
}
$results = [];
foreach ($processes as [$process, $pipes]) {
    $output = trim(stream_get_contents($pipes[1]));
    $error = trim(stream_get_contents($pipes[2]));
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0 || ! in_array($output, ['accepted', 'rejected'], true)) {
        throw new RuntimeException('Processo concorrente falhou: '.$error);
    }
    $results[] = $output;
}
sort($results);
if ($results !== ['accepted', 'rejected'] || DB::table('monthly_schedules')->where('company_id', $user->company_id)->where('month', '2024-02')->where('revision', 1)->count() !== 1
    || $user->company->employees()->count() !== 1) {
    throw new RuntimeException('O resultado concorrente ou o estado final são inconsistentes.');
}
echo "Duas conexões aguardaram o bloqueio; uma gravação aceita, outra rejeitada, uma revisão e um colaborador.\n";
