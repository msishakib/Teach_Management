<?php

namespace App\Services;

use App\Enums\GuardianRelation;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\UserType;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentService
{
    public function __construct(
        protected NumberGeneratorService $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{email?: string, password?: string, name?: string}|null  $userAttributes
     * @param  list<array{name: string, phone?: ?string, relation: string|GuardianRelation}>  $guardians
     */
    public function create(array $attributes, ?array $userAttributes = null, array $guardians = []): Student
    {
        return DB::transaction(function () use ($attributes, $userAttributes, $guardians) {
            if (blank($attributes['admission_no'] ?? null)) {
                $attributes['admission_no'] = $this->numbers->nextAdmission();
            }

            $attributes['status'] = $attributes['status'] ?? StudentStatus::Active;

            $student = Student::query()->create($attributes);

            if ($userAttributes !== null) {
                $student->user()->associate($this->createLinkedUser($student, $userAttributes));
                $student->save();
            }

            $this->syncGuardians($student, $guardians);

            return $student->fresh(['user', 'guardians']) ?? $student;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array{email?: string, password?: string, name?: string}|null  $userAttributes
     * @param  list<array{name: string, phone?: ?string, relation: string|GuardianRelation}>|null  $guardians
     */
    public function update(Student $student, array $attributes, ?array $userAttributes = null, ?array $guardians = null): Student
    {
        return DB::transaction(function () use ($student, $attributes, $userAttributes, $guardians) {
            unset($attributes['admission_no']);

            $student->update($attributes);

            if ($userAttributes !== null) {
                if ($student->user) {
                    $student->user->update(array_filter([
                        'name' => $userAttributes['name'] ?? $student->name,
                        'email' => $userAttributes['email'] ?? null,
                        'password' => $userAttributes['password'] ?? null,
                    ], static fn ($value) => $value !== null && $value !== ''));
                } else {
                    $student->user()->associate($this->createLinkedUser($student, $userAttributes));
                    $student->save();
                }
            }

            if ($guardians !== null) {
                $student->guardians()->detach();
                $this->syncGuardians($student, $guardians);
            }

            return $student->fresh(['user', 'guardians']) ?? $student;
        });
    }

    /**
     * @param  array{email?: string, password?: string, name?: string}  $userAttributes
     */
    protected function createLinkedUser(Student $student, array $userAttributes): User
    {
        $email = $userAttributes['email'] ?? null;
        $password = $userAttributes['password'] ?? null;

        if (blank($email) || blank($password)) {
            throw ValidationException::withMessages([
                'user_email' => 'Email and password are required to create a linked user.',
            ]);
        }

        $user = User::query()->create([
            'name' => $userAttributes['name'] ?? $student->name,
            'email' => $email,
            'password' => $password,
            'type' => UserType::Student,
            'email_verified_at' => now(),
        ]);

        $user->assignRole(RoleName::Student->value);

        return $user;
    }

    /**
     * @param  list<array{name: string, phone?: ?string, relation: string|GuardianRelation}>  $guardians
     */
    protected function syncGuardians(Student $student, array $guardians): void
    {
        foreach ($guardians as $guardianData) {
            if (blank($guardianData['name'] ?? null)) {
                continue;
            }

            $relation = $guardianData['relation'] ?? GuardianRelation::Guardian;
            $relationValue = $relation instanceof GuardianRelation ? $relation->value : (string) $relation;

            $guardian = Guardian::query()->create([
                'name' => $guardianData['name'],
                'phone' => $guardianData['phone'] ?? null,
            ]);

            $student->guardians()->attach($guardian->id, [
                'relation' => $relationValue,
            ]);
        }
    }
}
