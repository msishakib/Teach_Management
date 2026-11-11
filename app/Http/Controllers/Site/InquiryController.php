<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\Site\StoreAdmissionRequest;
use App\Http\Requests\Site\StoreContactRequest;
use App\Models\ContactMessage;
use App\Models\Course;
use App\Services\InquiryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    public function __construct(
        protected InquiryService $inquiries,
    ) {}

    public function apply(Request $request): View
    {
        $courses = Course::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('site.apply', [
            'courses' => $courses,
            'selectedCourse' => (int) $request->query('course'),
        ]);
    }

    public function storeAdmission(StoreAdmissionRequest $request): RedirectResponse
    {
        if (! $request->filled('company_website')) {
            $this->inquiries->store(ContactMessage::TYPE_ADMISSION, $request->validated(), $request->ip());
        }

        return redirect()->route('apply')->with('status', __('site.apply.success'));
    }

    public function storeContact(StoreContactRequest $request): RedirectResponse
    {
        if (! $request->filled('company_website')) {
            $this->inquiries->store(ContactMessage::TYPE_CONTACT, $request->validated(), $request->ip());
        }

        return redirect()->to(route('home').'#contact')->with('status', __('site.contact.success'));
    }
}
