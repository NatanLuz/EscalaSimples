<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyScheduleReader
{
    public function snapshots(int $companyId, array $months): Collection
    {
        return DB::table('monthly_schedules as s')
            ->join('monthly_schedule_revisions as r', function ($join) {
                $join->on('r.monthly_schedule_id', '=', 's.id')->on('r.revision', '=', 's.revision');
            })->where('s.company_id', $companyId)->whereIn('s.month', $months)
            ->get(['s.month', 'r.snapshot'])->mapWithKeys(function ($item) {
                $snapshot = json_decode($item->snapshot, true, flags: JSON_THROW_ON_ERROR);

                return [$item->month => collect($snapshot['employees'])->keyBy('id')];
            });
    }

    public function apply(array $calculated, Collection $people, Collection $snapshots): array
    {
        foreach ($people as $person) {
            foreach ($calculated[$person->id] ?? [] as $date => $cell) {
                $month = substr($date, 0, 7);
                if (! $snapshots->has($month)) {
                    continue;
                }
                $row = $snapshots[$month]->get($person->public_id);
                $status = $row['days'][(int) substr($date, 8, 2) - 1] ?? 'unscheduled';
                $calculated[$person->id][$date]['status'] = $status;
                if ($status !== 'work') {
                    $calculated[$person->id][$date]['starts_at'] = null;
                    $calculated[$person->id][$date]['ends_at'] = null;
                    $calculated[$person->id][$date]['ends_next_day'] = false;
                }
            }
        }

        return $calculated;
    }
}
