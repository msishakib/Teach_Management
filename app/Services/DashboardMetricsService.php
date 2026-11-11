<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\AccountType;
use App\Enums\AttendanceSessionStatus;
use App\Enums\BatchStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\NoticeAudience;
use App\Enums\RoleName;
use App\Enums\StudentAttendanceStatus;
use App\Enums\StudentInvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\VoucherType;
use App\Enums\WeekDay;
use App\Models\User;
use App\Support\LedgerAccounts;
use App\Support\PortalAccess;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardMetricsService
{
    public const CACHE_SECONDS = 300;

    public function currencySymbol(): string
    {
        return (string) setting('general.currency_symbol', '৳');
    }

    public function formatMoney(float|int|string $amount): string
    {
        return $this->currencySymbol().number_format((float) $amount, 2);
    }

    /**
     * Today's posted receipt collection into cash/bank accounts vs yesterday.
     *
     * @return array{today_total: string, yesterday_total: string, today_receipts: int}
     */
    public function todaysCollection(?CarbonInterface $now = null): array
    {
        $now = $this->clock($now);
        $today = $now->toDateString();
        $yesterday = $now->copy()->subDay()->toDateString();

        return $this->remember("todays_collection:{$today}", function () use ($today, $yesterday): array {
            $row = DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
                ->where('journal_entries.status', JournalEntryStatus::Posted->value)
                ->where('journal_entries.voucher_type', VoucherType::Receipt->value)
                ->where('accounts.is_cash_or_bank', true)
                ->whereDate('journal_entries.entry_date', '>=', $yesterday)
                ->whereDate('journal_entries.entry_date', '<=', $today)
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN DATE(journal_entries.entry_date) = ? THEN journal_lines.debit ELSE 0 END), 0) as today_total,
                     COALESCE(SUM(CASE WHEN DATE(journal_entries.entry_date) = ? THEN journal_lines.debit ELSE 0 END), 0) as yesterday_total,
                     COUNT(DISTINCT CASE WHEN DATE(journal_entries.entry_date) = ? THEN journal_entries.id END) as today_receipts',
                    [$today, $yesterday, $today],
                )
                ->first();

            return [
                'today_total' => number_format((float) ($row->today_total ?? 0), 2, '.', ''),
                'yesterday_total' => number_format((float) ($row->yesterday_total ?? 0), 2, '.', ''),
                'today_receipts' => (int) ($row->today_receipts ?? 0),
            ];
        });
    }

    /**
     * Daily collection comparison for current calendar month vs previous month (days 1..31).
     *
     * @return array{
     *     labels: list<string>,
     *     this_month: list<float>,
     *     last_month: list<float>,
     *     this_month_label: string,
     *     last_month_label: string,
     *     this_month_total: string,
     *     last_month_total: string
     * }
     */
    public function monthlyCollectionComparison(?CarbonInterface $now = null): array
    {
        $now = $this->clock($now);
        $thisStart = $now->copy()->startOfMonth();
        $thisEnd = $now->copy()->endOfMonth();
        $lastStart = $now->copy()->subMonthNoOverflow()->startOfMonth();
        $lastEnd = $now->copy()->subMonthNoOverflow()->endOfMonth();

        $thisYm = $thisStart->format('Y-m');
        $lastYm = $lastStart->format('Y-m');

        return $this->remember("monthly_collection_vs_last:{$thisYm}", function () use (
            $thisStart,
            $thisEnd,
            $lastStart,
            $thisYm,
            $lastYm,
        ): array {
            $ymExpr = $this->yearMonthSql('journal_entries.entry_date');
            $dayExpr = $this->dayOfMonthSql('journal_entries.entry_date');

            $baseQuery = fn () => DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
                ->where('journal_entries.status', JournalEntryStatus::Posted->value)
                ->where('journal_entries.voucher_type', VoucherType::Receipt->value)
                ->where('accounts.is_cash_or_bank', true)
                ->whereDate('journal_entries.entry_date', '>=', $lastStart->toDateString())
                ->whereDate('journal_entries.entry_date', '<=', $thisEnd->toDateString());

            $dailyRows = $baseQuery()
                ->groupBy(DB::raw($ymExpr), DB::raw($dayExpr))
                ->selectRaw("{$ymExpr} as ym, {$dayExpr} as day_num, COALESCE(SUM(journal_lines.debit), 0) as total")
                ->get();

            $totals = $baseQuery()
                ->selectRaw(
                    "COALESCE(SUM(CASE WHEN {$ymExpr} = ? THEN journal_lines.debit ELSE 0 END), 0) as this_month_total,
                     COALESCE(SUM(CASE WHEN {$ymExpr} = ? THEN journal_lines.debit ELSE 0 END), 0) as last_month_total",
                    [$thisYm, $lastYm],
                )
                ->first();

            $thisSeries = array_fill(1, 31, 0.0);
            $lastSeries = array_fill(1, 31, 0.0);

            foreach ($dailyRows as $row) {
                $day = (int) $row->day_num;
                if ($day < 1 || $day > 31) {
                    continue;
                }

                if ($row->ym === $thisYm) {
                    $thisSeries[$day] = round((float) $row->total, 2);
                } elseif ($row->ym === $lastYm) {
                    $lastSeries[$day] = round((float) $row->total, 2);
                }
            }

            return [
                'labels' => array_map('strval', range(1, 31)),
                'this_month' => array_values($thisSeries),
                'last_month' => array_values($lastSeries),
                'this_month_label' => $thisStart->format('M Y'),
                'last_month_label' => $lastStart->format('M Y'),
                'this_month_total' => number_format((float) ($totals->this_month_total ?? 0), 2, '.', ''),
                'last_month_total' => number_format((float) ($totals->last_month_total ?? 0), 2, '.', ''),
            ];
        });
    }

    /**
     * Outstanding student fee receivables across active/non-deleted students.
     *
     * @return array{total_due: string, overdue_due: string, open_invoices: int, students_with_due: int}
     */
    public function totalDueSummary(?CarbonInterface $now = null): array
    {
        $today = $this->clock($now)->toDateString();

        return $this->remember("total_due:{$today}", function () use ($today): array {
            $row = DB::table('student_invoices')
                ->join('students', 'students.id', '=', 'student_invoices.student_id')
                ->whereIn('student_invoices.status', [
                    StudentInvoiceStatus::Unpaid->value,
                    StudentInvoiceStatus::Partial->value,
                ])
                ->where('student_invoices.due', '>', 0)
                ->whereNull('students.deleted_at')
                ->selectRaw(
                    'COALESCE(SUM(student_invoices.due), 0) as total_due,
                     COALESCE(SUM(CASE WHEN DATE(student_invoices.due_date) < ? THEN student_invoices.due ELSE 0 END), 0) as overdue_due,
                     COUNT(student_invoices.id) as open_invoices,
                     COUNT(DISTINCT student_invoices.student_id) as students_with_due',
                    [$today],
                )
                ->first();

            return [
                'total_due' => number_format((float) ($row->total_due ?? 0), 2, '.', ''),
                'overdue_due' => number_format((float) ($row->overdue_due ?? 0), 2, '.', ''),
                'open_invoices' => (int) ($row->open_invoices ?? 0),
                'students_with_due' => (int) ($row->students_with_due ?? 0),
            ];
        });
    }

    /**
     * Total posted expense for the current calendar month vs last month.
     *
     * @return array{this_month_expense: string, last_month_expense: string}
     */
    public function expenseThisMonth(?CarbonInterface $now = null): array
    {
        $now = $this->clock($now);
        $thisStart = $now->copy()->startOfMonth()->toDateString();
        $thisEnd = $now->copy()->endOfMonth()->toDateString();
        $lastStart = $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
        $lastEnd = $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
        $ym = $now->format('Y-m');

        return $this->remember("expense_this_month:{$ym}", function () use ($thisStart, $thisEnd, $lastStart, $lastEnd): array {
            $row = DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
                ->where('journal_entries.status', JournalEntryStatus::Posted->value)
                ->where('accounts.type', AccountType::Expense->value)
                ->where('accounts.is_group', false)
                ->whereDate('journal_entries.entry_date', '>=', $lastStart)
                ->whereDate('journal_entries.entry_date', '<=', $thisEnd)
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN DATE(journal_entries.entry_date) >= ? AND DATE(journal_entries.entry_date) <= ? THEN (journal_lines.debit - journal_lines.credit) ELSE 0 END), 0) as this_month_expense,
                     COALESCE(SUM(CASE WHEN DATE(journal_entries.entry_date) >= ? AND DATE(journal_entries.entry_date) <= ? THEN (journal_lines.debit - journal_lines.credit) ELSE 0 END), 0) as last_month_expense',
                    [$thisStart, $thisEnd, $lastStart, $lastEnd],
                )
                ->first();

            return [
                'this_month_expense' => number_format((float) ($row->this_month_expense ?? 0), 2, '.', ''),
                'last_month_expense' => number_format((float) ($row->last_month_expense ?? 0), 2, '.', ''),
            ];
        });
    }

    /**
     * Combined active cash & bank ledger balance (opening + posted net movements).
     *
     * @return array{total_balance: string, cash_balance: string, bank_balance: string, account_count: int}
     */
    public function cashBankBalance(): array
    {
        return $this->remember('cash_bank_balance', function (): array {
            $moves = DB::table('journal_lines')
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
                ->where('journal_entries.status', JournalEntryStatus::Posted->value)
                ->groupBy('journal_lines.account_id')
                ->selectRaw('journal_lines.account_id, COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as net_movement');

            $row = DB::table('accounts')
                ->leftJoinSub($moves, 'moves', function ($join): void {
                    $join->on('moves.account_id', '=', 'accounts.id');
                })
                ->where('accounts.is_cash_or_bank', true)
                ->where('accounts.is_group', false)
                ->where('accounts.status', AccountStatus::Active->value)
                ->selectRaw(
                    'COALESCE(SUM(accounts.opening_balance + COALESCE(moves.net_movement, 0)), 0) as total_balance,
                     COALESCE(SUM(CASE WHEN accounts.code = ? THEN accounts.opening_balance + COALESCE(moves.net_movement, 0) ELSE 0 END), 0) as bank_balance,
                     COALESCE(SUM(CASE WHEN accounts.code != ? THEN accounts.opening_balance + COALESCE(moves.net_movement, 0) ELSE 0 END), 0) as cash_balance,
                     COUNT(accounts.id) as account_count',
                    [LedgerAccounts::BANK, LedgerAccounts::BANK],
                )
                ->first();

            return [
                'total_balance' => number_format((float) ($row->total_balance ?? 0), 2, '.', ''),
                'cash_balance' => number_format((float) ($row->cash_balance ?? 0), 2, '.', ''),
                'bank_balance' => number_format((float) ($row->bank_balance ?? 0), 2, '.', ''),
                'account_count' => (int) ($row->account_count ?? 0),
            ];
        });
    }

    /**
     * New student admissions this month vs last month, plus pending admission applications.
     *
     * @return array{
     *     new_admissions_this_month: int,
     *     new_admissions_last_month: int,
     *     pending_applications: int,
     *     reviewing_applications: int
     * }
     */
    public function admissionsAndApplications(?CarbonInterface $now = null): array
    {
        $now = $this->clock($now);
        $thisStart = $now->copy()->startOfMonth()->toDateTimeString();
        $thisEnd = $now->copy()->endOfMonth()->toDateTimeString();
        $lastStart = $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateTimeString();
        $lastEnd = $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateTimeString();
        $ym = $now->format('Y-m');

        return $this->remember("admissions_applications:{$ym}", function () use ($thisStart, $thisEnd, $lastStart, $lastEnd): array {
            $studentsRow = DB::table('students')
                ->whereNull('deleted_at')
                ->where('created_at', '>=', $lastStart)
                ->where('created_at', '<=', $thisEnd)
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END), 0) as this_month_count,
                     COALESCE(SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END), 0) as last_month_count',
                    [$thisStart, $thisEnd, $lastStart, $lastEnd],
                )
                ->first();

            $pendingCount = 0;
            $reviewingCount = 0;

            if (Schema::hasTable('admission_applications')) {
                $appsRow = DB::table('admission_applications')
                    ->whereIn('status', ['pending', 'reviewing'])
                    ->selectRaw(
                        "COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending_count,
                         COALESCE(SUM(CASE WHEN status = 'reviewing' THEN 1 ELSE 0 END), 0) as reviewing_count",
                    )
                    ->first();

                $pendingCount = (int) ($appsRow->pending_count ?? 0);
                $reviewingCount = (int) ($appsRow->reviewing_count ?? 0);
            }

            return [
                'new_admissions_this_month' => (int) ($studentsRow->this_month_count ?? 0),
                'new_admissions_last_month' => (int) ($studentsRow->last_month_count ?? 0),
                'pending_applications' => $pendingCount,
                'reviewing_applications' => $reviewingCount,
            ];
        });
    }

    /**
     * Active enrolled student count grouped by active course (SQL aggregate).
     *
     * @return array{labels: list<string>, counts: list<int>, total_enrollments: int}
     */
    public function studentsByCourse(): array
    {
        return $this->remember('students_by_course', function (): array {
            $rows = DB::table('courses')
                ->leftJoin('batches', 'batches.course_id', '=', 'courses.id')
                ->leftJoin('enrollments', function ($join): void {
                    $join->on('enrollments.batch_id', '=', 'batches.id')
                        ->where('enrollments.status', '=', EnrollmentStatus::Active->value);
                })
                ->leftJoin('students', function ($join): void {
                    $join->on('students.id', '=', 'enrollments.student_id')
                        ->whereNull('students.deleted_at')
                        ->where('students.status', '=', StudentStatus::Active->value);
                })
                ->whereNull('courses.deleted_at')
                ->where('courses.is_active', true)
                ->groupBy('courses.id', 'courses.name', 'courses.code')
                ->orderByRaw('COUNT(DISTINCT students.id) DESC')
                ->orderBy('courses.name')
                ->selectRaw('courses.name, courses.code, COUNT(DISTINCT students.id) as student_count')
                ->get();

            $totalRow = DB::table('enrollments')
                ->join('batches', 'batches.id', '=', 'enrollments.batch_id')
                ->join('courses', 'courses.id', '=', 'batches.course_id')
                ->join('students', 'students.id', '=', 'enrollments.student_id')
                ->where('enrollments.status', EnrollmentStatus::Active->value)
                ->whereNull('courses.deleted_at')
                ->where('courses.is_active', true)
                ->whereNull('students.deleted_at')
                ->where('students.status', StudentStatus::Active->value)
                ->selectRaw('COUNT(DISTINCT students.id) as total_students')
                ->first();

            $labels = [];
            $counts = [];

            foreach ($rows as $row) {
                $labels[] = (string) $row->name;
                $counts[] = (int) $row->student_count;
            }

            return [
                'labels' => $labels,
                'counts' => $counts,
                'total_enrollments' => (int) ($totalRow->total_students ?? 0),
            ];
        });
    }

    /**
     * Today's overall student attendance percentage across all sessions.
     *
     * @return array{percentage: float, attended_count: int, absent_count: int, total_marked: int, sessions_count: int}
     */
    public function todaysAttendanceSummary(?CarbonInterface $now = null): array
    {
        $today = $this->clock($now)->toDateString();
        $attendedSql = $this->attendedStatusSqlList();

        return $this->remember("todays_attendance:{$today}", function () use ($today, $attendedSql): array {
            $row = DB::table('attendance_sessions')
                ->leftJoin('student_attendances', 'student_attendances.attendance_session_id', '=', 'attendance_sessions.id')
                ->leftJoin('students', function ($join): void {
                    $join->on('students.id', '=', 'student_attendances.student_id')
                        ->whereNull('students.deleted_at');
                })
                ->whereDate('attendance_sessions.date', $today)
                ->selectRaw("
                    COUNT(DISTINCT attendance_sessions.id) as sessions_count,
                    COUNT(students.id) as total_marked,
                    COALESCE(SUM(CASE WHEN students.id IS NOT NULL AND student_attendances.status IN ({$attendedSql}) THEN 1 ELSE 0 END), 0) as attended_count,
                    COALESCE(SUM(CASE WHEN students.id IS NOT NULL AND student_attendances.status = 'absent' THEN 1 ELSE 0 END), 0) as absent_count,
                    ROUND(
                        CASE
                            WHEN COUNT(students.id) = 0 THEN 0
                            ELSE SUM(CASE WHEN students.id IS NOT NULL AND student_attendances.status IN ({$attendedSql}) THEN 1 ELSE 0 END) * 100.0 / COUNT(students.id)
                        END
                    , 1) as percentage
                ")
                ->first();

            return [
                'percentage' => (float) ($row->percentage ?? 0.0),
                'attended_count' => (int) ($row->attended_count ?? 0),
                'absent_count' => (int) ($row->absent_count ?? 0),
                'total_marked' => (int) ($row->total_marked ?? 0),
                'sessions_count' => (int) ($row->sessions_count ?? 0),
            ];
        });
    }

    /**
     * Upcoming / ongoing exams aggregate summary + top 5 nearest exams.
     *
     * @return array{
     *     upcoming_count: int,
     *     scheduled_count: int,
     *     ongoing_count: int,
     *     next_exam_date: ?string,
     *     items: list<array{id: int, name: string, batch_name: string, start_date: string, end_date: string, status: string, subjects_count: int}>
     * }
     */
    public function upcomingExamsSummary(?CarbonInterface $now = null): array
    {
        $today = $this->clock($now)->toDateString();

        return $this->remember("upcoming_exams:{$today}", function () use ($today): array {
            $statuses = [ExamStatus::Scheduled->value, ExamStatus::Ongoing->value];

            $agg = DB::table('exams')
                ->whereIn('exams.status', $statuses)
                ->whereDate('exams.end_date', '>=', $today)
                ->selectRaw(
                    "COUNT(exams.id) as upcoming_count,
                     COALESCE(SUM(CASE WHEN exams.status = 'scheduled' THEN 1 ELSE 0 END), 0) as scheduled_count,
                     COALESCE(SUM(CASE WHEN exams.status = 'ongoing' THEN 1 ELSE 0 END), 0) as ongoing_count,
                     MIN(exams.start_date) as next_exam_date",
                )
                ->first();

            $rows = DB::table('exams')
                ->join('batches', 'batches.id', '=', 'exams.batch_id')
                ->leftJoin('exam_subjects', 'exam_subjects.exam_id', '=', 'exams.id')
                ->whereIn('exams.status', $statuses)
                ->whereDate('exams.end_date', '>=', $today)
                ->groupBy('exams.id', 'exams.name', 'exams.start_date', 'exams.end_date', 'exams.status', 'batches.name')
                ->orderBy('exams.start_date')
                ->orderBy('exams.id')
                ->limit(5)
                ->selectRaw('
                    exams.id,
                    exams.name,
                    batches.name as batch_name,
                    exams.start_date,
                    exams.end_date,
                    exams.status,
                    COUNT(exam_subjects.id) as subjects_count
                ')
                ->get();

            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                    'batch_name' => (string) $row->batch_name,
                    'start_date' => (string) $row->start_date,
                    'end_date' => (string) $row->end_date,
                    'status' => (string) $row->status,
                    'subjects_count' => (int) $row->subjects_count,
                ];
            }

            return [
                'upcoming_count' => (int) ($agg->upcoming_count ?? 0),
                'scheduled_count' => (int) ($agg->scheduled_count ?? 0),
                'ongoing_count' => (int) ($agg->ongoing_count ?? 0),
                'next_exam_date' => $agg?->next_exam_date ? (string) $agg->next_exam_date : null,
                'items' => $items,
            ];
        });
    }

    /**
     * Active published notices summary + 5 most recent notices for Admin dashboard.
     *
     * @return array{
     *     active_count: int,
     *     pinned_count: int,
     *     items: list<array{id: int, title: string, audience: string, batch_name: ?string, published_at: string, is_pinned: bool}>
     * }
     */
    public function recentNoticesSummary(?CarbonInterface $now = null): array
    {
        $now = $this->clock($now);

        return $this->remember('recent_notices', function () use ($now): array {
            $hasPinned = Schema::hasColumn('notices', 'is_pinned');
            $hasExpires = Schema::hasColumn('notices', 'expires_at');

            $base = fn () => DB::table('notices')
                ->where('notices.is_active', true)
                ->whereNotNull('notices.published_at')
                ->where('notices.published_at', '<=', $now->toDateTimeString())
                ->when($hasExpires, function ($query) use ($now): void {
                    $query->where(function ($inner) use ($now): void {
                        $inner->whereNull('notices.expires_at')
                            ->orWhere('notices.expires_at', '>', $now->toDateTimeString());
                    });
                });

            $pinnedSelect = $hasPinned
                ? 'COALESCE(SUM(CASE WHEN notices.is_pinned = 1 THEN 1 ELSE 0 END), 0) as pinned_count'
                : '0 as pinned_count';

            $agg = $base()
                ->selectRaw("COUNT(notices.id) as active_count, {$pinnedSelect}")
                ->first();

            $rowsQuery = $base()
                ->leftJoin('batches', 'batches.id', '=', 'notices.batch_id');

            if ($hasPinned) {
                $rowsQuery->orderByDesc('notices.is_pinned');
            }

            $rows = $rowsQuery
                ->orderByDesc('notices.published_at')
                ->orderByDesc('notices.id')
                ->limit(5)
                ->select([
                    'notices.id',
                    'notices.title',
                    'notices.audience',
                    'notices.published_at',
                    'batches.name as batch_name',
                    ...($hasPinned ? ['notices.is_pinned'] : []),
                ])
                ->get();

            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'id' => (int) $row->id,
                    'title' => (string) $row->title,
                    'audience' => (string) $row->audience,
                    'batch_name' => $row->batch_name !== null ? (string) $row->batch_name : null,
                    'published_at' => (string) $row->published_at,
                    'is_pinned' => $hasPinned && (bool) ($row->is_pinned ?? false),
                ];
            }

            return [
                'active_count' => (int) ($agg->active_count ?? 0),
                'pinned_count' => (int) ($agg->pinned_count ?? 0),
                'items' => $items,
            ];
        });
    }

    /**
     * Teacher's scheduled classes for today's weekday across active/open batches.
     *
     * @return array{
     *     classes_count: int,
     *     batches_count: int,
     *     first_start: ?string,
     *     last_end: ?string,
     *     items: list<array{id: int, batch_name: string, course_name: string, subject_name: string, start_time: string, end_time: string, student_count: int}>
     * }
     */
    public function teacherTodaysClasses(int $teacherId, ?CarbonInterface $now = null): array
    {
        $now = $this->clock($now);
        $today = $now->toDateString();
        $weekday = strtolower($now->englishDayOfWeek);

        return $this->remember("teacher:{$teacherId}:todays_classes:{$today}", function () use ($teacherId, $weekday): array {
            $inactiveBatchStatuses = [BatchStatus::Completed->value, BatchStatus::Cancelled->value];

            $agg = DB::table('class_routines')
                ->join('batches', 'batches.id', '=', 'class_routines.batch_id')
                ->where('class_routines.teacher_id', $teacherId)
                ->where('class_routines.day', $weekday)
                ->whereNotIn('batches.status', $inactiveBatchStatuses)
                ->selectRaw('
                    COUNT(class_routines.id) as classes_count,
                    COUNT(DISTINCT class_routines.batch_id) as batches_count,
                    MIN(TIME(class_routines.start_time)) as first_start,
                    MAX(TIME(class_routines.end_time)) as last_end
                ')
                ->first();

            $rows = DB::table('class_routines')
                ->join('batches', 'batches.id', '=', 'class_routines.batch_id')
                ->join('courses', 'courses.id', '=', 'batches.course_id')
                ->join('subjects', 'subjects.id', '=', 'class_routines.subject_id')
                ->leftJoin('enrollments', function ($join): void {
                    $join->on('enrollments.batch_id', '=', 'batches.id')
                        ->where('enrollments.status', '=', EnrollmentStatus::Active->value);
                })
                ->leftJoin('students', function ($join): void {
                    $join->on('students.id', '=', 'enrollments.student_id')
                        ->whereNull('students.deleted_at')
                        ->where('students.status', '=', StudentStatus::Active->value);
                })
                ->where('class_routines.teacher_id', $teacherId)
                ->where('class_routines.day', $weekday)
                ->whereNotIn('batches.status', $inactiveBatchStatuses)
                ->groupBy(
                    'class_routines.id',
                    'batches.name',
                    'courses.name',
                    'subjects.name',
                    'class_routines.start_time',
                    'class_routines.end_time',
                )
                ->orderByRaw('TIME(class_routines.start_time) ASC')
                ->limit(10)
                ->selectRaw('
                    class_routines.id,
                    batches.name as batch_name,
                    courses.name as course_name,
                    subjects.name as subject_name,
                    TIME(class_routines.start_time) as start_time,
                    TIME(class_routines.end_time) as end_time,
                    COUNT(DISTINCT students.id) as student_count
                ')
                ->get();

            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'id' => (int) $row->id,
                    'batch_name' => (string) $row->batch_name,
                    'course_name' => (string) $row->course_name,
                    'subject_name' => (string) $row->subject_name,
                    'start_time' => $this->formatTimeSlot($row->start_time) ?? '',
                    'end_time' => $this->formatTimeSlot($row->end_time) ?? '',
                    'student_count' => (int) $row->student_count,
                ];
            }

            return [
                'classes_count' => (int) ($agg->classes_count ?? 0),
                'batches_count' => (int) ($agg->batches_count ?? 0),
                'first_start' => $this->formatTimeSlot($agg?->first_start),
                'last_end' => $this->formatTimeSlot($agg?->last_end),
                'items' => $items,
            ];
        });
    }

    /**
     * Pending attendance for a teacher: today's scheduled classes without finalized attendance + open sessions.
     *
     * @return array{
     *     pending_today_count: int,
     *     open_sessions_count: int,
     *     items: list<array{batch_name: string, subject_name: string, start_time: string, state: string}>
     * }
     */
    public function teacherPendingAttendance(int $teacherId, ?CarbonInterface $now = null): array
    {
        $now = $this->clock($now);
        $today = $now->toDateString();
        $weekday = strtolower($now->englishDayOfWeek);

        return $this->remember("teacher:{$teacherId}:pending_attendance:{$today}", function () use ($teacherId, $today, $weekday): array {
            $inactiveBatchStatuses = [BatchStatus::Completed->value, BatchStatus::Cancelled->value];

            $notFinalizedToday = function ($query) use ($today): void {
                $query->whereNotExists(function ($sub) use ($today): void {
                    $sub->select(DB::raw(1))
                        ->from('attendance_sessions')
                        ->whereColumn('attendance_sessions.batch_id', 'class_routines.batch_id')
                        ->whereDate('attendance_sessions.date', $today)
                        ->where('attendance_sessions.status', AttendanceSessionStatus::Finalized->value)
                        ->where(function ($matchSubject): void {
                            $matchSubject->whereColumn('attendance_sessions.subject_id', 'class_routines.subject_id')
                                ->orWhereNull('attendance_sessions.subject_id');
                        });
                });
            };

            $pendingTodayCount = (int) (DB::table('class_routines')
                ->join('batches', 'batches.id', '=', 'class_routines.batch_id')
                ->where('class_routines.teacher_id', $teacherId)
                ->where('class_routines.day', $weekday)
                ->whereNotIn('batches.status', $inactiveBatchStatuses)
                ->tap($notFinalizedToday)
                ->selectRaw('COUNT(class_routines.id) as aggregate_count')
                ->value('aggregate_count') ?? 0);

            $openSessionsCount = (int) (DB::table('attendance_sessions')
                ->where('teacher_id', $teacherId)
                ->where('status', AttendanceSessionStatus::Open->value)
                ->selectRaw('COUNT(id) as aggregate_count')
                ->value('aggregate_count') ?? 0);

            $rows = DB::table('class_routines')
                ->join('batches', 'batches.id', '=', 'class_routines.batch_id')
                ->join('subjects', 'subjects.id', '=', 'class_routines.subject_id')
                ->where('class_routines.teacher_id', $teacherId)
                ->where('class_routines.day', $weekday)
                ->whereNotIn('batches.status', $inactiveBatchStatuses)
                ->tap($notFinalizedToday)
                ->orderByRaw('TIME(class_routines.start_time) ASC')
                ->limit(8)
                ->selectRaw(
                    "batches.name as batch_name,
                     subjects.name as subject_name,
                     TIME(class_routines.start_time) as start_time,
                     CASE WHEN EXISTS (
                         SELECT 1 FROM attendance_sessions
                         WHERE attendance_sessions.batch_id = class_routines.batch_id
                           AND DATE(attendance_sessions.date) = ?
                           AND attendance_sessions.status = ?
                           AND (attendance_sessions.subject_id = class_routines.subject_id OR attendance_sessions.subject_id IS NULL)
                     ) THEN 'open' ELSE 'not_taken' END as state",
                    [$today, AttendanceSessionStatus::Open->value],
                )
                ->get();

            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'batch_name' => (string) $row->batch_name,
                    'subject_name' => (string) $row->subject_name,
                    'start_time' => $this->formatTimeSlot($row->start_time) ?? '',
                    'state' => (string) $row->state,
                ];
            }

            return [
                'pending_today_count' => $pendingTodayCount,
                'open_sessions_count' => $openSessionsCount,
                'items' => $items,
            ];
        });
    }

    /**
     * Pending marks entry for a teacher across exams in `marks_entry` status.
     *
     * @return array{
     *     pending_subjects_count: int,
     *     pending_marks_count: int,
     *     items: list<array{exam_subject_id: int, exam_name: string, batch_name: string, subject_name: string, exam_date: string, completed_count: int, enrolled_count: int}>
     * }
     */
    public function teacherPendingMarksEntry(int $teacherId): array
    {
        return $this->remember("teacher:{$teacherId}:pending_marks", function () use ($teacherId): array {
            $batchEnrolled = DB::table('enrollments')
                ->join('students', 'students.id', '=', 'enrollments.student_id')
                ->where('enrollments.status', EnrollmentStatus::Active->value)
                ->where('students.status', StudentStatus::Active->value)
                ->whereNull('students.deleted_at')
                ->groupBy('enrollments.batch_id')
                ->selectRaw('enrollments.batch_id, COUNT(DISTINCT students.id) as enrolled_count');

            $completedMarks = DB::table('exam_marks')
                ->join('students', 'students.id', '=', 'exam_marks.student_id')
                ->whereNull('students.deleted_at')
                ->where(function ($inner): void {
                    $inner->where('exam_marks.is_absent', true)
                        ->orWhereNotNull('exam_marks.marks_obtained');
                })
                ->groupBy('exam_marks.exam_subject_id')
                ->selectRaw('exam_marks.exam_subject_id, COUNT(exam_marks.id) as completed_count');

            $base = fn () => DB::table('exam_subjects')
                ->join('exams', 'exams.id', '=', 'exam_subjects.exam_id')
                ->join('batches', 'batches.id', '=', 'exams.batch_id')
                ->join('subjects', 'subjects.id', '=', 'exam_subjects.subject_id')
                ->leftJoinSub($batchEnrolled, 'batch_enrolled', function ($join): void {
                    $join->on('batch_enrolled.batch_id', '=', 'exams.batch_id');
                })
                ->leftJoinSub($completedMarks, 'subject_completed', function ($join): void {
                    $join->on('subject_completed.exam_subject_id', '=', 'exam_subjects.id');
                })
                ->where('exam_subjects.teacher_id', $teacherId)
                ->where('exams.status', ExamStatus::MarksEntry->value)
                ->whereRaw('COALESCE(batch_enrolled.enrolled_count, 0) > COALESCE(subject_completed.completed_count, 0)');

            $agg = $base()
                ->selectRaw('
                    COUNT(exam_subjects.id) as pending_subjects_count,
                    COALESCE(SUM(COALESCE(batch_enrolled.enrolled_count, 0) - COALESCE(subject_completed.completed_count, 0)), 0) as pending_marks_count
                ')
                ->first();

            $rows = $base()
                ->orderBy('exam_subjects.exam_date')
                ->orderBy('exam_subjects.id')
                ->limit(8)
                ->selectRaw('
                    exam_subjects.id as exam_subject_id,
                    exams.name as exam_name,
                    batches.name as batch_name,
                    subjects.name as subject_name,
                    exam_subjects.exam_date,
                    COALESCE(subject_completed.completed_count, 0) as completed_count,
                    COALESCE(batch_enrolled.enrolled_count, 0) as enrolled_count
                ')
                ->get();

            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'exam_subject_id' => (int) $row->exam_subject_id,
                    'exam_name' => (string) $row->exam_name,
                    'batch_name' => (string) $row->batch_name,
                    'subject_name' => (string) $row->subject_name,
                    'exam_date' => (string) $row->exam_date,
                    'completed_count' => (int) $row->completed_count,
                    'enrolled_count' => (int) $row->enrolled_count,
                ];
            }

            return [
                'pending_subjects_count' => (int) ($agg->pending_subjects_count ?? 0),
                'pending_marks_count' => (int) ($agg->pending_marks_count ?? 0),
                'items' => $items,
            ];
        });
    }

    /**
     * Student / guardian attendance summary across accessible student IDs.
     *
     * @param  list<int>  $studentIds
     * @return array{percentage: float, attended_count: int, absent_count: int, late_count: int, total_sessions: int}
     */
    public function studentAttendanceSummary(array $studentIds): array
    {
        $studentIds = $this->normalizeIds($studentIds);

        if ($studentIds === []) {
            return [
                'percentage' => 0.0,
                'attended_count' => 0,
                'absent_count' => 0,
                'late_count' => 0,
                'total_sessions' => 0,
            ];
        }

        $key = 'student_attendance:'.$this->idsHash($studentIds);
        $attendedSql = $this->attendedStatusSqlList();

        return $this->remember($key, function () use ($studentIds, $attendedSql): array {
            $row = DB::table('student_attendances')
                ->join('attendance_sessions', 'attendance_sessions.id', '=', 'student_attendances.attendance_session_id')
                ->whereIn('student_attendances.student_id', $studentIds)
                ->selectRaw("
                    COUNT(student_attendances.id) as total_sessions,
                    COALESCE(SUM(CASE WHEN student_attendances.status IN ({$attendedSql}) THEN 1 ELSE 0 END), 0) as attended_count,
                    COALESCE(SUM(CASE WHEN student_attendances.status = 'absent' THEN 1 ELSE 0 END), 0) as absent_count,
                    COALESCE(SUM(CASE WHEN student_attendances.status = 'late' THEN 1 ELSE 0 END), 0) as late_count,
                    ROUND(
                        CASE
                            WHEN COUNT(student_attendances.id) = 0 THEN 0
                            ELSE SUM(CASE WHEN student_attendances.status IN ({$attendedSql}) THEN 1 ELSE 0 END) * 100.0 / COUNT(student_attendances.id)
                        END
                    , 1) as percentage
                ")
                ->first();

            return [
                'percentage' => (float) ($row->percentage ?? 0.0),
                'attended_count' => (int) ($row->attended_count ?? 0),
                'absent_count' => (int) ($row->absent_count ?? 0),
                'late_count' => (int) ($row->late_count ?? 0),
                'total_sessions' => (int) ($row->total_sessions ?? 0),
            ];
        });
    }

    /**
     * Next upcoming class slot across active enrolled batches for a student/guardian.
     *
     * @param  list<int>  $studentIds
     * @return array{
     *     weekly_classes_count: int,
     *     next_class: ?array{day: string, day_label: string, start_time: string, end_time: string, subject_name: string, batch_name: string, teacher_name: string, is_today: bool}
     * }
     */
    public function studentNextClass(array $studentIds, ?CarbonInterface $now = null): array
    {
        $studentIds = $this->normalizeIds($studentIds);

        if ($studentIds === []) {
            return [
                'weekly_classes_count' => 0,
                'next_class' => null,
            ];
        }

        $now = $this->clock($now);
        $bucket = $now->format('Y-m-d-H').':'.floor((int) $now->format('i') / 5);
        $key = 'student_next_class:'.$this->idsHash($studentIds).':'.$bucket;

        return $this->remember($key, function () use ($studentIds, $now): array {
            $inactiveBatchStatuses = [BatchStatus::Completed->value, BatchStatus::Cancelled->value];

            $base = fn () => DB::table('class_routines')
                ->join('batches', 'batches.id', '=', 'class_routines.batch_id')
                ->join('subjects', 'subjects.id', '=', 'class_routines.subject_id')
                ->join('teachers', 'teachers.id', '=', 'class_routines.teacher_id')
                ->whereNotIn('batches.status', $inactiveBatchStatuses)
                ->whereExists(function ($sub) use ($studentIds): void {
                    $sub->select(DB::raw(1))
                        ->from('enrollments')
                        ->whereColumn('enrollments.batch_id', 'class_routines.batch_id')
                        ->whereIn('enrollments.student_id', $studentIds)
                        ->where('enrollments.status', EnrollmentStatus::Active->value);
                });

            $weeklyCount = (int) ($base()
                ->selectRaw('COUNT(class_routines.id) as aggregate_count')
                ->value('aggregate_count') ?? 0);

            if ($weeklyCount === 0) {
                return [
                    'weekly_classes_count' => 0,
                    'next_class' => null,
                ];
            }

            $dayOrderCase = $this->upcomingWeekdayOffsetSql('class_routines.day', 'class_routines.end_time', $now);

            $next = $base()
                ->orderByRaw("{$dayOrderCase} ASC")
                ->orderByRaw('TIME(class_routines.start_time) ASC')
                ->selectRaw("
                    class_routines.day,
                    TIME(class_routines.start_time) as start_time,
                    TIME(class_routines.end_time) as end_time,
                    subjects.name as subject_name,
                    batches.name as batch_name,
                    teachers.name as teacher_name,
                    ({$dayOrderCase}) as day_offset
                ")
                ->first();

            if (! $next) {
                return [
                    'weekly_classes_count' => $weeklyCount,
                    'next_class' => null,
                ];
            }

            $weekDay = WeekDay::tryFrom((string) $next->day);

            return [
                'weekly_classes_count' => $weeklyCount,
                'next_class' => [
                    'day' => (string) $next->day,
                    'day_label' => $weekDay?->getLabel() ?? ucfirst((string) $next->day),
                    'start_time' => $this->formatTimeSlot($next->start_time) ?? '',
                    'end_time' => $this->formatTimeSlot($next->end_time) ?? '',
                    'subject_name' => (string) $next->subject_name,
                    'batch_name' => (string) $next->batch_name,
                    'teacher_name' => (string) $next->teacher_name,
                    'is_today' => (int) $next->day_offset === 0,
                ],
            ];
        });
    }

    /**
     * Outstanding fee invoices summary for a student/guardian.
     *
     * @param  list<int>  $studentIds
     * @return array{total_due: string, overdue_due: string, open_invoices_count: int, next_due_date: ?string}
     */
    public function studentDueFees(array $studentIds, ?CarbonInterface $now = null): array
    {
        $studentIds = $this->normalizeIds($studentIds);

        if ($studentIds === []) {
            return [
                'total_due' => '0.00',
                'overdue_due' => '0.00',
                'open_invoices_count' => 0,
                'next_due_date' => null,
            ];
        }

        $today = $this->clock($now)->toDateString();
        $key = 'student_due_fees:'.$this->idsHash($studentIds).":{$today}";

        return $this->remember($key, function () use ($studentIds, $today): array {
            $row = DB::table('student_invoices')
                ->whereIn('student_id', $studentIds)
                ->whereIn('status', [
                    StudentInvoiceStatus::Unpaid->value,
                    StudentInvoiceStatus::Partial->value,
                ])
                ->where('due', '>', 0)
                ->selectRaw(
                    'COALESCE(SUM(due), 0) as total_due,
                     COALESCE(SUM(CASE WHEN DATE(due_date) < ? THEN due ELSE 0 END), 0) as overdue_due,
                     COUNT(id) as open_invoices_count,
                     MIN(due_date) as next_due_date',
                    [$today],
                )
                ->first();

            return [
                'total_due' => number_format((float) ($row->total_due ?? 0), 2, '.', ''),
                'overdue_due' => number_format((float) ($row->overdue_due ?? 0), 2, '.', ''),
                'open_invoices_count' => (int) ($row->open_invoices_count ?? 0),
                'next_due_date' => $row?->next_due_date ? (string) $row->next_due_date : null,
            ];
        });
    }

    /**
     * Latest published exam result + aggregate GPA/percentage for a student/guardian.
     *
     * @param  list<int>  $studentIds
     * @return array{
     *     published_count: int,
     *     avg_gpa: string,
     *     avg_percentage: string,
     *     latest: ?array{exam_name: string, student_name: string, gpa: string, letter_grade: ?string, percentage: string, total_marks: string, full_marks: string, status: string, rank_in_batch: ?int, published_at: string}
     * }
     */
    public function studentLatestResult(array $studentIds): array
    {
        $studentIds = $this->normalizeIds($studentIds);

        if ($studentIds === []) {
            return [
                'published_count' => 0,
                'avg_gpa' => '0.00',
                'avg_percentage' => '0.0',
                'latest' => null,
            ];
        }

        $key = 'student_latest_result:'.$this->idsHash($studentIds);

        return $this->remember($key, function () use ($studentIds): array {
            $agg = DB::table('exam_results')
                ->whereIn('student_id', $studentIds)
                ->whereNotNull('published_at')
                ->selectRaw('
                    COUNT(id) as published_count,
                    COALESCE(ROUND(AVG(gpa), 2), 0) as avg_gpa,
                    COALESCE(ROUND(AVG(percentage), 1), 0) as avg_percentage
                ')
                ->first();

            $latestRow = DB::table('exam_results')
                ->join('exams', 'exams.id', '=', 'exam_results.exam_id')
                ->join('students', 'students.id', '=', 'exam_results.student_id')
                ->whereIn('exam_results.student_id', $studentIds)
                ->whereNotNull('exam_results.published_at')
                ->orderByDesc('exam_results.published_at')
                ->orderByDesc('exam_results.id')
                ->select([
                    'exams.name as exam_name',
                    'students.name as student_name',
                    'exam_results.gpa',
                    'exam_results.letter_grade',
                    'exam_results.percentage',
                    'exam_results.total_marks',
                    'exam_results.full_marks',
                    'exam_results.status',
                    'exam_results.rank_in_batch',
                    'exam_results.published_at',
                ])
                ->first();

            return [
                'published_count' => (int) ($agg->published_count ?? 0),
                'avg_gpa' => number_format((float) ($agg->avg_gpa ?? 0), 2, '.', ''),
                'avg_percentage' => number_format((float) ($agg->avg_percentage ?? 0), 1, '.', ''),
                'latest' => $latestRow ? [
                    'exam_name' => (string) $latestRow->exam_name,
                    'student_name' => (string) $latestRow->student_name,
                    'gpa' => number_format((float) $latestRow->gpa, 2, '.', ''),
                    'letter_grade' => $latestRow->letter_grade !== null ? (string) $latestRow->letter_grade : null,
                    'percentage' => number_format((float) $latestRow->percentage, 1, '.', ''),
                    'total_marks' => number_format((float) $latestRow->total_marks, 2, '.', ''),
                    'full_marks' => number_format((float) $latestRow->full_marks, 2, '.', ''),
                    'status' => (string) $latestRow->status,
                    'rank_in_batch' => $latestRow->rank_in_batch !== null ? (int) $latestRow->rank_in_batch : null,
                    'published_at' => (string) $latestRow->published_at,
                ] : null,
            ];
        });
    }

    /**
     * Published active notices scoped to a student/guardian portal user.
     *
     * @return array{
     *     total_count: int,
     *     items: list<array{id: int, title: string, body: string, batch_name: ?string, published_at: string, is_pinned: bool}>
     * }
     */
    public function studentNotices(User $user, ?CarbonInterface $now = null): array
    {
        $studentIds = $this->normalizeIds(PortalAccess::accessibleStudentIds($user) ?? []);
        $isGuardian = (bool) ($user->guardian || $user->hasRole(RoleName::Guardian->value));

        if ($studentIds === [] && ! $isGuardian) {
            return [
                'total_count' => 0,
                'items' => [],
            ];
        }

        $now = $this->clock($now);
        $key = 'student_notices:'.($isGuardian ? 'g' : 's').':'.$this->idsHash($studentIds);

        return $this->remember($key, function () use ($studentIds, $isGuardian, $now): array {
            $hasPinned = Schema::hasColumn('notices', 'is_pinned');
            $hasExpires = Schema::hasColumn('notices', 'expires_at');

            $audiences = [
                NoticeAudience::All->value,
                NoticeAudience::Students->value,
            ];

            if ($isGuardian) {
                $audiences[] = NoticeAudience::Guardians->value;
            }

            $base = fn () => DB::table('notices')
                ->where('notices.is_active', true)
                ->whereNotNull('notices.published_at')
                ->where('notices.published_at', '<=', $now->toDateTimeString())
                ->when($hasExpires, function ($query) use ($now): void {
                    $query->where(function ($inner) use ($now): void {
                        $inner->whereNull('notices.expires_at')
                            ->orWhere('notices.expires_at', '>', $now->toDateTimeString());
                    });
                })
                ->where(function ($visibility) use ($audiences, $studentIds): void {
                    $visibility->whereIn('notices.audience', $audiences);

                    if ($studentIds !== []) {
                        $visibility->orWhere(function ($batchMatch) use ($studentIds): void {
                            $batchMatch->where('notices.audience', NoticeAudience::Batch->value)
                                ->whereExists(function ($sub) use ($studentIds): void {
                                    $sub->select(DB::raw(1))
                                        ->from('enrollments')
                                        ->whereColumn('enrollments.batch_id', 'notices.batch_id')
                                        ->whereIn('enrollments.student_id', $studentIds)
                                        ->where('enrollments.status', EnrollmentStatus::Active->value);
                                });
                        });
                    }
                });

            $totalCount = (int) ($base()
                ->selectRaw('COUNT(notices.id) as aggregate_count')
                ->value('aggregate_count') ?? 0);

            $rowsQuery = $base()
                ->leftJoin('batches', 'batches.id', '=', 'notices.batch_id');

            if ($hasPinned) {
                $rowsQuery->orderByDesc('notices.is_pinned');
            }

            $rows = $rowsQuery
                ->orderByDesc('notices.published_at')
                ->orderByDesc('notices.id')
                ->limit(5)
                ->select([
                    'notices.id',
                    'notices.title',
                    'notices.body',
                    'notices.published_at',
                    'batches.name as batch_name',
                    ...($hasPinned ? ['notices.is_pinned'] : []),
                ])
                ->get();

            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'id' => (int) $row->id,
                    'title' => (string) $row->title,
                    'body' => (string) $row->body,
                    'batch_name' => $row->batch_name !== null ? (string) $row->batch_name : null,
                    'published_at' => (string) $row->published_at,
                    'is_pinned' => $hasPinned && (bool) ($row->is_pinned ?? false),
                ];
            }

            return [
                'total_count' => $totalCount,
                'items' => $items,
            ];
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function remember(string $key, callable $callback): mixed
    {
        return Cache::remember('dashboard:'.$key, self::CACHE_SECONDS, $callback);
    }

    protected function clock(?CarbonInterface $now): Carbon
    {
        return $now ? Carbon::instance($now->toDateTime()) : now();
    }

    protected function yearMonthSql(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    protected function dayOfMonthSql(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%d', {$column}) AS INTEGER)"
            : "DAY({$column})";
    }

    protected function attendedStatusSqlList(): string
    {
        return implode(',', array_map(
            static fn (string $status): string => "'{$status}'",
            StudentAttendanceStatus::attendedValues(),
        ));
    }

    protected function upcomingWeekdayOffsetSql(string $dayColumn, string $endTimeColumn, CarbonInterface $now): string
    {
        $isoMap = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
        ];

        $todayIso = (int) $now->dayOfWeekIso;
        $currentTime = $now->format('H:i:s');
        $cases = [];

        foreach ($isoMap as $day => $iso) {
            $offset = ($iso - $todayIso + 7) % 7;

            if ($offset === 0) {
                $cases[] = "WHEN {$dayColumn} = '{$day}' AND TIME({$endTimeColumn}) < '{$currentTime}' THEN 7";
                $cases[] = "WHEN {$dayColumn} = '{$day}' THEN 0";
            } else {
                $cases[] = "WHEN {$dayColumn} = '{$day}' THEN {$offset}";
            }
        }

        return 'CASE '.implode(' ', $cases).' ELSE 8 END';
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    protected function normalizeIds(array $ids): array
    {
        $clean = array_values(array_unique(array_map('intval', $ids)));
        sort($clean);

        return array_values(array_filter($clean, static fn (int $id): bool => $id > 0));
    }

    /**
     * @param  list<int>  $ids
     */
    protected function idsHash(array $ids): string
    {
        return sha1(implode(',', $ids));
    }

    protected function formatTimeSlot(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (preg_match('/(\d{2}:\d{2})/', (string) $raw, $matches) === 1) {
            return $matches[1];
        }

        return (string) $raw;
    }
}
