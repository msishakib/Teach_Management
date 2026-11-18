<?php

namespace Tests\Feature;

use App\Models\AdmissionApplication;
use App\Models\Course;
use App\Models\StudentRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_form_renders_in_both_locales(): void
    {
        $this->get(route('admissions.track.form', ['locale' => 'en']))
            ->assertOk()
            ->assertSee(__('site.track.title'))
            ->assertSee(__('site.track.type_registration'));

        $this->get(route('admissions.track.form', ['locale' => 'bn']))
            ->assertOk()
            ->assertSee('আপনার আবেদন ট্র্যাক করুন');
    }

    public function test_visitor_can_track_an_admission_application_with_number_and_phone(): void
    {
        $course = Course::factory()->create(['name' => 'Physics']);

        $application = AdmissionApplication::query()->create([
            'application_no' => 'ADM-2026-0001',
            'name' => 'Alice Applicant',
            'phone' => '01712345678',
            'course_id' => $course->id,
            'status' => 'reviewing',
            'remarks' => 'Documents verified.',
        ]);

        $response = $this->post(route('admissions.track', ['locale' => 'en']), [
            'type' => 'admission',
            'application_no' => $application->application_no,
            'phone' => $application->phone,
        ]);

        $response->assertRedirect(route('admissions.track.form', ['locale' => 'en']));

        $page = $this->get(route('admissions.track.form', ['locale' => 'en']));

        $page->assertOk()
            ->assertSee($application->application_no)
            ->assertSee('Alice Applicant')
            ->assertSee('Physics')
            ->assertSee(__('site.track.statuses.reviewing'))
            ->assertSee(__('site.track.messages.admission.reviewing'))
            ->assertSee('Documents verified.');
    }

    public function test_visitor_can_track_a_student_registration(): void
    {
        $registration = StudentRegistration::query()->create([
            'registration_no' => 'REG-2026-0001',
            'name' => 'Nadia Rahman',
            'phone' => '01711223344',
            'present_address' => '12 Green Road, Dhaka',
            'last_exam' => 'HSC',
            'last_institution' => 'Dhaka College',
            'status' => StudentRegistration::STATUS_APPROVED,
        ]);

        $this->post(route('admissions.track', ['locale' => 'en']), [
            'type' => 'registration',
            'application_no' => $registration->registration_no,
            'phone' => $registration->phone,
        ])->assertRedirect(route('admissions.track.form', ['locale' => 'en']));

        $page = $this->get(route('admissions.track.form', ['locale' => 'en']));

        $page->assertOk()
            ->assertSee($registration->registration_no)
            ->assertSee('Nadia Rahman')
            ->assertSee(__('site.track.type_registration'))
            ->assertSee(__('site.track.statuses.approved'))
            ->assertSee(__('site.track.messages.registration.approved'))
            ->assertSee('HSC')
            ->assertSee('Dhaka College')
            ->assertDontSee(__('site.track.not_found_title'));
    }

    public function test_tracking_with_a_wrong_phone_number_shows_no_record(): void
    {
        $course = Course::factory()->create();

        $application = AdmissionApplication::query()->create([
            'application_no' => 'ADM-2026-0002',
            'name' => 'Private Person',
            'phone' => '01712345678',
            'course_id' => $course->id,
            'status' => 'pending',
        ]);

        $this->post(route('admissions.track', ['locale' => 'en']), [
            'type' => 'admission',
            'application_no' => $application->application_no,
            'phone' => '01999999999',
        ])->assertRedirect(route('admissions.track.form', ['locale' => 'en']));

        $this->get(route('admissions.track.form', ['locale' => 'en']))
            ->assertOk()
            ->assertSee(__('site.track.not_found_title'))
            ->assertDontSee('Private Person');
    }

    public function test_tracking_requires_a_number_and_phone(): void
    {
        $this->from(route('admissions.track.form', ['locale' => 'en']))
            ->post(route('admissions.track', ['locale' => 'en']), [])
            ->assertSessionHasErrors(['application_no', 'phone']);
    }

    public function test_number_and_type_can_be_prefilled_from_the_confirmation_page(): void
    {
        $this->get(route('admissions.track.form', [
            'locale' => 'en',
            'type' => 'registration',
            'number' => 'REG-2026-0010',
        ]))
            ->assertOk()
            ->assertSee('value="REG-2026-0010"', false);
    }
}
