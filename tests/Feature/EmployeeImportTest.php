<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployeeImportTest extends TestCase
{
    use RefreshDatabase;

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('colaboradores.csv', $content);
    }

    private function preview(string $content): array
    {
        $response = $this->post('/colaboradores/importar/previa', ['file' => $this->csv($content)]);
        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('Employees/Import'));

        return $response->viewData('page')['props']['preview'];
    }

    public function test_comma_and_semicolon_bom_import_without_preview_persistence_and_replay(): void
    {
        $user = User::factory()->create();
        $user->company->departments()->create(['name' => 'Caixa', 'is_active' => true]);
        $this->actingAs($user);
        foreach (["nome,setor\nAna,Caixa\nAna,Caixa\n", "\xEF\xBB\xBFnome;setor\nBruno;Caixa\n"] as $content) {
            $before = $user->company->employees()->count();
            $preview = $this->preview($content);
            $this->assertSame($before, $user->company->employees()->count());
            $this->assertNotNull($preview['token']);
            $this->post('/colaboradores/importar/confirmar', ['token' => $preview['token'], 'file' => $this->csv($content), 'company_id' => 999, 'rows' => [['name' => 'Injetado']]])->assertRedirect('/colaboradores');
            $this->assertSame($before + $preview['total'], $user->company->employees()->count());
            $this->assertDatabaseMissing('employees', ['name' => 'Injetado']);
            $this->post('/colaboradores/importar/confirmar', ['token' => $preview['token'], 'file' => $this->csv($content)])->assertSessionHasErrors('file');
            $this->assertSame($before + $preview['total'], $user->company->employees()->count());
        }
        $this->assertSame(2, $user->company->employees()->where('name', 'Ana')->count());
    }

    public function test_invalid_headers_rows_and_foreign_or_inactive_departments_are_rejected(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->company->departments()->create(['name' => 'Inativo', 'is_active' => false]);
        $other->company->departments()->create(['name' => 'Estrangeiro', 'is_active' => true]);
        $this->actingAs($user);
        $this->post('/colaboradores/importar/previa', ['file' => $this->csv("nome,email\nAna,a\n")])->assertSessionHasErrors('file');
        foreach (["nome,setor\n,Caixa\n", "nome,setor\nAna,Inexistente\n", "nome,setor\nAna,Inativo\n", "nome,setor\nAna,Estrangeiro\n"] as $content) {
            $preview = $this->preview($content);
            $this->assertNull($preview['token']);
            $this->assertSame(1, $preview['error_count']);
            $this->assertSame(2, $preview['errors'][0]['line']);
        }
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_model_and_possible_homonym_warnings_do_not_block_import(): void
    {
        $user = User::factory()->create();
        $user->company->departments()->create(['name' => 'Caixa', 'is_active' => true]);
        $user->company->employees()->create(['name' => 'Ana', 'is_active' => true]);
        $this->actingAs($user)->get('/colaboradores/modelo-csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertSee('nome,setor');
        $preview = $this->preview("nome,setor\nAna,Caixa\nAna,Caixa\n");
        $this->assertSame(2, $preview['duplicates']);
        $this->assertNotNull($preview['token']);
    }

    public function test_invalid_encoding_and_file_extension_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->post('/colaboradores/importar/previa', ['file' => $this->csv("\xFFnome,setor\n")])->assertSessionHasErrors('file');
        $this->post('/colaboradores/importar/previa', ['file' => UploadedFile::fake()->createWithContent('arquivo.txt', "nome,setor\n")])->assertSessionHasErrors('file');
    }

    public function test_limits_and_file_changes_are_rejected(): void
    {
        $user = User::factory()->create();
        $user->company->departments()->create(['name' => 'Caixa', 'is_active' => true]);
        $this->actingAs($user);
        config(['employee_import.max_rows' => 1, 'employee_import.max_kilobytes' => 1]);
        $this->post('/colaboradores/importar/previa', ['file' => $this->csv("nome,setor\nAna,Caixa\nBia,Caixa\n")])->assertSessionHasErrors('file');
        $this->post('/colaboradores/importar/previa', ['file' => $this->csv(str_repeat('x', 2048))])->assertSessionHasErrors('file');
        $content = "nome,setor\nAna,Caixa\n";
        $preview = $this->preview($content);
        $this->post('/colaboradores/importar/confirmar', ['token' => $preview['token'], 'file' => $this->csv("nome,setor\nBia,Caixa\n"), 'company_id' => 999])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_confirmation_revalidates_departments_and_company_context(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $department = $user->company->departments()->create(['name' => 'Caixa', 'is_active' => true]);
        $content = "nome,setor\nAna,Caixa\n";
        $this->actingAs($user);
        $preview = $this->preview($content);
        $this->actingAs($other)->post('/colaboradores/importar/confirmar', ['token' => $preview['token'], 'file' => $this->csv($content)])->assertSessionHasErrors('file');
        $department->update(['is_active' => false]);
        $this->actingAs($user)->post('/colaboradores/importar/confirmar', ['token' => $preview['token'], 'file' => $this->csv($content)])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('employees', 0);
        $this->assertNull(DB::table('employee_imports')->where('public_id', $preview['token'])->value('completed_at'));
    }

    public function test_imports_700_records(): void
    {
        $user = User::factory()->create();
        $user->company->departments()->create(['name' => 'Caixa', 'is_active' => true]);
        $content = "nome,setor\n";
        for ($i = 0; $i < 700; $i++) {
            $content .= "Pessoa $i,Caixa\n";
        }
        $this->actingAs($user);
        $start = hrtime(true);
        $memory = memory_get_peak_usage(true);
        $preview = $this->preview($content);
        $this->assertCount(50, $preview['rows']);
        $this->post('/colaboradores/importar/confirmar', ['token' => $preview['token'], 'file' => $this->csv($content)])->assertRedirect('/colaboradores');
        $this->assertDatabaseCount('employees', 700);
        fwrite(STDERR, sprintf("Importação 700: %.3f s; pico PHP %.2f MiB; aumento de pico %.2f MiB\n", (hrtime(true) - $start) / 1e9, memory_get_peak_usage(true) / 1048576, (memory_get_peak_usage(true) - $memory) / 1048576));
    }

    public function test_failed_insert_rolls_back_every_batch_and_operation(): void
    {
        $user = User::factory()->create();
        $user->company->departments()->create(['name' => 'Caixa', 'is_active' => true]);
        $content = "nome,setor\n";
        for ($i = 0; $i < 101; $i++) {
            $content .= "Pessoa $i,Caixa\n";
        }
        $this->actingAs($user);
        $preview = $this->preview($content);
        $insertions = 0;
        DB::listen(function ($query) use (&$insertions) {
            if (str_starts_with(strtolower($query->sql), 'insert into "employees"') && ++$insertions === 2) {
                throw new \RuntimeException('Falha simulada');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->post('/colaboradores/importar/confirmar', ['token' => $preview['token'], 'file' => $this->csv($content)]);
            $this->fail('A falha simulada deveria interromper a importação.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha simulada', $exception->getMessage());
        }
        $this->assertDatabaseCount('employees', 0);
        $this->assertNull(DB::table('employee_imports')->where('public_id', $preview['token'])->value('completed_at'));
    }
}
