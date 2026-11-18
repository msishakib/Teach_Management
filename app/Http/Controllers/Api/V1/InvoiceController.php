<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\StudentInvoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()?->student;

        abort_unless($student !== null, 403, 'Only students can view invoices.');

        $invoices = StudentInvoice::query()
            ->with(['batch.course', 'items.feeType'])
            ->where('student_id', $student->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('id')
            ->paginate(25);

        return InvoiceResource::collection($invoices);
    }
}
