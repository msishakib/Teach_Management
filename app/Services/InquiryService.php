<?php

namespace App\Services;

use App\Models\ContactMessage;

class InquiryService
{
    /**
     * Persist a public contact message or admission application.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(string $type, array $data, ?string $ip = null): ContactMessage
    {
        return ContactMessage::query()->create([
            'type' => $type,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'] ?? null,
            'course_id' => $data['course_id'] ?? null,
            'ip_address' => $ip,
        ]);
    }
}
