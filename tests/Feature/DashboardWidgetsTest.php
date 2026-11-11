<?php

namespace Tests\Feature;

use App\Enums\AttendanceSessionStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ExamResultStatus;
use App\Enums\ExamStatus;
use App\Enums\NoticeAudience;
use App\Enums\RoleName;
use App\Enums\StudentAttendanceStatus;
use App\Enums\StudentInvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Enums\VoucherType;
use App\Enums\WeekDay;
use App\Filament\Student\Widgets\StudentAttendanceStatsWidget;
use App\Filament\Student\Widgets\StudentLatestResultWidget;
use App\Filament\Student\Widgets\StudentNextClassWidget;
use App\Filament\Student\Widgets\StudentNoticesWidget;
use App\Filament\Teacher\Widgets\PendingAttendanceWidget;
use App\Filament\Teacher\Widgets\PendingMarksEntryWidget;
use App\Filament\Teacher\Widgets\TodaysClassesWidget;
use App\Filament\Widgets\AdmissionsStatsWidget;
use App\Filament\Widgets\FinanceStatsWidget;
use App\Filament\Widgets\MonthlyCollectionComparisonChart;
use App\Filament\Widgets\RecentNoticesWidget;
use App\Filament\Widgets\StudentsByCourseChart;
use App\Filament\Widgets\UpcomingExamsWidget;
use App\Models\Account;
use App\Models\AdmissionApplication;
use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\ClassRoutine;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamMark;
use App\Models\ExamResult;
use App\Models\ExamSubject;
use App\Models\Guardian;
use App\Models\Notice;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentInvoice;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\DashboardMetricsService;
use App\Support\LedgerAccounts;
use Carbon\Carbon;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected DashboardMetricsService $metrics;

    protected AccountingService $accounting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);

        Cache::flush();

        $this->metrics = app(DashboardMetricsService::class);
        $this->accounting = app(AccountingService::class);
    }

    public function test_widget_visibility_respects_roles_and_granular_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $accountant = User::factory()->accountant()->create();
        $accountant->assignRole(RoleName::Accountant->value);

        $teacherUser = User::factory()->teacher()->create(['type' => UserType::Teacher]);
        $teacherUser->assignRole(RoleName::Teacher->value);
        Teacher::factory()->create(['user_id' => $teacherUser->id]);

        $studentUser = User::factory()->create(['type' => UserType::Student]);
        $studentUser->assignRole(RoleName::Student->value);
        Student::factory()->create(['user_id' => $studentUser->id]);

        $guardianUser = User::factory()->create(['type' => UserType::Guardian]);
        $guardianUser->assignRole(RoleName::Guardian->value);
        Guardian::factory()->create(['user_id' => $guardianUser->id]);

        // Admin sees all Admin/Accountant + Admin-only widgets.
        $this->actingAs($admin);
        $this->assertTrue(FinanceStatsWidget::canView());
        $this->assertTrue(AdmissionsStatsWidget::canView());
        $this->assertTrue(MonthlyCollectionComparisonChart::canView());
        $this->assertTrue(StudentsByCourseChart::canView());
        $this->assertTrue(UpcomingExamsWidget::canView());
        $this->assertTrue(RecentNoticesWidget::canView());
        $this->assertFalse(TodaysClassesWidget::canView());
        $this->assertFalse(StudentAttendanceStatsWidget::canView());

        // Accountant sees finance + admissions widgets, NOT admin-only academic/notice widgets.
        $this->actingAs($accountant);
        $this->assertTrue(FinanceStatsWidget::canView());
        $this->assertTrue(AdmissionsStatsWidget::canView());
        $this->assertTrue(MonthlyCollectionComparisonChart::canView());
        $this->assertFalse(StudentsByCourseChart::canView());
        $this->assertFalse(UpcomingExamsWidget::canView());
        $this->assertFalse(RecentNoticesWidget::canView());

        Livewire::actingAs($accountant)
            ->test(AdmissionsStatsWidget::class)
            ->assertSee('New admissions')
            ->assertSee('Pending applications')
            ->assertDontSee("Today's attendance");

        // Custom restricted role with ONLY `fee.view` sees only fee-permitted widgets/stats.
        $feeClerkRole = Role::create(['name' => 'fee_clerk', 'guard_name' => 'web']);
        $feeClerkRole->givePermissionTo(['fee.view']);
        $feeClerk = User::factory()->create();
        $feeClerk->assignRole('fee_clerk');

        $this->actingAs($feeClerk);
        $this->assertTrue(FinanceStatsWidget::canView());
        $this->assertTrue(MonthlyCollectionComparisonChart::canView());
        $this->assertFalse(AdmissionsStatsWidget::canView());

        Livewire::actingAs($feeClerk)
            ->test(FinanceStatsWidget::class)
            ->assertSee("Today's collection")
            ->assertSee('Total due')
            ->assertDontSee('Expense this month')
            ->assertDontSee('Cash / bank');

        // Teacher sees teacher widgets, and revoking exam permissions hides PendingMarksEntryWidget.
        $this->actingAs($teacherUser);
        $this->assertTrue(TodaysClassesWidget::canView());
        $this->assertTrue(PendingAttendanceWidget::canView());
        $this->assertTrue(PendingMarksEntryWidget::canView());
        $this->assertFalse(FinanceStatsWidget::canView());

        $teacherRole = Role::findByName(RoleName::Teacher->value, 'web');
        $teacherRole->revokePermissionTo(['exam.view', 'exam.create', 'exam.update']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $teacherUser->unsetRelation('roles')->unsetRelation('permissions');
        $this->assertFalse(PendingMarksEntryWidget::canView());

        // Student & guardian see learner widgets; revoking `fee.view` hides Due fees inside the combined stats widget.
        $this->actingAs($studentUser);
        $this->assertTrue(StudentAttendanceStatsWidget::canView());
        $this->assertTrue(StudentNextClassWidget::canView());
        $this->assertTrue(StudentLatestResultWidget::canView());
        $this->assertTrue(StudentNoticesWidget::canView());

        $this->actingAs($guardianUser);
        $this->assertTrue(StudentAttendanceStatsWidget::canView());
        $this->assertTrue(StudentNextClassWidget::canView());
        $this->assertTrue(StudentLatestResultWidget::canView());
        $this->assertTrue(StudentNoticesWidget::canView());

        $studentRole = Role::findByName(RoleName::Student->value, 'web');
        $studentRole->revokePermissionTo('fee.view');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $studentUser->unsetRelation('roles')->unsetRelation('permissions');

        $this->actingAs($studentUser);
        $this->assertTrue(StudentAttendanceStatsWidget::canView());
        Livewire::actingAs($studentUser)
            ->test(StudentAttendanceStatsWidget::class)
            ->assertSee('Attendance')
            ->assertDontSee('Due fees');
    }

    public function test_admin_and_accountant_financial_and_academic_aggregates_and_caching(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 10:30:00'));

        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        Account::query()->where('code', LedgerAccounts::CASH)->update(['opening_balance' => 5000]);
        Account::query()->where('code', LedgerAccounts::BANK)->update(['opening_balance' => 12000]);

        $cash = LedgerAccounts::byCode(LedgerAccounts::CASH);
        $bank = LedgerAccounts::byCode(LedgerAccounts::BANK);
        $income = LedgerAccounts::byCode(LedgerAccounts::TUITION_FEE_INCOME);
        $rent = LedgerAccounts::byCode(LedgerAccounts::RENT);

        // Today's receipt: 1,500 into Cash.
        $todayReceipt = $this->accounting->createEntry([
            'entry_date' => '2026-11-11',
            'voucher_type' => VoucherType::Receipt,
            'narration' => 'Today tuition receipt',
            'created_by' => $admin,
        ], [
            ['account_id' => $cash->id, 'debit' => 1500, 'credit' => 0],
            ['account_id' => $income->id, 'debit' => 0, 'credit' => 1500],
        ]);
        $this->accounting->post($todayReceipt);

        // Earlier this month receipt: 2,500 into Bank.
        $thisMonthReceipt = $this->accounting->createEntry([
            'entry_date' => '2026-11-03',
            'voucher_type' => VoucherType::Receipt,
            'narration' => 'Early Nov receipt',
            'created_by' => $admin,
        ], [
            ['account_id' => $bank->id, 'debit' => 2500, 'credit' => 0],
            ['account_id' => $income->id, 'debit' => 0, 'credit' => 2500],
        ]);
        $this->accounting->post($thisMonthReceipt);

        // Last month receipt: 3,200 into Cash.
        $lastMonthReceipt = $this->accounting->createEntry([
            'entry_date' => '2026-10-15',
            'voucher_type' => VoucherType::Receipt,
            'narration' => 'Oct receipt',
            'created_by' => $admin,
        ], [
            ['account_id' => $cash->id, 'debit' => 3200, 'credit' => 0],
            ['account_id' => $income->id, 'debit' => 0, 'credit' => 3200],
        ]);
        $this->accounting->post($lastMonthReceipt);

        // Expense this month: 800 Rent paid from Cash.
        $expenseEntry = $this->accounting->createEntry([
            'entry_date' => '2026-11-08',
            'voucher_type' => VoucherType::Payment,
            'narration' => 'Office rent',
            'created_by' => $admin,
        ], [
            ['account_id' => $rent->id, 'debit' => 800, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => 800],
        ]);
        $this->accounting->post($expenseEntry);

        // Courses, batches, students, invoices, applications, attendance, exams, notices.
        $courseA = Course::factory()->create(['name' => 'HSC Physics', 'is_active' => true]);
        $courseB = Course::factory()->create(['name' => 'SSC Math', 'is_active' => true]);
        $batchA = Batch::factory()->create(['course_id' => $courseA->id]);
        $batchB = Batch::factory()->create(['course_id' => $courseB->id]);

        $student1 = Student::factory()->create(['status' => StudentStatus::Active, 'created_at' => now()]);
        $student2 = Student::factory()->create(['status' => StudentStatus::Active, 'created_at' => now()->subMonth()]);

        Enrollment::factory()->create(['student_id' => $student1->id, 'batch_id' => $batchA->id, 'status' => EnrollmentStatus::Active]);
        Enrollment::factory()->create(['student_id' => $student2->id, 'batch_id' => $batchA->id, 'status' => EnrollmentStatus::Active]);
        Enrollment::factory()->create(['student_id' => $student1->id, 'batch_id' => $batchB->id, 'status' => EnrollmentStatus::Active]);

        StudentInvoice::withoutGlobalScopes()->create([
            'student_id' => $student1->id,
            'batch_id' => $batchA->id,
            'invoice_no' => 'INV-DASH-1',
            'month' => '2026-11',
            'due_date' => '2026-11-05',
            'total' => 2000,
            'discount' => 0,
            'paid' => 500,
            'due' => 1500,
            'status' => StudentInvoiceStatus::Partial,
        ]);

        AdmissionApplication::query()->create([
            'application_no' => 'APP-DASH-1',
            'name' => 'Applicant One',
            'phone' => '01700000001',
            'course_id' => $courseA->id,
            'status' => 'pending',
        ]);

        $teacher = Teacher::factory()->create();
        $session = AttendanceSession::factory()->create([
            'batch_id' => $batchA->id,
            'teacher_id' => $teacher->id,
            'taken_by' => $admin->id,
            'date' => '2026-11-11',
            'status' => AttendanceSessionStatus::Finalized,
        ]);
        StudentAttendance::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student1->id,
            'status' => StudentAttendanceStatus::Present,
        ]);
        StudentAttendance::query()->create([
            'attendance_session_id' => $session->id,
            'student_id' => $student2->id,
            'status' => StudentAttendanceStatus::Absent,
        ]);

        Exam::factory()->create([
            'batch_id' => $batchA->id,
            'name' => 'Model Test 1',
            'start_date' => '2026-11-15',
            'end_date' => '2026-11-20',
            'status' => ExamStatus::Scheduled,
        ]);

        Notice::query()->create([
            'title' => 'Campus Notice',
            'body' => 'Classes start at 9 AM.',
            'audience' => NoticeAudience::All,
            'published_at' => now()->subHour(),
            'is_active' => true,
        ]);

        // Assert SQL aggregate results.
        $todayCol = $this->metrics->todaysCollection();
        $this->assertSame('1500.00', $todayCol['today_total']);
        $this->assertSame(1, $todayCol['today_receipts']);

        $monthly = $this->metrics->monthlyCollectionComparison();
        $this->assertSame('4000.00', $monthly['this_month_total']);
        $this->assertSame('3200.00', $monthly['last_month_total']);
        $this->assertSame(1500.0, $monthly['this_month'][10]); // Day 11 (0-indexed 10)
        $this->assertSame(2500.0, $monthly['this_month'][2]);  // Day 3 (0-indexed 2)

        $due = $this->metrics->totalDueSummary();
        $this->assertSame('1500.00', $due['total_due']);
        $this->assertSame(1, $due['students_with_due']);

        $expense = $this->metrics->expenseThisMonth();
        $this->assertSame('800.00', $expense['this_month_expense']);

        // Cash = 5000 + 1500 + 3200 - 800 = 8900; Bank = 12000 + 2500 = 14500; Total = 23400.
        $cashBank = $this->metrics->cashBankBalance();
        $this->assertSame('8900.00', $cashBank['cash_balance']);
        $this->assertSame('14500.00', $cashBank['bank_balance']);
        $this->assertSame('23400.00', $cashBank['total_balance']);

        $admissions = $this->metrics->admissionsAndApplications();
        $this->assertSame(1, $admissions['new_admissions_this_month']);
        $this->assertSame(1, $admissions['new_admissions_last_month']);
        $this->assertSame(1, $admissions['pending_applications']);

        $byCourse = $this->metrics->studentsByCourse();
        $this->assertSame(['HSC Physics', 'SSC Math'], $byCourse['labels']);
        $this->assertSame([2, 1], $byCourse['counts']);

        $att = $this->metrics->todaysAttendanceSummary();
        $this->assertSame(50.0, $att['percentage']);
        $this->assertSame(1, $att['attended_count']);
        $this->assertSame(1, $att['absent_count']);

        $exams = $this->metrics->upcomingExamsSummary();
        $this->assertSame(1, $exams['upcoming_count']);
        $this->assertSame('Model Test 1', $exams['items'][0]['name']);

        $notices = $this->metrics->recentNoticesSummary();
        $this->assertSame(1, $notices['active_count']);

        // Verify 5-minute caching: another receipt posted now does not change cached value until TTL expires.
        $extraReceipt = $this->accounting->createEntry([
            'entry_date' => '2026-11-11',
            'voucher_type' => VoucherType::Receipt,
            'narration' => 'Second receipt',
            'created_by' => $admin,
        ], [
            ['account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
            ['account_id' => $income->id, 'debit' => 0, 'credit' => 500],
        ]);
        $this->accounting->post($extraReceipt);

        $this->assertSame('1500.00', $this->metrics->todaysCollection()['today_total']);

        $this->travel(301)->seconds();
        $this->assertSame('2000.00', $this->metrics->todaysCollection()['today_total']);

        // Livewire smoke check on Admin widgets (includes +500 receipt after 5-min cache expiry = 23,900.00).
        Livewire::actingAs($admin)->test(FinanceStatsWidget::class)->assertSee('23,900.00');
        Livewire::actingAs($admin)
            ->test(AdmissionsStatsWidget::class)
            ->assertSee('New admissions')
            ->assertSee('Pending applications')
            ->assertSee('50.0%');
        Livewire::actingAs($admin)->test(MonthlyCollectionComparisonChart::class)->assertSee('Collection: this vs last month');
        Livewire::actingAs($admin)->test(StudentsByCourseChart::class)->assertSee('Students by course');
        Livewire::actingAs($admin)->test(UpcomingExamsWidget::class)->assertSee('Model Test 1');
        Livewire::actingAs($admin)->test(RecentNoticesWidget::class)->assertSee('Campus Notice');
    }

    public function test_teacher_and_student_guardian_widgets_aggregate_correctly(): void
    {
        // Wednesday 10:00 AM
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00'));

        $teacherUser = User::factory()->teacher()->create(['type' => UserType::Teacher]);
        $teacherUser->assignRole(RoleName::Teacher->value);
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id, 'name' => 'Mr. Karim']);

        $studentUser = User::factory()->create(['type' => UserType::Student]);
        $studentUser->assignRole(RoleName::Student->value);
        $student = Student::factory()->create(['user_id' => $studentUser->id, 'status' => StudentStatus::Active]);

        $guardianUser = User::factory()->create(['type' => UserType::Guardian]);
        $guardianUser->assignRole(RoleName::Guardian->value);
        $guardian = Guardian::factory()->create(['user_id' => $guardianUser->id]);
        $guardian->students()->attach($student->id, ['relation' => 'father']);

        $batch = Batch::factory()->create(['name' => 'HSC-A']);
        $otherBatch = Batch::factory()->create(['name' => 'HSC-B']);
        $subject = Subject::factory()->create(['name' => 'Physics']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'status' => EnrollmentStatus::Active,
        ]);

        // Class scheduled today (Wednesday) 11:00 - 12:00.
        ClassRoutine::factory()->create([
            'batch_id' => $batch->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'day' => WeekDay::Wednesday,
            'start_time' => '11:00:00',
            'end_time' => '12:00:00',
        ]);

        // Exam in marks_entry with 1 enrolled student and 0 marks entered yet.
        $exam = Exam::factory()->create([
            'batch_id' => $batch->id,
            'name' => 'Quiz 1',
            'status' => ExamStatus::MarksEntry,
        ]);
        $examSubject = ExamSubject::factory()->create([
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        // Teacher metrics: 1 class today, 1 pending attendance, 1 pending marks subject (1 mark remaining).
        $classes = $this->metrics->teacherTodaysClasses($teacher->id);
        $this->assertSame(1, $classes['classes_count']);
        $this->assertSame('11:00', $classes['items'][0]['start_time']);
        $this->assertSame(1, $classes['items'][0]['student_count']);

        $pendingAtt = $this->metrics->teacherPendingAttendance($teacher->id);
        $this->assertSame(1, $pendingAtt['pending_today_count']);

        $pendingMarks = $this->metrics->teacherPendingMarksEntry($teacher->id);
        $this->assertSame(1, $pendingMarks['pending_subjects_count']);
        $this->assertSame(1, $pendingMarks['pending_marks_count']);

        // Enter mark & flush cache -> pending marks drops to 0.
        ExamMark::query()->create([
            'exam_subject_id' => $examSubject->id,
            'student_id' => $student->id,
            'marks_obtained' => 85,
            'is_absent' => false,
            'entered_by' => $teacherUser->id,
        ]);
        Cache::flush();
        $this->assertSame(0, $this->metrics->teacherPendingMarksEntry($teacher->id)['pending_subjects_count']);

        // Student/guardian attendance, due fees, next class, latest result, and portal-scoped notices.
        $pastSession = AttendanceSession::factory()->create([
            'batch_id' => $batch->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'taken_by' => $teacherUser->id,
            'date' => '2026-11-10',
            'status' => AttendanceSessionStatus::Finalized,
        ]);
        StudentAttendance::query()->create([
            'attendance_session_id' => $pastSession->id,
            'student_id' => $student->id,
            'status' => StudentAttendanceStatus::Present,
        ]);

        StudentInvoice::withoutGlobalScopes()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'invoice_no' => 'INV-STU-1',
            'month' => '2026-11',
            'due_date' => '2026-11-20',
            'total' => 1200,
            'discount' => 0,
            'paid' => 200,
            'due' => 1000,
            'status' => StudentInvoiceStatus::Partial,
        ]);

        $publishedExam = Exam::factory()->create([
            'batch_id' => $batch->id,
            'name' => 'Mid Term Exam',
            'status' => ExamStatus::Published,
        ]);
        ExamResult::withoutGlobalScopes()->create([
            'exam_id' => $publishedExam->id,
            'student_id' => $student->id,
            'total_marks' => 88,
            'full_marks' => 100,
            'percentage' => 88.0,
            'gpa' => 5.00,
            'letter_grade' => 'A+',
            'status' => ExamResultStatus::Pass,
            'rank_in_batch' => 1,
            'published_at' => now()->subHour(),
        ]);

        Notice::query()->create([
            'title' => 'Guardian Meeting',
            'body' => 'Guardians only.',
            'audience' => NoticeAudience::Guardians,
            'published_at' => now()->subHour(),
            'is_active' => true,
        ]);
        Notice::query()->create([
            'title' => 'HSC-A Lab Notice',
            'body' => 'Bring lab coat.',
            'audience' => NoticeAudience::Batch,
            'batch_id' => $batch->id,
            'published_at' => now()->subMinutes(30),
            'is_active' => true,
        ]);
        Notice::query()->create([
            'title' => 'Other Batch Notice',
            'body' => 'Not for HSC-A.',
            'audience' => NoticeAudience::Batch,
            'batch_id' => $otherBatch->id,
            'published_at' => now()->subMinutes(15),
            'is_active' => true,
        ]);

        $studentAtt = $this->metrics->studentAttendanceSummary([$student->id]);
        $this->assertSame(100.0, $studentAtt['percentage']);

        $nextClass = $this->metrics->studentNextClass([$student->id]);
        $this->assertTrue($nextClass['next_class']['is_today']);
        $this->assertSame('Physics', $nextClass['next_class']['subject_name']);
        $this->assertSame('11:00', $nextClass['next_class']['start_time']);

        $dueFees = $this->metrics->studentDueFees([$student->id]);
        $this->assertSame('1000.00', $dueFees['total_due']);

        $latestRes = $this->metrics->studentLatestResult([$student->id]);
        $this->assertSame('Mid Term Exam', $latestRes['latest']['exam_name']);
        $this->assertSame('5.00', $latestRes['latest']['gpa']);

        // Student sees HSC-A batch notice (1), NOT guardian-only or HSC-B notice.
        $studentNotices = $this->metrics->studentNotices($studentUser);
        $this->assertSame(1, $studentNotices['total_count']);
        $this->assertSame('HSC-A Lab Notice', $studentNotices['items'][0]['title']);

        // Guardian sees BOTH guardian-only notice AND ward's HSC-A batch notice (2), NOT HSC-B notice.
        $guardianNotices = $this->metrics->studentNotices($guardianUser);
        $this->assertSame(2, $guardianNotices['total_count']);

        // Livewire render checks for Teacher & Student/Guardian widgets.
        Livewire::actingAs($teacherUser)->test(TodaysClassesWidget::class)->assertSee('Physics');
        Livewire::actingAs($teacherUser)->test(PendingAttendanceWidget::class)->assertSee('HSC-A');
        Livewire::actingAs($teacherUser)->test(PendingMarksEntryWidget::class)->assertSee('Pending marks');
        Livewire::actingAs($studentUser)
            ->test(StudentAttendanceStatsWidget::class)
            ->assertSee('100.0%')
            ->assertSee('1,000.00');
        Livewire::actingAs($studentUser)->test(StudentNextClassWidget::class)->assertSee('Physics');
        Livewire::actingAs($studentUser)->test(StudentLatestResultWidget::class)->assertSee('Mid Term Exam');
        Livewire::actingAs($guardianUser)->test(StudentNoticesWidget::class)->assertSee('Guardian Meeting');
    }
}
