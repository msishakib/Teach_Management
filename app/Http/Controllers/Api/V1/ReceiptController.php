<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\ReceiptResource;
use App\Models\Receipt;
use App\Support\DateRange;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()?->student;

        abort_unless($student !== null, 403, 'Only students can view receipts.');

        $receipts = Receipt::query()
            ->with(['allocations.invoice'])
            ->where('student_id', $student->id)
            ->when($request->filled('from'), fn ($query) => $query->where('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('date', '<', DateRange::endExclusive($request->date('to'))))
            ->latest('date')
            ->latest('id')
            ->paginate(25);

        return ReceiptResource::collection($receipts);
    }
}
