<?php

namespace Tests\Feature;

use App\Enums\FeeFrequency;
use App\Models\AdmissionApplication;
use App\Models\Batch;
use App\Models\Course;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\User;
use App\Services\AdmissionService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_submit_and_track_application(): void
    {
        $course = Course::factory()->create();
        $batch = Batch::factory()->create(['course_id' => $course->id]);

        $response = $this->post(route('apply.store', ['locale' => 'en']), [
            'name' => 'Alice',
            'phone' => '0123456789',
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'hp_name' => '',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('admission_applications', [
            'name' => 'Alice',
            'phone' => '0123456789',
        ]);

        $app = AdmissionApplication::first();

        $trackResponse = $this->post(route('admissions.track', ['locale' => 'en']), [
            'application_no' => $app->application_no,
            'phone' => $app->phone,
        ]);

        $trackResponse->assertRedirect(route('admissions.track.form', ['locale' => 'en']));

        $page = $this->get(route('admissions.track.form', ['locale' => 'en']));

        $page->assertOk();
        $page->assertSee($app->application_no);
        $page->assertSee('Alice');
    }

    public function test_approve_creates_student_and_invoice(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $course = Course::factory()->create();
        $batch = Batch::factory()->create(['course_id' => $course->id]);

        FeeStructure::factory()->create([
            'course_id' => $course->id,
            'fee_type_id' => FeeType::factory()->tuition()->create()->id,
            'amount' => 1000,
            'frequency' => FeeFrequency::Monthly,
        ]);

        $app = AdmissionApplication::query()->create([
            'application_no' => 'APP-1',
            'name' => 'Bob',
            'phone' => '0987654321',
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'pending',
        ]);

        $admin = User::factory()->create();

        $service = app(AdmissionService::class);
        $service->approve($app, $admin);

        $this->assertDatabaseHas('students', ['name' => 'Bob']);
        $this->assertDatabaseHas('student_invoices', ['batch_id' => $batch->id]);
        $this->assertDatabaseHas('admission_applications', ['application_no' => 'APP-1', 'status' => 'approved']);
    }
}
