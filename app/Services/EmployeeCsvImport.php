<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Department;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class EmployeeCsvImport
{
    public function inspect(UploadedFile $file, Company $company, bool $lockDepartments = false): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) {
            throw ValidationException::withMessages(['file' => 'Não foi possível ler o arquivo.']);
        }
        try {
            $headerLine = fgets($handle);
            if ($headerLine === false || ! mb_check_encoding($headerLine, 'UTF-8')) {
                throw ValidationException::withMessages(['file' => 'O arquivo deve ser UTF-8 e conter as colunas nome e setor.']);
            }
            $headerLine = preg_replace('/^\xEF\xBB\xBF/', '', $headerLine);
            $delimiter = substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
            $header = array_map(fn ($value) => mb_strtolower(trim($value)), str_getcsv(rtrim($headerLine, "\r\n"), $delimiter, '"', ''));
            if ($header !== ['nome', 'setor']) {
                throw ValidationException::withMessages(['file' => 'O cabeçalho deve conter exatamente nome e setor, nessa ordem.']);
            }
            $departments = $company->departments()->where('is_active', true)->when($lockDepartments, fn ($query) => $query->lockForUpdate())->get(['id', 'name_key'])->keyBy('name_key');
            $rows = [];
            $errors = [];
            $seen = [];
            $duplicates = 0;
            $line = 2;
            while (true) {
                $start = ftell($handle);
                $row = fgetcsv($handle, 0, $delimiter, '"', '');
                if ($row === false) {
                    break;
                }
                $end = ftell($handle);
                fseek($handle, $start);
                $raw = stream_get_contents($handle, $end - $start);
                fseek($handle, $end);
                $currentLine = $line;
                $line += max(1, substr_count($raw, "\n"));
                if ($row === [null]) {
                    continue;
                }
                if (count($rows) + count($errors) >= config('employee_import.max_rows')) {
                    throw ValidationException::withMessages(['file' => 'O arquivo excede o limite de registros permitido.']);
                }
                if (! mb_check_encoding($raw, 'UTF-8') || count($row) !== 2 || str_contains($row[0] ?? '', "\n") || str_contains($row[1] ?? '', "\n")) {
                    $errors[] = ['line' => $currentLine, 'message' => 'Linha inválida: use UTF-8 e duas colunas em uma única linha.'];

                    continue;
                }
                $name = trim($row[0]);
                $departmentName = Department::normalizeName($row[1]);
                $department = $departmentName !== '' ? $departments->get(Department::nameKey($departmentName)) : null;
                if ($name === '' || mb_strlen($name) > 255 || ! $department) {
                    $errors[] = ['line' => $currentLine, 'message' => $name === '' ? 'Informe o nome.' : (mb_strlen($name) > 255 ? 'O nome excede 255 caracteres.' : 'Setor não encontrado ou inativo.')];

                    continue;
                }
                $key = mb_strtolower($name, 'UTF-8');
                $repeated = isset($seen[$key]);
                $seen[$key] = true;
                if ($repeated) {
                    $duplicates++;
                }
                $rows[] = ['line' => $currentLine, 'name' => $name, 'department' => $departmentName, 'department_id' => $department->id, 'possible_duplicate' => $repeated];
            }
            if (! $rows && ! $errors) {
                throw ValidationException::withMessages(['file' => 'O arquivo não contém registros.']);
            }
            $existing = [];
            foreach (array_chunk(array_values(array_unique(array_column($rows, 'name'))), 200) as $names) {
                foreach ($company->employees()->whereIn('name', $names)->pluck('name') as $name) {
                    $existing[mb_strtolower(trim($name), 'UTF-8')] = true;
                }
            }
            foreach ($rows as &$row) {
                if (! $row['possible_duplicate'] && isset($existing[mb_strtolower($row['name'], 'UTF-8')])) {
                    $row['possible_duplicate'] = true;
                    $duplicates++;
                }
            }
            unset($row);

            return ['rows' => $rows, 'errors' => $errors, 'duplicates' => $duplicates];
        } finally {
            fclose($handle);
        }
    }
}
