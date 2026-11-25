<?php

use Illuminate\Support\Facades\DB;

ob_start();
require __DIR__.'/DockerMySqlGuard.php';
ob_end_clean();
$schedules = DB::table('monthly_schedules')->orderBy('id')->get(['id', 'company_id', 'month', 'revision'])->all();
$revisions = DB::table('monthly_schedule_revisions')->orderBy('id')->get(['id', 'monthly_schedule_id', 'revision', 'snapshot'])->all();
if (count($schedules) < 1 || count($revisions) < 1) {
    throw new RuntimeException('Nenhuma escala fictícia encontrada para verificar persistência.');
}
echo hash('sha256', json_encode([$schedules, $revisions], JSON_THROW_ON_ERROR));
