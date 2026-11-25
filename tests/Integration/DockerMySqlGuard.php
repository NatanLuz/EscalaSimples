<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$config = config('database.connections.mysql');
$expectedId = getenv('ESCALA_DOCKER_ID');
if (config('database.default') !== 'mysql' || ($config['host'] ?? '') !== '127.0.0.1'
    || (string) ($config['port'] ?? '') !== '3309' || ($config['database'] ?? '') !== 'escalasimples_test'
    || ($config['username'] ?? '') !== 'escala_test' || ! empty($config['url'])
    || isset($config['read']) || isset($config['write']) || ! preg_match('/^[a-f0-9]{12}$/', (string) $expectedId)) {
    throw new RuntimeException('Conexão MySQL de teste não autorizada.');
}

$server = DB::selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS account, @@hostname AS container_id, VERSION() AS version');
if ($server->db !== 'escalasimples_test' || ! str_starts_with($server->account, 'escala_test@')
    || $server->container_id !== $expectedId || ! str_starts_with($server->version, '8.4.')) {
    throw new RuntimeException('O servidor ou a conta não correspondem ao MySQL de teste autorizado.');
}

echo "Destino MySQL de teste verificado.\n";
