<?php

namespace Tests\Feature;

use App\Models\ExamMark;
use App\Models\PaymentVoucher;
use App\Models\Receipt;
use App\Models\ReceiveVoucher;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(SettingsSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_student_changes_record_old_new_values_causer_and_request_context(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Audit Admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin);
        request()->server->set('REMOTE_ADDR', '203.0.113.9');
        request()->headers->set('USER_AGENT', 'PHPUnit Audit Agent');
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Audit Agent';

        $student = Student::factory()->create(['name' => 'Original Name']);

        $student->update(['name' => 'Updated Name']);

        $activity = Activity::query()
            ->where('subject_type', Student::class)
            ->where('subject_id', $student->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame($admin->id, $activity->causer_id);
        $this->assertSame(User::class, $activity->causer_type);
        $this->assertSame('203.0.113.9', $activity->getExtraProperty('ip_address'));
        $this->assertSame('PHPUnit Audit Agent', $activity->getExtraProperty('user_agent'));

        $properties = $activity->properties->toArray();
        $this->assertSame('Original Name', data_get($properties, 'old.name'));
        $this->assertSame('Updated Name', data_get($properties, 'attributes.name'));
    }

    public function test_activity_log_page_renders_with_user_filter(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole('admin');

        Student::factory()->create();

        $this->actingAs($admin)
            ->get(\App\Filament\Resources\ActivityResource::getUrl('index'))
            ->assertOk();
    }

    public function test_all_audited_models_write_activity_entries(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7']);

        $models = [
            [Student::class, Student::factory()->create()],
            [Teacher::class, Teacher::factory()->create()],
            [StudentInvoice::class, StudentInvoice::factory()->create()],
            [Receipt::class, Receipt::factory()->create()],
            [PaymentVoucher::class, PaymentVoucher::factory()->create()],
            [ReceiveVoucher::class, ReceiveVoucher::factory()->create()],
            [ExamMark::class, ExamMark::factory()->create()],
            [Setting::class, Setting::query()->create([
                'group' => 'security',
                'key' => 'audit_probe',
                'value' => 'before',
                'type' => 'string',
            ])],
        ];

        foreach ($models as [$class, $model]) {
            $payload = match ($class) {
                Student::class, Teacher::class => ['name' => $model->name.' Updated'],
                StudentInvoice::class => ['due' => '99.00'],
                Receipt::class => ['notes' => 'Updated receipt notes'],
                PaymentVoucher::class, ReceiveVoucher::class => ['narration' => 'Updated narration'],
                ExamMark::class => ['remarks' => 'Updated remark'],
                Setting::class => ['value' => 'after'],
                default => [],
            };

            $model->update($payload);

            $this->assertTrue(
                Activity::query()
                    ->where('subject_type', $class)
                    ->where('subject_id', $model->getKey())
                    ->where('event', 'updated')
                    ->exists(),
                "Expected an updated activity entry for {$class}.",
            );
        }
    }
}
