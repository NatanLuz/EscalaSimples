<?php

use Illuminate\Support\Facades\DB;

require __DIR__.'/DockerMySqlGuard.php';

$devPassword = getenv('ESCALA_DEV_TEST_PASSWORD');
if (! preg_match('/^[0-9a-f]{32,}$/', (string) $devPassword)) {
    throw new RuntimeException('Credencial de desenvolvimento indisponível para verificação.');
}
$pdo = new PDO('mysql:host=127.0.0.1;port=3309;dbname=escalasimples', 'escala_dev', $devPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$identity = $pdo->query('SELECT DATABASE(), CURRENT_USER(), @@hostname')->fetch(PDO::FETCH_NUM);
if ($identity[0] !== 'escalasimples' || ! str_starts_with($identity[1], 'escala_dev@') || $identity[2] !== getenv('ESCALA_DOCKER_ID')) {
    throw new RuntimeException('Conexão de desenvolvimento não corresponde ao container autorizado.');
}
foreach ([['escala_dev', $devPassword, 'escalasimples_test'], ['escala_test', config('database.connections.mysql.password'), 'escalasimples']] as [$user, $password, $database]) {
    try {
        new PDO('mysql:host=127.0.0.1;port=3309;dbname='.$database, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $exception) {
        if (($exception->errorInfo[1] ?? null) === 1044) {
            continue;
        }
        throw new RuntimeException('Não foi possível verificar a restrição de acesso.');
    }
    throw new RuntimeException('Uma conta conseguiu acessar o banco da outra.');
}
foreach ([$pdo->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_COLUMN), array_map(fn ($row) => array_values((array) $row)[0], DB::select('SHOW GRANTS FOR CURRENT_USER'))] as $grants) {
    foreach ($grants as $grant) {
        if (str_contains($grant, ' ON *.*') && ! str_starts_with($grant, 'GRANT USAGE ON *.*')) {
            throw new RuntimeException('Foi encontrado privilégio global inesperado.');
        }
    }
}
echo "Contas restritas aos respectivos bancos, sem privilégios globais.\n";
