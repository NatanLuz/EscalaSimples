<?php

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
DB::beginTransaction();
try {
    $publicId = (string) Str::ulid();
    $companyId = DB::table('companies')->insertGetId(['public_id' => (string) Str::ulid(), 'name' => 'Feriados fictícios']);
    $row = ['public_id' => $publicId, 'company_id' => $companyId, 'name' => 'Data fictícia', 'date' => '2026-04-05', 'type' => 'holiday', 'scope' => 'company', 'is_active' => true];
    DB::table('holidays')->insert($row);
    foreach ([['public_id' => $publicId], ['public_id' => (string) Str::ulid(), 'company_id' => 999999999]] as $invalid) {
        try {
            DB::table('holidays')->insert(array_replace($row, $invalid));
            throw new RuntimeException('Constraint não rejeitou o registro inválido.');
        } catch (QueryException) {
            // A rejeição pelo banco é o resultado esperado.
        }
    }
} finally {
    DB::rollBack();
}
if (DB::table('holidays')->where('public_id', $publicId)->exists()) {
    throw new RuntimeException('Rollback não reverteu o registro fictício.');
}
echo "Feriados: unicidade, chave estrangeira e rollback aprovados.\n";
