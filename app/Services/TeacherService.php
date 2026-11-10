<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Enums\UserType;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherService
{
    public function __construct(
        protected NumberGeneratorService $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{email?: string, password?: string, name?: string}|null  $userAttributes
     */
    public function create(array $attributes, ?array $userAttributes = null): Teacher
    {
        return DB::transaction(function () use ($attributes, $userAttributes) {
            if (blank($attributes['employee_no'] ?? null)) {
                $attributes['employee_no'] = $this->numbers->nextEmployee();
            }

            $teacher = Teacher::query()->create($attributes);

            if ($userAttributes !== null) {
                $teacher->user()->associate($this->createLinkedUser($teacher, $userAttributes));
                $teacher->save();
            }

            return $teacher->fresh(['user']) ?? $teacher;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{email?: string, password?: string, name?: string}|null  $userAttributes
     */
    public function update(Teacher $teacher, array $attributes, ?array $userAttributes = null): Teacher
    {
        return DB::transaction(function () use ($teacher, $attributes, $userAttributes) {
            unset($attributes['employee_no']);

            $teacher->update($attributes);

            if ($userAttributes !== null) {
                if ($teacher->user) {
                    $teacher->user->update(array_filter([
                        'name' => $userAttributes['name'] ?? $teacher->name,
                        'email' => $userAttributes['email'] ?? null,
                        'password' => $userAttributes['password'] ?? null,
                    ], static fn ($value) => $value !== null && $value !== ''));
                } else {
                    $teacher->user()->associate($this->createLinkedUser($teacher, $userAttributes));
                    $teacher->save();
                }
            }

            return $teacher->fresh(['user']) ?? $teacher;
        });
    }

    /**
     * @param  array{email?: string, password?: string, name?: string}  $userAttributes
     */
    protected function createLinkedUser(Teacher $teacher, array $userAttributes): User
    {
        $email = $userAttributes['email'] ?? null;
        $password = $userAttributes['password'] ?? null;

        if (blank($email) || blank($password)) {
            throw ValidationException::withMessages([
                'user_email' => 'Email and password are required to create a linked user.',
            ]);
        }

        $user = User::query()->create([
            'name' => $userAttributes['name'] ?? $teacher->name,
            'email' => $email,
            'password' => $password,
            'type' => UserType::Teacher,
            'email_verified_at' => now(),
        ]);

        $user->assignRole(RoleName::Teacher->value);

        return $user;
    }
}
