<?php

namespace App\Http\Controllers\Public;

use App\Enums\NumberSequenceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdmissionApplicationRequest;
use App\Models\AdmissionApplication;
use App\Models\Course;
use App\Models\StudentRegistration;
use App\Services\NumberGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdmissionController extends Controller
{
    public function showApply()
    {
        $request = request();
        $courses = Course::query()->orderBy('name')->get(['id', 'name']);

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

        $data['application_no'] = $numbers->next(NumberSequenceType::Admission);

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

    public function trackForm(Request $request)
    {
        $lookup = $request->session()->get('track_lookup');
        $lookup = is_array($lookup) ? $lookup : null;

        $application = null;
        $registration = null;

        if (filled($lookup['id'] ?? null)) {
            if (($lookup['type'] ?? null) === 'registration') {
                $registration = StudentRegistration::query()->find($lookup['id']);
            } else {
                $application = AdmissionApplication::query()
                    ->with(['course', 'batch'])
                    ->find($lookup['id']);
            }
        }

        $queryType = $request->query('type');
        $queryType = is_string($queryType) ? $queryType : 'admission';

        $queryNumber = $request->query('number');
        $queryNumber = is_string($queryNumber) ? $queryNumber : null;

        $type = old('type', $lookup['type'] ?? $queryType);
        if (! in_array($type, ['admission', 'registration'], true)) {
            $type = 'admission';
        }

        $oldNumber = old('application_no');
        $number = is_string($oldNumber) ? $oldNumber : ($lookup['number'] ?? $queryNumber);

        return view('site.track_application', [
            'application' => $application,
            'registration' => $registration,
            'type' => $type,
            'searched' => $lookup !== null,
            'number' => $number,
        ]);
    }

    public function track(Request $request)
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(['admission', 'registration'])],
            'application_no' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $type = $data['type'] ?? 'admission';
        $number = trim($data['application_no']);
        $phone = trim($data['phone']);

        $record = $type === 'registration'
            ? StudentRegistration::query()
                ->where('registration_no', $number)
                ->where('phone', $phone)
                ->first()
            : AdmissionApplication::query()
                ->where('application_no', $number)
                ->where('phone', $phone)
                ->first();

        $request->session()->flash('track_lookup', [
            'type' => $type,
            'id' => $record?->getKey(),
            'number' => $number,
        ]);

        return redirect()->route('admissions.track.form');
    }
}
