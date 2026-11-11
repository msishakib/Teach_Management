<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdmissionApplicationRequest;
use App\Models\AdmissionApplication;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdmissionController extends Controller
{
    public function showApply()
    {
        $request = request();
        $courses = \App\Models\Course::query()->orderBy('name')->get(['id','name']);

        return view('site.apply', [
            'courses' => $courses,
            'selectedCourse' => (int) $request->query('course', 0),
        ]);
    }

    public function store(StoreAdmissionApplicationRequest $request, NumberGeneratorService $numbers)
    {
        // rate limiting: simple throttle middleware should be used in routes; also check honeypot in request

        $data = $request->validated();

        // handle uploads
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('admissions/photos', 'public');
        }

        $documents = [];
        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $f) {
                $documents[] = $f->store('admissions/documents', 'public');
            }
        }
        $data['documents'] = $documents;

        $data['application_no'] = $numbers->next(\App\Enums\NumberSequenceType::Admission);

        $application = AdmissionApplication::query()->create($data);

        return redirect()->route('admissions.success', [
            'locale' => app()->getLocale(),
            'application_no' => $application->application_no,
        ]);
    }

    public function success(string $application_no)
    {
        $application = AdmissionApplication::query()->where('application_no', $application_no)->firstOrFail();
        return view('site.apply_success', compact('application'));
    }

    public function trackForm()
    {
        return view('site.track_application');
    }

    public function track(Request $request)
    {
        $data = $request->validate([
            'application_no' => ['required','string'],
            'phone' => ['required','string'],
        ]);

        $application = AdmissionApplication::query()
            ->where('application_no', $data['application_no'])
            ->where('phone', $data['phone'])
            ->first();

        return view('site.track_application', compact('application'));
    }
}

