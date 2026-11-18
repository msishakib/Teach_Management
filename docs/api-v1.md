# REST API v1

Student-facing JSON API for the Flutter app, built on Laravel Sanctum.
Base URL: `/api/v1`. All responses are JSON unless noted otherwise.

- [Authentication](#authentication)
- [Conventions](#conventions)
- [Endpoints](#endpoints)
  - [Auth](#auth)
  - [Profile](#profile)
  - [Routine](#routine)
  - [Attendance](#attendance)
  - [Invoices](#invoices)
  - [Receipts](#receipts)
  - [Notices](#notices)
  - [Courses](#courses)
  - [Lessons](#lessons)
  - [Progress](#progress)
  - [Quiz attempts](#quiz-attempts)
  - [Orders](#orders)
- [Errors](#errors)
- [Rate limiting](#rate-limiting)
- [Tests](#tests)

## Authentication

Tokens are issued by `POST /api/v1/login` and must be sent on every
protected request:

```
Authorization: Bearer <token>
Accept: application/json
```

Tokens are personal access tokens (Sanctum). `POST /logout` revokes only the
token used for the current request, so signing out on one device keeps other
devices signed in.

Login is throttled like any other API route (60 requests/minute per IP).

```bash
curl -X POST https://example.test/api/v1/login \
  -H "Accept: application/json" \
  -d "email=student@example.com" \
  -d "password=secret" \
  -d "device_name=flutter"
```

```json
{
  "token": "1|3f9c...",
  "token_type": "Bearer",
  "user": {
    "id": 12,
    "name": "Rahim Uddin",
    "email": "student@example.com",
    "roles": ["student"],
    "student": {
      "id": 7,
      "admission_no": "STU-0007",
      "name": "Rahim Uddin",
      "phone": "01700000000"
    },
    "teacher": null,
    "created_at": "2026-01-04T09:12:00.000000Z"
  }
}
```

Failed credentials return `422` with an `email` validation message.

## Conventions

- **Envelope.** Single resources are returned as `{ "data": { ... } }`.
  Collections are returned as
  `{ "data": [ ... ], "links": { ... }, "meta": { ... } }`.
- **Pagination.** Every collection is paginated with `page` (1-based).
  Page sizes are fixed per endpoint: courses 20, lessons/routine/attendance 50,
  invoices/receipts/notices/orders 25. Read `meta.current_page`,
  `meta.last_page`, `meta.total` and the `links.next` URL for infinite scroll.
- **Dates.** Calendar dates use `YYYY-MM-DD`; timestamps use ISO-8601 UTC
  (`2026-11-16T12:00:00.000000Z`).
- **Money.** Amounts are returned as stored (decimal strings such as
  `"1500.00"`), not floats.
- **Student access.** Most endpoints require the authenticated user to have a
  linked student profile. Staff accounts receive `403` on student-only
  endpoints. Course and lesson access is additionally checked against active
  enrollments and lesson publication.
- **Fields that don't apply** are omitted rather than returned as `null`
  (for example `is_completed` before it is calculated).

## Endpoints

### Auth

| Method | Path | Body | Notes |
| --- | --- | --- | --- |
| `POST` | `/api/v1/login` | `email`, `password`, `device_name?` | Public. Returns a bearer token. |
| `POST` | `/api/v1/logout` | – | Revokes the current token. `204 No Content`. |

### Profile

| Method | Path | Notes |
| --- | --- | --- |
| `GET` | `/api/v1/profile` | Current user with `roles`, `student` and `teacher`. |

### Routine

| Method | Path | Query | Notes |
| --- | --- | --- | --- |
| `GET` | `/api/v1/routine` | `day?`, `page?` | Class routine for batches with an active enrollment. `day` accepts the stored enum value (for example `sunday`). |

Each row includes `day`, `start_time`, `end_time`, `batch.course`,
`subject` and `teacher`.

### Attendance

| Method | Path | Query | Notes |
| --- | --- | --- | --- |
| `GET` | `/api/v1/attendance` | `from?`, `to?`, `page?` | Student attendance, newest first. Date filters compare the session date. |

Each row includes `status`, `remarks` and the nested
`session.date`, `session.start_time`, `session.status`,
`session.subject` and `session.batch.course`.

### Invoices

| Method | Path | Query | Notes |
| --- | --- | --- | --- |
| `GET` | `/api/v1/invoices` | `status?`, `page?` | Student invoices, newest first. |

`status` is the stored invoice status (for example `unpaid`, `partial`,
`paid`). Each row includes `invoice_no`, `month`, `due_date`, `total`,
`discount`, `paid`, `due`, `status`, `batch.course` and `items[]`
(description, amount, fee type).

### Receipts

| Method | Path | Query | Notes |
| --- | --- | --- | --- |
| `GET` | `/api/v1/receipts` | `from?`, `to?`, `page?` | Payment receipts, newest first. |

Each row includes `receipt_no`, `date`, `method`, `amount`, `status`,
`notes` and `allocations[]` with the settled invoice (`id`, `invoice_no`).

### Notices

| Method | Path | Query | Notes |
| --- | --- | --- | --- |
| `GET` | `/api/v1/notices` | `audience?`, `page?` | Published, unexpired notices visible to the current portal user. Pinned notices come first. |

Each row includes `title`, `body`, `audience`, `is_pinned`,
`published_at`, `expires_at` and `batch` when the notice targets one batch.

### Courses

| Method | Path | Query | Notes |
| --- | --- | --- | --- |
| `GET` | `/api/v1/my-courses` | `search?`, `page?` | Courses tied to the student's active enrollments, ordered by name. |

Each row includes `id`, `code`, `name`, `short_description`,
`delivery_mode`, `access_days`, `lessons_count`,
`completed_lessons_count`, `progress_percent` and `is_completed`.

```json
{
  "data": [
    {
      "id": 3,
      "code": "CSE-101",
      "name": "Web Development",
      "lessons_count": 24,
      "completed_lessons_count": 6,
      "progress_percent": 25,
      "is_completed": false
    }
  ],
  "links": { "first": "...?page=1", "last": "...?page=1", "prev": null, "next": null },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 1 }
}
```

### Lessons

| Method | Path | Query | Notes |
| --- | --- | --- | --- |
| `GET` | `/api/v1/lessons` | `course_id` (required), `section_id?`, `page?` | Published lessons in display order. `403` when the student cannot access the course. |

Each row includes `title`, `type`, `content`, `video_type`, `video_url`,
`duration_seconds`, `is_free_preview`, `is_published`, `sort_order`,
`is_completed`, `is_locked` and downloadable `resources[]`.

`is_locked` is `true` when the lesson exists but the student's enrollment
does not grant access yet; clients should show a lock instead of the media.

### Progress

| Method | Path | Body | Notes |
| --- | --- | --- | --- |
| `POST` | `/api/v1/progress` | `lesson_id`, `completed?` (default `true`) | Marks a lesson complete/incomplete, recalculates course completion and returns the new totals. |

```json
{
  "data": {
    "lesson_id": 41,
    "course_id": 3,
    "completed": true,
    "completed_at": "2026-11-16T12:00:00.000000Z",
    "completed_lessons": 7,
    "total_lessons": 24,
    "progress_percent": 29.17,
    "course_completed": false,
    "course_completed_at": null
  }
}
```

Sending `completed=false` clears the completion timestamp and recalculates
progress in the same way.

### Quiz attempts

| Method | Path | Body | Notes |
| --- | --- | --- | --- |
| `POST` | `/api/v1/quiz-attempts` | `attempt_id` or `quiz_id`, `answers?[]`, `submit?` | Starts an attempt, saves answers, then optionally submits it. |

- Start a new attempt with `quiz_id`.
- Continue an existing attempt with `attempt_id` (ownership is enforced).
- `answers[]` entries take `question_id` plus either
  `selected_option_ids[]` (multiple choice) or `answer_text`
  (short/long answer).
- Send `submit: true` to grade and close the attempt. Graded results are
  included only once the attempt is closed; while it is in progress the
  result fields are withheld.

`quiz_id` is required when `attempt_id` is absent; `attempt_id` is optional.
Requests with both are treated as a continuation of `attempt_id`.

### Orders

| Method | Path | Query | Notes |
| --- | --- | --- | --- |
| `GET` | `/api/v1/orders` | `status?`, `page?` | Course purchase orders, newest first. |

Each row includes `order_no`, `amount`, `discount`, `net_amount`, `status`,
`gateway`, `gateway_txn_id`, `paid_at`, `created_at`, `course` and
`payment_attempts[]`.

## Errors

The API uses conventional HTTP status codes with Laravel's JSON error body:

| Status | Meaning |
| --- | --- |
| `401` | Missing, invalid or revoked token. |
| `403` | Authenticated but not allowed (no student profile, no course access, lesson locked). |
| `404` | Record not found or not owned by the current student. |
| `422` | Validation failed; `errors` maps field names to messages. |
| `429` | Rate limit exceeded; check the `Retry-After` header. |

```json
{
  "message": "The given data was invalid.",
  "errors": { "course_id": ["The selected course id is invalid."] }
}
```

## Rate limiting

`throttle:api` is applied to every route: **60 requests per minute** per
authenticated user, or per IP address for login and unauthenticated calls.
Responses include `X-RateLimit-Limit`, `X-RateLimit-Remaining` and, when
throttled, `Retry-After`.

## Tests

`tests/Feature/ApiV1Test.php` covers login/logout, profile, attendance,
invoices, receipts, notices, courses, lessons, progress, quiz attempts and
orders, including authorization and cross-student isolation.

```bash
php artisan test --filter=ApiV1Test
```
