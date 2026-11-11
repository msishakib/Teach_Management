<?php

namespace Tests\Feature;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamResultStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReceiptStatus;
use App\Enums\RoleName;
use App\Enums\StudentInvoiceStatus;
use App\Enums\StudentStatus;
use App\Enums\TeacherSalaryStatus;
use App\Enums\UserType;
use App\Models\Account;
use App\Models\Batch;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Guardian;
use App\Models\Receipt;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Models\User;
use App\Support\PortalAccess;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PortalIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            ChartOfAccountsSeeder::class,
        ]);
    }

    public function test_student_cannot_query_another_students_invoices_or_receipts(): void
    {
        [$studentA, $userA] = $this->makeStudentUser();
        [$studentB] = $this->makeStudentUser();

        $batchA = Batch::factory()->create();
        $batchB = Batch::factory()->create();
        $account = Account::query()->firstOrFail();

        StudentInvoice::withoutGlobalScopes()->create([
            'student_id' => $studentA->id,
            'batch_id' => $batchA->id,
            'invoice_no' => 'INV-A-1',
            'month' => '2026-01',
            'due_date' => now()->toDateString(),
            'total' => 100,
            'discount' => 0,
            'paid' => 0,
            'due' => 100,
            'status' => StudentInvoiceStatus::Unpaid,
        ]);

        StudentInvoice::withoutGlobalScopes()->create([
            'student_id' => $studentB->id,
            'batch_id' => $batchB->id,
            'invoice_no' => 'INV-B-1',
            'month' => '2026-01',
            'due_date' => now()->toDateString(),
            'total' => 200,
            'discount' => 0,
            'paid' => 0,
            'due' => 200,
            'status' => StudentInvoiceStatus::Unpaid,
        ]);

        Receipt::withoutGlobalScopes()->create([
            'receipt_no' => 'RCPT-A-1',
            'student_id' => $studentA->id,
            'date' => now()->toDateString(),
            'method' => PaymentMethod::Cash,
            'account_id' => $account->id,
            'amount' => 50,
            'status' => ReceiptStatus::Posted,
        ]);

        Receipt::withoutGlobalScopes()->create([
            'receipt_no' => 'RCPT-B-1',
            'student_id' => $studentB->id,
            'date' => now()->toDateString(),
            'method' => PaymentMethod::Cash,
            'account_id' => $account->id,
            'amount' => 75,
            'status' => ReceiptStatus::Posted,
        ]);

        Auth::login($userA);

        $this->assertSame(['INV-A-1'], StudentInvoice::query()->pluck('invoice_no')->all());
        $this->assertSame(['RCPT-A-1'], Receipt::query()->pluck('receipt_no')->all());
        $this->assertFalse(StudentInvoice::query()->where('invoice_no', 'INV-B-1')->exists());
    }

    public function test_guardian_only_sees_ward_enrollments_and_invoices(): void
    {
        [$ward] = $this->makeStudentUser();
        [$other] = $this->makeStudentUser();

        $guardianUser = User::factory()->create(['type' => UserType::Guardian]);
        $guardianUser->assignRole(RoleName::Guardian->value);
        $guardian = Guardian::factory()->create([
            'user_id' => $guardianUser->id,
            'name' => $guardianUser->name,
        ]);
        $guardian->students()->attach($ward->id, ['relation' => 'father']);

        $batch = Batch::factory()->create();
        Enrollment::withoutGlobalScopes()->create([
            'student_id' => $ward->id,
            'batch_id' => $batch->id,
            'enrolled_on' => now()->toDateString(),
            'status' => EnrollmentStatus::Active,
        ]);
        Enrollment::withoutGlobalScopes()->create([
            'student_id' => $other->id,
            'batch_id' => $batch->id,
            'enrolled_on' => now()->toDateString(),
            'status' => EnrollmentStatus::Active,
        ]);

        StudentInvoice::withoutGlobalScopes()->create([
            'student_id' => $ward->id,
            'batch_id' => $batch->id,
            'invoice_no' => 'INV-WARD',
            'month' => '2026-02',
            'due_date' => now()->toDateString(),
            'total' => 100,
            'discount' => 0,
            'paid' => 0,
            'due' => 100,
            'status' => StudentInvoiceStatus::Unpaid,
        ]);
        StudentInvoice::withoutGlobalScopes()->create([
            'student_id' => $other->id,
            'batch_id' => $batch->id,
            'invoice_no' => 'INV-OTHER',
            'month' => '2026-02',
            'due_date' => now()->toDateString(),
            'total' => 100,
            'discount' => 0,
            'paid' => 0,
            'due' => 100,
            'status' => StudentInvoiceStatus::Unpaid,
        ]);

        Auth::login($guardianUser);

        Filament::setCurrentPanel(Filament::getPanel('student'));
        $this->assertTrue($guardianUser->canAccessPanel(Filament::getPanel('student')));
        $this->assertSame([$ward->id], PortalAccess::accessibleStudentIds($guardianUser));
        $this->assertSame([$ward->id], Enrollment::query()->pluck('student_id')->all());
        $this->assertSame(['INV-WARD'], StudentInvoice::query()->pluck('invoice_no')->all());
        $this->assertFalse(Enrollment::query()->where('student_id', $other->id)->exists());
    }

    public function test_teacher_only_sees_own_salary_slips(): void
    {
        $teacherUser = User::factory()->teacher()->create();
        $teacherUser->assignRole(RoleName::Teacher->value);
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);
        $otherTeacher = Teacher::factory()->create();

        TeacherSalary::withoutGlobalScopes()->create([
            'teacher_id' => $teacher->id,
            'month' => '2026-01',
            'amount' => 1000,
            'bonus' => 0,
            'deductions' => 0,
            'net' => 1000,
            'status' => TeacherSalaryStatus::Generated,
        ]);
        TeacherSalary::withoutGlobalScopes()->create([
            'teacher_id' => $otherTeacher->id,
            'month' => '2026-01',
            'amount' => 2000,
            'bonus' => 0,
            'deductions' => 0,
            'net' => 2000,
            'status' => TeacherSalaryStatus::Generated,
        ]);

        Auth::login($teacherUser);

        $this->assertSame(1, TeacherSalary::query()->count());
        $this->assertSame($teacher->id, TeacherSalary::query()->first()->teacher_id);
    }

    public function test_teacher_cannot_open_unassigned_batch_students_page(): void
    {
        $teacherUser = User::factory()->teacher()->create();
        $teacherUser->assignRole(RoleName::Teacher->value);
        $teacher = Teacher::factory()->create(['user_id' => $teacherUser->id]);

        $assigned = Batch::factory()->create();
        $foreign = Batch::factory()->create();

        ClassRoutine::factory()->create([
            'batch_id' => $assigned->id,
            'teacher_id' => $teacher->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('teacher'));

        $this->actingAs($teacherUser)
            ->get('/teacher/batch-students?batch='.$foreign->id)
            ->assertForbidden();
    }

    public function test_student_receipt_pdf_forbidden_for_peer(): void
    {
        [$studentA, $userA] = $this->makeStudentUser();
        [$studentB] = $this->makeStudentUser();
        $account = Account::query()->firstOrFail();

        $peerReceipt = Receipt::withoutGlobalScopes()->create([
            'receipt_no' => 'RCPT-PEER',
            'student_id' => $studentB->id,
            'date' => now()->toDateString(),
            'method' => PaymentMethod::Cash,
            'account_id' => $account->id,
            'amount' => 40,
            'status' => ReceiptStatus::Posted,
        ]);

        $this->actingAs($userA)
            ->get(route('receipts.pdf', $peerReceipt))
            ->assertNotFound();
    }

    public function test_published_exam_result_isolation_still_holds_for_students(): void
    {
        [$studentA, $userA] = $this->makeStudentUser();
        [$studentB] = $this->makeStudentUser();

        ExamResult::withoutGlobalScopes()->create([
            'exam_id' => Exam::factory()->create()->id,
            'student_id' => $studentA->id,
            'total_marks' => 100,
            'full_marks' => 100,
            'percentage' => 100,
            'gpa' => 5,
            'letter_grade' => 'A+',
            'status' => ExamResultStatus::Pass,
            'published_at' => now(),
        ]);
        ExamResult::withoutGlobalScopes()->create([
            'exam_id' => Exam::factory()->create()->id,
            'student_id' => $studentB->id,
            'total_marks' => 90,
            'full_marks' => 100,
            'percentage' => 90,
            'gpa' => 5,
            'letter_grade' => 'A+',
            'status' => ExamResultStatus::Pass,
            'published_at' => now(),
        ]);

        Auth::login($userA);

        $this->assertSame([$studentA->id], ExamResult::query()->pluck('student_id')->all());
    }

    /**
     * @return array{0: Student, 1: User}
     */
    protected function makeStudentUser(): array
    {
        $user = User::factory()->student()->create();
        $user->assignRole(RoleName::Student->value);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'status' => StudentStatus::Active,
        ]);

        return [$student, $user];
    }
}
