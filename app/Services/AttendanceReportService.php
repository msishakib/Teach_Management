<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentAttendanceStatus;
use App\Enums\StudentStatus;
use App\Models\Batch;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceReportService
{
    /**
     * Monthly sheet data: one SQL query returning student/day/status rows.
     *
     * @return array{
     *     students: Collection<int, object>,
     *     days: list<string>,
     *     cells: array<int, array<string, string|null>>
     * }
     */
    public function monthlyBatchSheet(Batch $batch, int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        $rows = DB::table('students')
            ->whereExists(function ($query) use ($batch) {
                $query->select(DB::raw(1))
                    ->from('enrollments')
                    ->whereColumn('enrollments.student_id', 'students.id')
                    ->where('enrollments.batch_id', $batch->id)
                    ->where('enrollments.status', EnrollmentStatus::Active->value);
            })
            ->leftJoin('student_attendances', 'student_attendances.student_id', '=', 'students.id')
            ->leftJoin('attendance_sessions', function ($join) use ($batch, $start, $end) {
                $join->on('attendance_sessions.id', '=', 'student_attendances.attendance_session_id')
                    ->where('attendance_sessions.batch_id', '=', $batch->id)
                    ->whereBetween('attendance_sessions.date', [$start, $end]);
            })
            ->where('students.status', StudentStatus::Active->value)
            ->whereNull('students.deleted_at')
            ->orderBy('students.name')
            ->orderBy('attendance_sessions.date')
            ->select([
                'students.id as student_id',
                'students.admission_no',
                'students.name',
                'attendance_sessions.date as attendance_date',
                'student_attendances.status as attendance_status',
            ])
            ->get();

        $days = [];
        $period = new \DatePeriod(
            new \DateTimeImmutable($start),
            new \DateInterval('P1D'),
            (new \DateTimeImmutable($end))->modify('+1 day'),
        );

        foreach ($period as $day) {
            $days[] = $day->format('Y-m-d');
        }

        $students = collect();
        $cells = [];

        foreach ($rows as $row) {
            $studentId = (int) $row->student_id;

            if (! $students->has($studentId)) {
                $students->put($studentId, (object) [
                    'id' => $studentId,
                    'admission_no' => $row->admission_no,
                    'name' => $row->name,
                ]);
                $cells[$studentId] = array_fill_keys($days, null);
            }

            if ($row->attendance_date) {
                $cells[$studentId][$row->attendance_date] = $row->attendance_status;
            }
        }

        return [
            'students' => $students->values(),
            'days' => $days,
            'cells' => $cells,
        ];
    }

    /**
     * Per-student attendance summary with SQL-computed percentages.
     *
     * @return Collection<int, object>
     */
    public function studentSummaries(?int $batchId = null, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection
    {
        $attended = $this->attendedListSql();

        return DB::table('students')
            ->whereExists(function ($query) use ($batchId) {
                $query->select(DB::raw(1))
                    ->from('enrollments')
                    ->whereColumn('enrollments.student_id', 'students.id')
                    ->where('enrollments.status', EnrollmentStatus::Active->value)
                    ->when($batchId, fn ($q) => $q->where('enrollments.batch_id', $batchId));
            })
            ->leftJoin('student_attendances', 'student_attendances.student_id', '=', 'students.id')
            ->leftJoin('attendance_sessions', function ($join) use ($batchId, $from, $to) {
                $join->on('attendance_sessions.id', '=', 'student_attendances.attendance_session_id');

                if ($batchId) {
                    $join->where('attendance_sessions.batch_id', '=', $batchId);
                }

                if ($from) {
                    $join->whereDate('attendance_sessions.date', '>=', $from->toDateString());
                }

                if ($to) {
                    $join->whereDate('attendance_sessions.date', '<=', $to->toDateString());
                }
            })
            ->where('students.status', StudentStatus::Active->value)
            ->whereNull('students.deleted_at')
            ->groupBy('students.id', 'students.admission_no', 'students.name')
            ->orderBy('students.name')
            ->select([
                'students.id as student_id',
                'students.admission_no',
                'students.name',
                DB::raw('COUNT(student_attendances.id) as total_days'),
                DB::raw("SUM(CASE WHEN student_attendances.status IN ({$attended}) THEN 1 ELSE 0 END) as attended_days"),
                DB::raw("SUM(CASE WHEN student_attendances.status = 'absent' THEN 1 ELSE 0 END) as absent_days"),
                DB::raw("SUM(CASE WHEN student_attendances.status = 'late' THEN 1 ELSE 0 END) as late_days"),
                DB::raw("SUM(CASE WHEN student_attendances.status = 'excused' THEN 1 ELSE 0 END) as excused_days"),
                DB::raw("ROUND(
                    CASE
                        WHEN COUNT(student_attendances.id) = 0 THEN 0
                        ELSE (
                            SUM(CASE WHEN student_attendances.status IN ({$attended}) THEN 1 ELSE 0 END) * 100.0
                            / COUNT(student_attendances.id)
                        )
                    END
                , 2) as attendance_percent"),
            ])
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function absentToday(?int $batchId = null, ?CarbonInterface $date = null): Collection
    {
        $dateString = ($date ?? now())->toDateString();

        return DB::table('student_attendances')
            ->join('attendance_sessions', 'attendance_sessions.id', '=', 'student_attendances.attendance_session_id')
            ->join('students', 'students.id', '=', 'student_attendances.student_id')
            ->join('batches', 'batches.id', '=', 'attendance_sessions.batch_id')
            ->whereDate('attendance_sessions.date', $dateString)
            ->where('student_attendances.status', StudentAttendanceStatus::Absent->value)
            ->whereNull('students.deleted_at')
            ->when($batchId, fn ($q) => $q->where('attendance_sessions.batch_id', $batchId))
            ->orderBy('batches.name')
            ->orderBy('students.name')
            ->select([
                'students.id as student_id',
                'students.admission_no',
                'students.name',
                'students.phone',
                'batches.id as batch_id',
                'batches.name as batch_name',
                'attendance_sessions.id as session_id',
            ])
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function lowAttendance(?int $batchId = null, ?float $threshold = null, ?CarbonInterface $from = null, ?CarbonInterface $to = null): Collection
    {
        $threshold ??= (float) setting('attendance.min_percent', 75);
        $attended = $this->attendedListSql();

        return DB::table('students')
            ->whereExists(function ($query) use ($batchId) {
                $query->select(DB::raw(1))
                    ->from('enrollments')
                    ->whereColumn('enrollments.student_id', 'students.id')
                    ->where('enrollments.status', EnrollmentStatus::Active->value)
                    ->when($batchId, fn ($q) => $q->where('enrollments.batch_id', $batchId));
            })
            ->join('student_attendances', 'student_attendances.student_id', '=', 'students.id')
            ->join('attendance_sessions', function ($join) use ($batchId, $from, $to) {
                $join->on('attendance_sessions.id', '=', 'student_attendances.attendance_session_id');

                if ($batchId) {
                    $join->where('attendance_sessions.batch_id', '=', $batchId);
                }

                if ($from) {
                    $join->whereDate('attendance_sessions.date', '>=', $from->toDateString());
                }

                if ($to) {
                    $join->whereDate('attendance_sessions.date', '<=', $to->toDateString());
                }
            })
            ->where('students.status', StudentStatus::Active->value)
            ->whereNull('students.deleted_at')
            ->groupBy('students.id', 'students.admission_no', 'students.name')
            ->havingRaw(
                "ROUND(
                    SUM(CASE WHEN student_attendances.status IN ({$attended}) THEN 1 ELSE 0 END) * 100.0
                    / COUNT(student_attendances.id)
                , 2) < ?",
                [$threshold],
            )
            ->orderByRaw('attendance_percent asc')
            ->select([
                'students.id as student_id',
                'students.admission_no',
                'students.name',
                DB::raw('COUNT(student_attendances.id) as total_days'),
                DB::raw("SUM(CASE WHEN student_attendances.status IN ({$attended}) THEN 1 ELSE 0 END) as attended_days"),
                DB::raw("ROUND(
                    SUM(CASE WHEN student_attendances.status IN ({$attended}) THEN 1 ELSE 0 END) * 100.0
                    / COUNT(student_attendances.id)
                , 2) as attendance_percent"),
            ])
            ->get();
    }

    protected function attendedListSql(): string
    {
        return implode(',', array_map(
            static fn (string $status) => "'{$status}'",
            StudentAttendanceStatus::attendedValues(),
        ));
    }
}
