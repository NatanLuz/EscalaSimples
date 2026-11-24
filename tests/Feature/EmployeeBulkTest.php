<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeBulkTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_status_and_department_changes(): void
    {
        $user = User::factory()->create();
        $company = $user->company;
        $department = $company->departments()->create(['name' => 'Caixa', 'is_active' => true]);
        $first = $company->employees()->create(['name' => 'Ana', 'is_active' => true]);
        $second = $company->employees()->create(['name' => 'Ana', 'is_active' => true]);
        $ids = [$first->public_id, $second->public_id];
        $this->actingAs($user)->post('/colaboradores/lote', ['employees' => $ids, 'action' => 'deactivate'])->assertRedirect('/colaboradores');
        $this->assertFalse($first->fresh()->is_active);
        $this->assertFalse($second->fresh()->is_active);
        $this->post('/colaboradores/lote', ['employees' => $ids, 'action' => 'activate'])->assertRedirect('/colaboradores');
        $this->assertTrue($first->fresh()->is_active);
        $this->post('/colaboradores/lote', ['employees' => $ids, 'action' => 'department', 'department' => $department->public_id])->assertRedirect('/colaboradores');
        $this->assertSame($department->id, $first->fresh()->department_id);
        $this->assertSame($department->id, $second->fresh()->department_id);
    }

    public function test_foreign_employee_or_department_cannot_be_used_and_nothing_changes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $own = $user->company->employees()->create(['name' => 'Ana', 'is_active' => true]);
        $foreign = $other->company->employees()->create(['name' => 'Bia', 'is_active' => true]);
        $department = $other->company->departments()->create(['name' => 'Outro', 'is_active' => true]);
        $this->actingAs($user)->post('/colaboradores/lote', ['employees' => [$own->public_id, $foreign->public_id], 'action' => 'deactivate', 'company_id' => $other->company_id])->assertSessionHasErrors('employees');
        $this->assertTrue($own->fresh()->is_active);
        $this->assertTrue($foreign->fresh()->is_active);
        $this->post('/colaboradores/lote', ['employees' => [$own->public_id], 'action' => 'department', 'department' => $department->public_id])->assertSessionHasErrors('department');
        $this->assertNull($own->fresh()->department_id);
        $inactive = $user->company->departments()->create(['name' => 'Inativo', 'is_active' => false]);
        $this->post('/colaboradores/lote', ['employees' => [$own->public_id], 'action' => 'department', 'department' => $inactive->public_id])->assertSessionHasErrors('department');
        $this->assertNull($own->fresh()->department_id);
    }
}
