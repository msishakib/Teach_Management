<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\AdmissionApplication;
use App\Models\User;
use App\Services\AdmissionService;
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

        $trackResponse->assertOk();
        $trackResponse->assertSee($app->application_no);
    }

    public function test_approve_creates_student_and_invoice(): void
    {
        $course = Course::factory()->create();
        $batch = Batch::factory()->create(['course_id' => $course->id]);

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

