<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * 270 baris presensi dari "ABSENSI BULAN 1.pdf" (tanpa foto) untuk demo
 * & pembanding format laporan. Hanya untuk lingkungan pengembangan.
 */
class DemoAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::where('code', 'KDR')->firstOrFail();
        $location = $project->locations()->first();
        $shift = $project->defaultShift();
        $rows = json_decode(file_get_contents(database_path('data/absensi_bulan_1.json')), true);
        $employees = User::with('employee')->whereIn('username', array_unique(array_column($rows, 'username')))
            ->get()->mapWithKeys(fn ($u) => [$u->username => $u->employee]);

        foreach ($rows as $row) {
            $in = CarbonImmutable::parse("{$row['date']} {$row['check_in']}", $project->timezone);
            $out = CarbonImmutable::parse("{$row['date']} {$row['check_out']}", $project->timezone);

            $side = fn (CarbonImmutable $t) => [
                'at' => $t, 'lat' => $location->latitude, 'lng' => $location->longitude,
                'location_id' => $location->id, 'distance_m' => 0, 'mode' => Attendance::MODE_ONSITE, 'received_at' => $t,
            ];

            $attributes = ['status' => $row['status'], 'shift_id' => $shift->id, 'duration_minutes' => (int) $in->diffInMinutes($out), 'is_manual' => true, 'note' => 'Impor dari ABSENSI BULAN 1.pdf'];
            foreach (['check_in' => $side($in), 'check_out' => $side($out)] as $p => $values) {
                foreach ($values as $k => $v) {
                    $attributes["{$p}_{$k}"] = $v;
                }
            }

            Attendance::updateOrCreate([
                'employee_id' => $employees[$row['username']]->id,
                'project_id' => $project->id,
                'work_date' => $row['date'],
            ], $attributes);
        }
    }
}
