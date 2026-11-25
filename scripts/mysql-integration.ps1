param([switch]$Migrate)
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')
if (Test-Path 'bootstrap/cache/config.php') { throw 'Remova o cache de configuração pelo procedimento do projeto antes de executar.' }
$matching = @(docker ps --filter 'label=com.docker.compose.project=escalasimples-local' --filter 'name=^/escalasimples-mysql-local$' --format '{{.ID}}')
if ($LASTEXITCODE -ne 0 -or $matching.Count -ne 1) { throw 'Container exclusivo ausente.' }
$container = docker inspect --format '{{.Id}} {{.State.Health.Status}}' escalasimples-mysql-local
if ($LASTEXITCODE -ne 0 -or $container -notmatch '^([a-f0-9]{64}) healthy$' -or -not $Matches[1].StartsWith($matching[0])) { throw 'Container exclusivo não está saudável.' }
$containerId = $Matches[1].Substring(0, 12)
$line = @(Get-Content '.env.docker' | Where-Object { $_ -match '^ESCALA_TEST_PASSWORD=[0-9a-f]{32,}$' })
if ($line.Count -ne 1) { throw 'Credencial local de teste ausente ou inválida.' }
$devLine = @(Get-Content '.env.docker' | Where-Object { $_ -match '^ESCALA_DEV_PASSWORD=[0-9a-f]{32,}$' })
if ($devLine.Count -ne 1) { throw 'Credencial local de desenvolvimento ausente ou inválida.' }
$env:ESCALA_DOCKER_ID = $containerId
$env:DB_CONNECTION = 'mysql'
$env:DB_HOST = '127.0.0.1'
$env:DB_PORT = '3309'
$env:DB_DATABASE = 'escalasimples_test'
$env:DB_USERNAME = 'escala_test'
$env:DB_PASSWORD = $line[0].Substring('ESCALA_TEST_PASSWORD='.Length)
$env:ESCALA_DEV_TEST_PASSWORD = $devLine[0].Substring('ESCALA_DEV_PASSWORD='.Length)
$env:DB_URL = ''
php tests/Integration/DockerMySqlGuard.php
if ($LASTEXITCODE -ne 0) { throw 'Validação do destino falhou.' }
if ($Migrate) {
    php artisan migrate --force
    if ($LASTEXITCODE -ne 0) { throw 'Migrations de teste falharam.' }
}
foreach ($test in @('DockerMySqlPermissions.php', 'MariaDbConstraints.php', 'HolidayConstraints.php', 'DockerMonthlyConcurrency.php')) {
    php (Join-Path 'tests/Integration' $test)
    if ($LASTEXITCODE -ne 0) { throw "Teste de integração falhou: $test" }
}
php vendor/bin/phpunit --configuration phpunit.mysql.xml --do-not-cache-result
if ($LASTEXITCODE -ne 0) { throw 'Suíte PHPUnit MySQL falhou.' }
