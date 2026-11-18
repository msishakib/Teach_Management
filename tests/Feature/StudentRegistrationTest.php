<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Resources\StudentRegistrationResource\Pages\ListStudentRegistrations;
use App\Models\StudentRegistration;
use App\Models\User;
use App\Notifications\SendCredentialsNotification;
use App\Services\StudentRegistrationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nadia Rahman',
            'dob' => '2005-04-12',
            'gender' => 'female',
            'phone' => '01711223344',
            'email' => 'nadia@example.com',
            'national_id' => '1234567890',
            'birth_certificate_no' => 'BC-9988',
            'religion' => 'Islam',
            'blood_group' => 'O+',
            'present_address' => '12 Green Road, Dhaka',
            'permanent_address' => '45 Village Road, Cumilla',
            'last_institution' => 'Dhaka College',
            'last_exam' => 'HSC',
            'last_result' => 'GPA 4.83',
            'passing_year' => 2023,
            'hp_name' => '',
        ], $overrides);
    }

    public function test_homepage_and_navigation_expose_the_registration_link(): void
    {
        $response = $this->get(route('home', ['locale' => 'en']));

        $response->assertOk();
        $response->assertSee(route('register', ['locale' => 'en']), false);
        $response->assertSee(__('site.nav.register'));
    }

    public function test_registration_form_renders_in_both_locales(): void
    {
        $this->get(route('register', ['locale' => 'en']))
            ->assertOk()
            ->assertSee(__('site.register.title'));

        $this->get(route('register', ['locale' => 'bn']))
            ->assertOk()
            ->assertSee('শিক্ষার্থী হিসেবে রেজিস্টার করুন');
    }

    public function test_public_can_submit_a_registration_with_identity_address_and_academic_details(): void
    {
        $response = $this->post(route('register.store', ['locale' => 'en']), $this->payload());

        $registration = StudentRegistration::query()->firstOrFail();

        $response->assertRedirect(route('register.success', [
            'locale' => 'en',
            'registration' => $registration->registration_no,
        ]));

        $this->assertSame(StudentRegistration::STATUS_PENDING, $registration->status);
        $this->assertStringStartsWith('REG-', $registration->registration_no);

        $this->assertDatabaseHas('student_registrations', [
            'name' => 'Nadia Rahman',
            'phone' => '01711223344',
            'national_id' => '1234567890',
            'birth_certificate_no' => 'BC-9988',
            'religion' => 'Islam',
            'blood_group' => 'O+',
            'present_address' => '12 Green Road, Dhaka',
            'permanent_address' => '45 Village Road, Cumilla',
            'last_institution' => 'Dhaka College',
            'last_exam' => 'HSC',
            'last_result' => 'GPA 4.83',
            'passing_year' => 2023,
            'status' => StudentRegistration::STATUS_PENDING,
        ]);

        $this->get(route('register.success', [
            'locale' => 'en',
            'registration' => $registration->registration_no,
        ]))
            ->assertOk()
            ->assertSee($registration->registration_no)
            ->assertSee(__('site.register.statuses.pending'));
    }

    public function test_registration_requires_personal_and_address_fields(): void
    {
        $response = $this->from(route('register', ['locale' => 'en']))
            ->post(route('register.store', ['locale' => 'en']), [
                'hp_name' => '',
            ]);

        $response->assertSessionHasErrors([
            'name',
            'dob',
            'gender',
            'phone',
            'present_address',
        ]);

        $this->assertDatabaseCount('student_registrations', 0);
    }

    public function test_honeypot_blocks_spam_registrations(): void
    {
        $response = $this->from(route('register', ['locale' => 'en']))
            ->post(route('register.store', ['locale' => 'en']), $this->payload([
                'hp_name' => 'spam',
            ]));

        $response->assertSessionHasErrors('hp_name');
        $this->assertDatabaseCount('student_registrations', 0);
    }

    public function test_approval_creates_student_and_user_without_enrollment_or_invoice(): void
    {
        Notification::fake();

        $registration = StudentRegistration::query()->create($this->payload([
            'registration_no' => 'REG-2026-0001',
            'status' => StudentRegistration::STATUS_PENDING,
        ]));

        $admin = User::factory()->create();

        $student = app(StudentRegistrationService::class)->approve($registration, $admin);

        $this->assertSame('Nadia Rahman', $student->name);
        $this->assertSame('1234567890', $student->national_id);
        $this->assertSame('BC-9988', $student->birth_certificate_no);
        $this->assertSame('O+', $student->blood_group);
        $this->assertSame('12 Green Road, Dhaka', $student->present_address);
        $this->assertSame('45 Village Road, Cumilla', $student->permanent_address);
        $this->assertSame('Dhaka College', $student->last_institution);
        $this->assertSame('HSC', $student->last_exam);
        $this->assertSame('GPA 4.83', $student->last_result);
        $this->assertSame(2023, $student->passing_year);

        $this->assertNotNull($student->user);
        $this->assertSame('nadia@example.com', $student->user->email);
        $this->assertTrue($student->user->hasRole(RoleName::Student->value));

        $registration->refresh();
        $this->assertSame(StudentRegistration::STATUS_APPROVED, $registration->status);
        $this->assertSame($admin->id, $registration->reviewed_by);
        $this->assertNotNull($registration->reviewed_at);

        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('student_invoices', 0);

        Notification::assertSentTo($student->user, SendCredentialsNotification::class);
    }

    public function test_approval_without_email_creates_student_but_no_user_account(): void
    {
        Notification::fake();

        $registration = StudentRegistration::query()->create($this->payload([
            'registration_no' => 'REG-2026-0002',
            'email' => null,
            'status' => StudentRegistration::STATUS_PENDING,
        ]));

        $student = app(StudentRegistrationService::class)->approve($registration);

        $this->assertNull($student->user_id);
        $this->assertSame(StudentRegistration::STATUS_APPROVED, $registration->fresh()->status);
        $this->assertDatabaseCount('users', 0);

        Notification::assertNothingSent();
    }

    public function test_approval_is_idempotent_and_rejects_duplicate_emails(): void
    {
        Notification::fake();

        $registration = StudentRegistration::query()->create($this->payload([
            'registration_no' => 'REG-2026-0003',
            'status' => StudentRegistration::STATUS_PENDING,
        ]));

        $service = app(StudentRegistrationService::class);
        $service->approve($registration);

        try {
            $service->approve($registration->fresh());
            $this->fail('Approving twice should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('registration', $exception->errors());
        }

        $second = StudentRegistration::query()->create($this->payload([
            'registration_no' => 'REG-2026-0004',
            'status' => StudentRegistration::STATUS_PENDING,
        ]));

        try {
            $service->approve($second);
            $this->fail('A duplicate email should not create another user.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }

        $this->assertSame(StudentRegistration::STATUS_PENDING, $second->fresh()->status);
        $this->assertSame(1, User::query()->where('email', 'nadia@example.com')->count());
    }

    public function test_rejection_records_reason_and_reviewer(): void
    {
        $registration = StudentRegistration::query()->create($this->payload([
            'registration_no' => 'REG-2026-0005',
            'status' => StudentRegistration::STATUS_PENDING,
        ]));

        $admin = User::factory()->create();

        app(StudentRegistrationService::class)->reject($registration, 'Incomplete documents', $admin);

        $registration->refresh();

        $this->assertSame(StudentRegistration::STATUS_REJECTED, $registration->status);
        $this->assertSame('Incomplete documents', $registration->remarks);
        $this->assertSame($admin->id, $registration->reviewed_by);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_admin_can_approve_a_registration_from_the_filament_list(): void
    {
        Notification::fake();

        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $registration = StudentRegistration::query()->create($this->payload([
            'registration_no' => 'REG-2026-0006',
            'status' => StudentRegistration::STATUS_PENDING,
        ]));

        Livewire::actingAs($admin)
            ->test(ListStudentRegistrations::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$registration])
            ->callTableAction('approve', $registration)
            ->assertHasNoTableActionErrors();

        $this->assertSame(StudentRegistration::STATUS_APPROVED, $registration->fresh()->status);
        $this->assertDatabaseHas('students', ['name' => 'Nadia Rahman']);
    }

    public function test_admin_can_reject_a_registration_from_the_filament_list(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole(RoleName::Admin->value);

        $registration = StudentRegistration::query()->create($this->payload([
            'registration_no' => 'REG-2026-0007',
            'status' => StudentRegistration::STATUS_PENDING,
        ]));

        Livewire::actingAs($admin)
            ->test(ListStudentRegistrations::class)
            ->callTableAction('reject', $registration, data: ['reason' => 'Missing certificate'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(StudentRegistration::STATUS_REJECTED, $registration->fresh()->status);
        $this->assertSame('Missing certificate', $registration->fresh()->remarks);
    }
}
