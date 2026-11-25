<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkPattern;
use App\Services\ScheduleCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CalendarPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_page_and_full_month_calculation_for_fictitious_companies(): void
    {
        foreach ([50, 100, 700] as $count) {
            $user = User::factory()->create();
            $pattern = new WorkPattern(['name' => '5x2 fictício', 'is_active' => true]);
            $pattern->company_id = $user->company_id;
            $pattern->save();
            $version = $pattern->versions()->create(['company_id' => $user->company_id, 'kind' => 'cycle', 'sequence' => [true, true, true, true, true, false, false]]);
            $employees = [];
            foreach (range(1, $count) as $i) {
                $employees[] = ['public_id' => (string) Str::ulid(), 'company_id' => $user->company_id, 'name' => 'Colaborador fictício '.$i, 'is_active' => true];
            }
            foreach (array_chunk($employees, 100) as $chunk) {
                DB::table('employees')->insert($chunk);
            }
            $ids = DB::table('employees')->where('company_id', $user->company_id)->pluck('id')->all();
            $assignments = array_map(fn ($id) => ['public_id' => (string) Str::ulid(), 'company_id' => $user->company_id, 'employee_id' => $id, 'work_pattern_version_id' => $version->id, 'reference_date' => '2026-05-01', 'starts_on' => '2026-05-01'], $ids);
            foreach (array_chunk($assignments, 100) as $chunk) {
                DB::table('employee_work_patterns')->insert($chunk);
            }
            DB::flushQueryLog();
            DB::enableQueryLog();
            $startMemory = memory_get_usage(true);
            memory_reset_peak_usage();
            $start = microtime(true);
            $result = app(ScheduleCalculator::class)->calculate($user->company_id, $ids, '2026-05-01', '2026-05-31');
            $calculateMs = (microtime(true) - $start) * 1000;
            $calculateQueries = count(DB::getQueryLog());
            $calculateMemory = memory_get_peak_usage(true) - $startMemory;
            $this->assertCount($count, $result);
            $this->assertSame(3, $calculateQueries);
            unset($result);
            DB::flushQueryLog();
            $startMemory = memory_get_usage(true);
            memory_reset_peak_usage();
            $start = microtime(true);
            $response = $this->actingAs($user)->get('/escalas?month=2026-05');
            $responseMs = (microtime(true) - $start) * 1000;
            $responseQueries = count(DB::getQueryLog());
            $responseMemory = memory_get_peak_usage(true) - $startMemory;
            DB::disableQueryLog();
            $response->assertOk();
            $this->assertLessThanOrEqual(20, count($response->viewData('page')['props']['rows']));
            $this->assertLessThanOrEqual(15, $responseQueries);
            fwrite(STDERR, sprintf("BENCHMARK %d: cálculo total %.1f ms, %d consultas, +%.2f MiB; página %.1f ms, %d consultas, +%.2f MiB, %d bytes\n", $count, $calculateMs, $calculateQueries, $calculateMemory / 1048576, $responseMs, $responseQueries, $responseMemory / 1048576, strlen($response->getContent())));
            DB::flushQueryLog();
            DB::enableQueryLog();
            $startMemory = memory_get_usage(true);
            memory_reset_peak_usage();
            $start = microtime(true);
            $summary = $this->get('/escalas?month=2026-05&summary=company&summary_date=2026-05-01');
            $summaryMs = (microtime(true) - $start) * 1000;
            $summaryQueries = count(DB::getQueryLog());
            $summaryMemory = memory_get_peak_usage(true) - $startMemory;
            DB::disableQueryLog();
            $summary->assertOk();
            $this->assertSame($count, $summary->viewData('page')['props']['summary']['totals']['work']);
            $this->assertLessThanOrEqual(20, $summaryQueries);
            fwrite(STDERR, sprintf("BENCHMARK %d: página + indicador diário da empresa %.1f ms, %d consultas, pico +%.2f MiB, %d bytes\n", $count, $summaryMs, $summaryQueries, $summaryMemory / 1048576, strlen($summary->getContent())));
        }
    }
}
