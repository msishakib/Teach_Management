<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'student' => $this->whenLoaded('student', fn () => $this->student ? [
                'id' => $this->student->id,
                'admission_no' => $this->student->admission_no,
                'name' => $this->student->name,
                'phone' => $this->student->phone,
            ] : null),
            'teacher' => $this->whenLoaded('teacher', fn () => $this->teacher ? [
                'id' => $this->teacher->id,
                'employee_no' => $this->teacher->employee_no,
                'name' => $this->teacher->name,
                'phone' => $this->teacher->phone,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
