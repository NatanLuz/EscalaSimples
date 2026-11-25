<?php

namespace App\Services;

use App\Models\EmployeeWorkPattern;
use App\Models\ScheduleOverride;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ScheduleCalculator
{
    public function weeklyStatus(CarbonImmutable $date, array $daysOff): string
    {
        return in_array($date->dayOfWeekIso - 1, $daysOff, true) ? 'off' : 'work';
    }

    /** @return array<int, array<string, array{status: string, starts_at: ?string, ends_at: ?string, ends_next_day: bool}>> */
    public function calculate(int $companyId, array $employeeIds, string $from, string $to): array
    {
        $first = CarbonImmutable::parse($from)->startOfDay();
        $last = CarbonImmutable::parse($to)->startOfDay();
        if ($last->lessThan($first) || $first->diffInDays($last) > 366) {
            throw new \InvalidArgumentException('Intervalo de datas inválido ou superior a 367 dias.');
        }
        $assignments = EmployeeWorkPattern::query()->with('version')->where('company_id', $companyId)
            ->whereIn('employee_id', $employeeIds)->where('starts_on', '<=', $last->toDateString())
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', $first->toDateString()))
            ->get()->groupBy('employee_id');
        $overrides = ScheduleOverride::query()->where('company_id', $companyId)->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$first->toDateString(), $last->toDateString()])->orderBy('id')->get()->groupBy('employee_id');
        $result = [];
        foreach ($employeeIds as $employeeId) {
            $items = $assignments->get($employeeId, new Collection);
            $exceptions = $overrides->get($employeeId, new Collection)->keyBy('date');
            for ($date = $first; $date->lessThanOrEqualTo($last); $date = $date->addDay()) {
                $key = $date->toDateString();
                $assignment = $items->first(fn ($item) => $item->starts_on <= $key && ($item->ends_on === null || $item->ends_on >= $key));
                $override = $exceptions->get($key);
                $status = 'unscheduled';
                $version = $assignment?->version;
                if ($version) {
                    $offset = $version->kind === 'weekly'
                        ? $date->dayOfWeekIso - 1
                        : (int) CarbonImmutable::parse($assignment->reference_date)->startOfDay()->diffInDays($date, false);
                    $count = count($version->sequence);
                    $status = $version->sequence[(($offset % $count) + $count) % $count] ? 'work' : 'off';
                }
                if ($override) {
                    $status = $override->is_work ? 'work' : 'off';
                }
                $startsAt = $status === 'work' && $version?->starts_at ? substr($version->starts_at, 0, 5) : null;
                $endsAt = $status === 'work' && $version?->ends_at ? substr($version->ends_at, 0, 5) : null;
                $result[$employeeId][$key] = ['status' => $status, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'ends_next_day' => $startsAt !== null && $endsAt !== null && $endsAt < $startsAt];
            }
        }

        return $result;
    }
}
