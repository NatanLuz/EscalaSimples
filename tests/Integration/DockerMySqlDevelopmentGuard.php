<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.mysql');
$expected = getenv('ESCALA_DOCKER_ID');
if (config('database.default') !== 'mysql' || ($config['host'] ?? '') !== '127.0.0.1'
    || (string) ($config['port'] ?? '') !== '3309' || ($config['database'] ?? '') !== 'escalasimples'
    || ($config['username'] ?? '') !== 'escala_dev' || ! empty($config['url'])
    || isset($config['read']) || isset($config['write']) || ! preg_match('/^[a-f0-9]{12}$/', (string) $expected)) {
    throw new RuntimeException('Conexão de desenvolvimento não autorizada.');
}
$server = DB::selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS account, @@hostname AS container_id, VERSION() AS version');
if ($server->db !== 'escalasimples' || ! str_starts_with($server->account, 'escala_dev@')
    || $server->container_id !== $expected || ! str_starts_with($server->version, '8.4.')) {
    throw new RuntimeException('Servidor de desenvolvimento não corresponde ao container autorizado.');
}
if (getenv('ESCALA_REQUIRE_EMPTY') === '1' && (int) DB::selectOne("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = 'escalasimples'")->total !== 0) {
    throw new RuntimeException('O banco de desenvolvimento não está vazio; revise antes de executar migrations.');
}
echo "Destino MySQL de desenvolvimento verificado.\n";
