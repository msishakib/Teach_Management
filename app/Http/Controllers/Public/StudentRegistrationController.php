<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRegistrationRequest;
use App\Models\StudentRegistration;
use App\Services\NumberGeneratorService;

class StudentRegistrationController extends Controller
{
    public function show()
    {
        return view('site.register');
    }

    public function store(StoreStudentRegistrationRequest $request, NumberGeneratorService $numbers)
    {
        $data = $request->validated();
        unset($data['hp_name']);

        $data['registration_no'] = $numbers->nextRegistration();
        $data['status'] = StudentRegistration::STATUS_PENDING;

        $registration = StudentRegistration::query()->create($data);

        return redirect()->route('register.success', ['registration' => $registration->registration_no]);
    }

    public function success(StudentRegistration $registration)
    {
        return view('site.register_success', ['registration' => $registration]);
    }
}
