<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()?->student;

        abort_unless($student !== null, 403, 'Only students can view orders.');

        $orders = Order::query()
            ->with(['course', 'paymentAttempts'])
            ->where('student_id', $student->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('id')
            ->paginate(25);

        return OrderResource::collection($orders);
    }
}
