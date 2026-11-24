<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_directories(): void
    {
        foreach (['/setores', '/setores/create', '/colaboradores', '/colaboradores/create'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/setores', [])->assertRedirect('/login');
        $this->post('/colaboradores', [])->assertRedirect('/login');
    }

    public function test_departments_are_normalized_unique_per_company_and_can_be_updated(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        $this->actingAs($one)->post('/setores', ['name' => '  CAIXA   Central ', 'is_active' => true, 'company_id' => $two->company_id])->assertRedirect('/setores');
        $department = $one->company->departments()->firstOrFail();
        $this->assertSame('CAIXA Central', $department->name);
        $this->assertSame($one->company_id, $department->company_id);
        $this->actingAs($one)->post('/setores', ['name' => 'caixa central', 'is_active' => true])->assertSessionHasErrors('name');
        $another = $one->company->departments()->create(['name' => 'Estoque', 'is_active' => true]);
        $this->put('/setores/'.$another->public_id, ['name' => '  caixa central ', 'is_active' => true])->assertSessionHasErrors('name');
        $this->actingAs($two)->post('/setores', ['name' => 'caixa central', 'is_active' => true])->assertRedirect('/setores');
        $this->actingAs($one)->put('/setores/'.$department->public_id, ['name' => 'Loja', 'is_active' => false])->assertRedirect('/setores');
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'name' => 'Loja', 'is_active' => false]);
        $this->actingAs($one)->put('/setores/'.$department->public_id, ['name' => 'Loja', 'is_active' => true])->assertRedirect('/setores');
        $this->assertTrue($department->fresh()->is_active);
        $this->post('/setores', ['name' => '', 'is_active' => true])->assertSessionHasErrors('name');
    }

    public function test_cross_company_resources_and_department_assignment_are_rejected(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        $foreignDepartment = $two->company->departments()->create(['name' => 'Setor estrangeiro', 'is_active' => true]);
        $foreignEmployee = $two->company->employees()->create(['name' => 'Pessoa estrangeira', 'is_active' => true]);
        $ownEmployee = $one->company->employees()->create(['name' => 'Pessoa própria', 'is_active' => true]);
        $this->actingAs($one)->get('/setores/'.$foreignDepartment->public_id.'/edit')->assertNotFound();
        $this->put('/setores/'.$foreignDepartment->public_id, ['name' => 'Roubo', 'is_active' => true])->assertNotFound();
        $this->get('/colaboradores/'.$foreignEmployee->public_id.'/edit')->assertNotFound();
        $this->put('/colaboradores/'.$foreignEmployee->public_id, ['name' => 'Roubo', 'is_active' => true])->assertNotFound();
        $this->post('/colaboradores', ['name' => 'Novo', 'is_active' => true, 'department' => $foreignDepartment->public_id, 'company_id' => $two->company_id])->assertSessionHasErrors('department');
        $this->put('/colaboradores/'.$ownEmployee->public_id, ['name' => 'Pessoa própria', 'is_active' => true, 'department' => $foreignDepartment->public_id])->assertSessionHasErrors('department');
        $this->get('/setores')->assertDontSee('Setor estrangeiro');
        $this->get('/colaboradores')->assertDontSee('Pessoa estrangeira');
        $this->get('/colaboradores?department='.$foreignDepartment->public_id)->assertInertia(fn (Assert $page) => $page->where('items.total', 0));
        $this->assertDatabaseHas('employees', ['id' => $foreignEmployee->id, 'name' => 'Pessoa estrangeira']);
    }

    public function test_employee_can_be_created_edited_and_can_keep_an_inactive_department(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $department = $user->company->departments()->create(['name' => 'Caixa', 'is_active' => true]);
        $this->actingAs($user)->post('/colaboradores', ['name' => 'Ana', 'is_active' => true, 'department' => $department->public_id, 'company_id' => $other->company_id])->assertRedirect('/colaboradores');
        $employee = $user->company->employees()->firstOrFail();
        $this->assertSame($department->id, $employee->department_id);
        $this->assertSame($user->company_id, $employee->company_id);
        $department->update(['is_active' => false]);
        $this->post('/colaboradores', ['name' => 'Outra', 'is_active' => true, 'department' => $department->public_id])->assertSessionHasErrors('department');
        $this->put('/colaboradores/'.$employee->public_id, ['name' => 'Ana Atualizada', 'is_active' => false, 'department' => $department->public_id])->assertRedirect('/colaboradores');
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'name' => 'Ana Atualizada', 'department_id' => $department->id, 'is_active' => false]);
        $this->put('/colaboradores/'.$employee->public_id, ['name' => 'Ana Atualizada', 'is_active' => true, 'department' => null])->assertRedirect('/colaboradores');
        $this->assertNull($employee->fresh()->department_id);
        $this->post('/colaboradores', ['name' => '', 'is_active' => true])->assertSessionHasErrors('name');
    }

    public function test_listing_search_filters_and_pagination_remain_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $department = $user->company->departments()->create(['name' => 'Padaria', 'is_active' => true]);
        $user->company->departments()->create(['name' => 'Estoque', 'is_active' => false]);
        $other->company->departments()->create(['name' => 'Padaria Estrangeira', 'is_active' => true]);
        for ($i = 1; $i <= 17; $i++) {
            $employee = $user->company->employees()->make(['name' => 'Pessoa '.$i, 'is_active' => true]);
            $employee->department()->associate($department);
            $user->company->employees()->save($employee);
        }
        $other->company->employees()->create(['name' => 'Pessoa secreta', 'is_active' => true]);
        $this->actingAs($user)->get('/setores?search=Padaria&status=active')->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.name', 'Padaria'))->assertDontSee('Estrangeira');
        $this->get('/setores?status=inactive')->assertInertia(fn (Assert $page) => $page->has('items.data', 1)->where('items.data.0.name', 'Estoque'));
        $this->get('/colaboradores?search=Pessoa')->assertInertia(fn (Assert $page) => $page->has('items.data', 15)->where('items.total', 17))->assertDontSee('secreta');
        $this->get('/colaboradores?department='.$department->public_id)->assertInertia(fn (Assert $page) => $page->where('items.total', 17));
        $this->get('/colaboradores?status=inactive')->assertInertia(fn (Assert $page) => $page->where('items.total', 0));
    }

    public function test_composite_foreign_key_rejects_cross_company_department(): void
    {
        $one = User::factory()->create();
        $two = User::factory()->create();
        $foreign = $two->company->departments()->create(['name' => 'Outro', 'is_active' => true]);
        $this->expectException(QueryException::class);
        DB::table('employees')->insert(['company_id' => $one->company_id, 'department_id' => $foreign->id, 'name' => 'Teste', 'public_id' => (string) Str::ulid(), 'is_active' => true]);
    }

    public function test_database_restricts_deleting_a_department_with_employees(): void
    {
        $user = User::factory()->create();
        $department = $user->company->departments()->create(['name' => 'Caixa', 'is_active' => true]);
        $employee = $user->company->employees()->make(['name' => 'Pessoa', 'is_active' => true]);
        $employee->department()->associate($department);
        $user->company->employees()->save($employee);
        $this->expectException(QueryException::class);
        $department->delete();
    }

    public function test_department_pagination_is_limited_to_authenticated_company(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        for ($i = 0; $i < 17; $i++) {
            $user->company->departments()->create(['name' => 'Setor '.$i, 'is_active' => true]);
        }
        $other->company->departments()->create(['name' => 'Setor externo', 'is_active' => true]);
        $this->actingAs($user)->get('/setores')->assertInertia(fn (Assert $page) => $page->has('items.data', 15)->where('items.total', 17));
        $this->get('/setores?page=2')->assertInertia(fn (Assert $page) => $page->has('items.data', 2)->where('items.total', 17));
    }

    public function test_duplicate_employee_names_are_allowed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/colaboradores', ['name' => 'Ana', 'is_active' => true])->assertRedirect('/colaboradores');
        $this->post('/colaboradores', ['name' => 'Ana', 'is_active' => true])->assertRedirect('/colaboradores');
        $this->assertSame(2, $user->company->employees()->where('name', 'Ana')->count());
    }
}
