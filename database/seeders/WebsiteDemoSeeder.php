<?php

namespace Database\Seeders;

use App\Enums\AcademicSessionStatus;
use App\Enums\BatchStatus;
use App\Enums\ExamResultStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamTypeStatus;
use App\Enums\NoticeAudience;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Banner;
use App\Models\Batch;
use App\Models\Course;
use App\Models\CourseProfile;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamType;
use App\Models\Faq;
use App\Models\GalleryImage;
use App\Models\GradingScale;
use App\Models\Notice;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherProfile;
use App\Models\Testimonial;
use App\Services\HomepageService;
use App\Services\SettingsService;
use App\Support\DemoImage;
use Illuminate\Database\Seeder;

/**
 * Realistic demo content for the public website. Safe to re-run: everything is
 * keyed on a natural identifier and existing admin edits to settings are kept.
 */
class WebsiteDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->banners();
        $courses = $this->courses();
        $this->teachers();
        $this->toppers($courses);
        $this->notices();
        $this->testimonials();
        $this->faqs();
        $this->gallery();

        app(HomepageService::class)->flush();
    }

    protected function settings(): void
    {
        $settings = app(SettingsService::class);

        $demo = [
            'general.address' => 'House 12, Road 5, Dhanmondi, Dhaka 1205, Bangladesh',
            'general.phone' => '+880 1711-000000',
            'general.email' => 'info@teachmanagement.example',
            'website.facebook' => 'https://www.facebook.com/',
            'website.youtube' => 'https://www.youtube.com/',
            'website.instagram' => 'https://www.instagram.com/',
            'website.linkedin' => 'https://www.linkedin.com/',
            'website.whatsapp' => '8801711000000',
            'website.meta_description' => 'Expert teachers, small batches and regular exams for SSC, HSC, admission test and English preparation in Dhaka. Apply online today.',
            'website.stat_students' => 1250,
            'website.stat_teachers' => 24,
            'website.stat_success_rate' => 96,
        ];

        // Only fill blanks – never overwrite what an admin already configured.
        $missing = collect($demo)->filter(fn ($value, $key) => blank($settings->get($key)))->all();

        if ($missing !== []) {
            $settings->setMany($missing);
        }
    }

    protected function banners(): void
    {
        $banners = [
            [
                'title' => 'Where ambitious students become top achievers',
                'title_bn' => 'যেখানে স্বপ্নবাজ শিক্ষার্থীরা হয়ে ওঠে সেরা',
                'subtitle' => 'Expert teachers, small batches and a smart learning system — admissions are open for the 2026–27 session.',
                'subtitle_bn' => 'অভিজ্ঞ শিক্ষক, ছোট ব্যাচ ও স্মার্ট শেখার ব্যবস্থা — ২০২৬–২৭ সেশনে ভর্তি চলছে।',
                'cta_label' => 'Apply Now',
                'cta_label_bn' => 'ভর্তি হোন',
                'cta_url' => '/apply',
                'palette' => 0,
            ],
            [
                'title' => 'Crack the admission test with confidence',
                'title_bn' => 'আত্মবিশ্বাসের সাথে ভর্তি পরীক্ষায় জয়ী হোন',
                'subtitle' => 'Medical, engineering and university admission prep with weekly model tests and mentor support.',
                'subtitle_bn' => 'সাপ্তাহিক মডেল টেস্ট ও মেন্টর সহায়তাসহ মেডিকেল, ইঞ্জিনিয়ারিং ও বিশ্ববিদ্যালয় ভর্তি প্রস্তুতি।',
                'cta_label' => 'See admission courses',
                'cta_label_bn' => 'ভর্তি কোর্স দেখুন',
                'cta_url' => '#courses',
                'palette' => 3,
            ],
            [
                'title' => 'Speak English fluently, score IELTS 7+',
                'title_bn' => 'সাবলীল ইংরেজি বলুন, আইএলটিএস ৭+ স্কোর করুন',
                'subtitle' => 'Small speaking-focused batches with certified IELTS trainers and mock tests every week.',
                'subtitle_bn' => 'সনদপ্রাপ্ত আইএলটিএস প্রশিক্ষকের সাথে কথা বলার অনুশীলনভিত্তিক ছোট ব্যাচ এবং প্রতি সপ্তাহে মক টেস্ট।',
                'cta_label' => 'Join the next batch',
                'cta_label_bn' => 'পরের ব্যাচে যোগ দিন',
                'cta_url' => '/apply',
                'palette' => 2,
            ],
        ];

        foreach ($banners as $index => $banner) {
            $palette = $banner['palette'];
            unset($banner['palette']);

            Banner::query()->updateOrCreate(
                ['title' => $banner['title']],
                $banner + [
                    'image' => DemoImage::make("website/demo/banner-{$index}.webp", 800, 1000, $palette),
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<string, Course>
     */
    protected function courses(): array
    {
        $definitions = [
            ['code' => 'WEB-SSC', 'name' => 'SSC Science (Class 9–10)', 'category' => 'School', 'duration' => '2 years', 'price' => 2500, 'period' => 'monthly', 'featured' => false,
                'tagline' => 'Complete board-oriented preparation in Math, Physics, Chemistry and Biology with weekly tests.',
                'description' => 'Complete board-oriented preparation for SSC Science students.'],
            ['code' => 'WEB-HSC-SCI', 'name' => 'HSC Science (Class 11–12)', 'category' => 'College', 'duration' => '2 years', 'price' => 3500, 'period' => 'monthly', 'featured' => true,
                'tagline' => 'Concept-first teaching, lab-style problem solving and monthly rank-based exams.',
                'description' => 'Concept-first HSC Science programme.'],
            ['code' => 'WEB-HSC-COM', 'name' => 'HSC Commerce (Class 11–12)', 'category' => 'College', 'duration' => '2 years', 'price' => 3000, 'period' => 'monthly', 'featured' => false,
                'tagline' => 'Accounting, Finance, Business Organisation and Economics taught by industry-experienced faculty.',
                'description' => 'HSC Commerce programme.'],
            ['code' => 'WEB-FOUND', 'name' => 'Foundation (Class 6–8)', 'category' => 'School', 'duration' => '1 year', 'price' => 1800, 'period' => 'monthly', 'featured' => false,
                'tagline' => 'Build strong basics in Math, Science and English before the board years begin.',
                'description' => 'Foundation programme for junior secondary students.'],
            ['code' => 'WEB-PRIMARY', 'name' => 'Primary Care (Class 3–5)', 'category' => 'School', 'duration' => '1 year', 'price' => 1500, 'period' => 'monthly', 'featured' => false,
                'tagline' => 'Gentle, activity-based learning that makes every young student curious and confident.',
                'description' => 'Primary level support programme.'],
            ['code' => 'WEB-ADMIT', 'name' => 'Admission Test Preparation', 'category' => 'Admission Prep', 'duration' => '6 months', 'price' => 12000, 'period' => 'one_time', 'featured' => true,
                'tagline' => 'Medical, engineering and public-university admission prep with weekly model tests.',
                'description' => 'Admission test preparation.'],
            ['code' => 'WEB-IELTS', 'name' => 'IELTS & Spoken English', 'category' => 'Language', 'duration' => '3 months', 'price' => 6500, 'period' => 'one_time', 'featured' => true,
                'tagline' => 'Speaking-focused batches with certified trainers and weekly IELTS mock tests.',
                'description' => 'IELTS and spoken English programme.'],
            ['code' => 'WEB-ICT', 'name' => 'ICT & Programming Basics', 'category' => 'Skills', 'duration' => '4 months', 'price' => 4500, 'period' => 'one_time', 'featured' => false,
                'tagline' => 'Learn computer fundamentals, Python and logic building with hands-on mini projects.',
                'description' => 'ICT and programming basics.'],
            ['code' => 'WEB-MATH-OLY', 'name' => 'Math Olympiad Club', 'category' => 'Skills', 'duration' => '3 months', 'price' => 2000, 'period' => 'monthly', 'featured' => false,
                'tagline' => 'Sharpen reasoning with olympiad problems, puzzles and friendly competitions.',
                'description' => 'Math olympiad training.'],
        ];

        $courses = [];

        foreach ($definitions as $index => $definition) {
            $course = Course::query()->updateOrCreate(
                ['code' => $definition['code']],
                ['name' => $definition['name'], 'description' => $definition['description'], 'is_active' => true],
            );

            CourseProfile::query()->updateOrCreate(
                ['course_id' => $course->id],
                [
                    'category' => $definition['category'],
                    'tagline' => $definition['tagline'],
                    'duration' => $definition['duration'],
                    'price' => $definition['price'],
                    'price_period' => $definition['period'],
                    'image' => DemoImage::make("website/demo/course-{$index}.webp", 640, 400, $index + 1),
                    'is_featured' => $definition['featured'],
                    'sort_order' => $index,
                ],
            );

            $courses[$definition['code']] = $course;
        }

        return $courses;
    }

    protected function teachers(): void
    {
        $teachers = [
            ['Md. Rafiqul Islam', 'Mathematics', 'M.Sc. in Mathematics, University of Dhaka', '15 years of helping students fall in love with Math and ace board exams.', 'RI'],
            ['Nusrat Jahan', 'Physics', 'M.Sc. in Physics, Jahangirnagar University', 'Makes tough physics concepts simple with real-life examples and experiments.', 'NJ'],
            ['Tanvir Ahmed', 'Chemistry', 'B.Sc. & M.Sc. in Chemistry, University of Dhaka', 'Known for crystal-clear organic chemistry shortcuts and exam strategy.', 'TA'],
            ['Farhana Akter', 'English & IELTS', 'M.A. in English, University of Dhaka · IELTS 8.0', 'Certified IELTS trainer focused on fluent speaking and confident writing.', 'FA'],
            ['Dr. Sabbir Hossain', 'Biology', 'MBBS, Dhaka Medical College', 'A practising doctor who guides medical aspirants with first-hand insight.', 'SH'],
            ['Mahmudul Hasan', 'ICT & Programming', 'B.Sc. in CSE, BUET', 'Software engineer turned teacher; builds logic through real mini projects.', 'MH'],
            ['Shirin Sultana', 'Bangla', 'M.A. in Bangla, Rajshahi University', 'Brings literature alive and trains students for creative, high-scoring writing.', 'SS'],
            ['Kamrul Hasan', 'Accounting', 'M.Com in Accounting, National University', 'Ten years of teaching commerce with a practical, case-study approach.', 'KH'],
        ];

        foreach ($teachers as $index => [$name, $subject, $qualification, $bio, $initials]) {
            $number = str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);

            $teacher = Teacher::query()->updateOrCreate(
                ['employee_no' => "EMP-DEMO-{$number}"],
                ['name' => $name, 'email' => null, 'phone' => null],
            );

            TeacherProfile::query()->updateOrCreate(
                ['teacher_id' => $teacher->id],
                [
                    'subject' => $subject,
                    'qualification' => $qualification,
                    'bio' => $bio,
                    'photo' => DemoImage::make("website/demo/teacher-{$index}.webp", 480, 600, $index, $initials, 0.3),
                    'is_featured' => true,
                    'sort_order' => $index,
                ],
            );
        }
    }

    /**
     * Published exam results for two batches, so the toppers carousel has content.
     *
     * @param  array<string, Course>  $courses
     */
    protected function toppers(array $courses): void
    {
        $session = AcademicSession::query()->where('is_current', true)->first()
            ?? AcademicSession::query()->updateOrCreate(
                ['name' => '2026-2027'],
                [
                    'start_date' => '2026-01-01',
                    'end_date' => '2026-12-31',
                    'status' => AcademicSessionStatus::Active,
                    'is_current' => true,
                ],
            );

        $examType = ExamType::query()->firstOrCreate(
            ['name' => 'Final'],
            ['weight' => 45, 'status' => ExamTypeStatus::Active],
        );

        $scale = GradingScale::query()->where('is_default', true)->first();

        $sets = [
            ['course' => 'WEB-HSC-SCI', 'batch' => 'HSC Science 2026', 'exam' => 'HSC Science Model Test 2026', 'students' => [
                ['Arif Rahman', 5.00, 96.40], ['Tasnim Akter', 5.00, 94.80], ['Sakib Hasan', 4.92, 92.10],
            ]],
            ['course' => 'WEB-SSC', 'batch' => 'SSC Science 2026', 'exam' => 'SSC Science Final Test 2026', 'students' => [
                ['Mim Chowdhury', 5.00, 97.20], ['Rahat Karim', 4.96, 93.50], ['Jannatul Ferdous', 4.88, 91.30],
            ]],
        ];

        $counter = 0;

        foreach ($sets as $set) {
            $batch = Batch::query()->firstOrCreate(
                ['course_id' => $courses[$set['course']]->id, 'session_id' => $session->id, 'name' => $set['batch']],
                ['start_date' => '2026-01-10', 'end_date' => '2026-11-30', 'capacity' => 40, 'status' => BatchStatus::Ongoing],
            );

            $exam = Exam::query()->firstOrCreate(
                ['batch_id' => $batch->id, 'name' => $set['exam']],
                [
                    'academic_session_id' => $session->id,
                    'exam_type_id' => $examType->id,
                    'start_date' => '2026-10-05',
                    'end_date' => '2026-10-15',
                    'status' => ExamStatus::Published,
                    'grading_scale_id' => $scale?->id,
                ],
            );

            if ($exam->status !== ExamStatus::Published) {
                $exam->update(['status' => ExamStatus::Published]);
            }

            foreach ($set['students'] as $rankIndex => [$name, $gpa, $percentage]) {
                $counter++;
                $initials = collect(explode(' ', $name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('');

                $student = Student::query()->updateOrCreate(
                    ['admission_no' => 'ADM-DEMO-'.str_pad((string) $counter, 3, '0', STR_PAD_LEFT)],
                    [
                        'name' => $name,
                        'photo' => DemoImage::make("website/demo/student-{$counter}.webp", 160, 160, $counter + 2, $initials, 0.34),
                        'status' => StudentStatus::Active,
                    ],
                );

                ExamResult::query()->withoutGlobalScopes()->updateOrCreate(
                    ['exam_id' => $exam->id, 'student_id' => $student->id],
                    [
                        'total_marks' => round(900 * $percentage / 100, 2),
                        'full_marks' => 900,
                        'percentage' => $percentage,
                        'gpa' => $gpa,
                        'letter_grade' => 'A+',
                        'status' => ExamResultStatus::Pass,
                        'rank_in_batch' => $rankIndex + 1,
                        'published_at' => now()->subDays(12),
                    ],
                );
            }
        }
    }

    protected function notices(): void
    {
        $notices = [
            ['Admissions open for the 2026–27 session', 'Admissions are now open for HSC, SSC, Foundation and Admission Test batches. Seats are limited to 40 per batch. Visit the campus or apply online to reserve your seat.', 1],
            ['Final test exam results published', 'Results of the Final Test Exam have been published. Students and guardians can log in to the portal to view marks, GPA and rank. Congratulations to all toppers!', 3],
            ['Free demo class this Friday', 'Join our free demo class for the new IELTS batch this Friday at 4:00 PM. Meet our trainers, try a mock speaking test and get a personal study plan.', 5],
            ['Parents’ meeting on 28 November', 'A parents’ meeting will be held on 28 November at 10:00 AM in the main hall to discuss progress reports and the upcoming exam schedule.', 8],
            ['Scholarship test for Class 8 students', 'A scholarship test will be held on 20 November for Class 8 students. Top performers will receive up to 50% tuition waiver for the Foundation programme.', 10],
            ['Winter break notice', 'The institute will remain closed for the winter break from 24 December to 1 January. Regular classes resume on 2 January with a revised routine.', 14],
            ['Mid-term exam routine for HSC batches', 'The mid-term exam routine for all HSC batches has been uploaded to the student portal. Please collect your admit card from the office a day before the exam.', 18],
            ['Fee payment reminder', 'Guardians are kindly requested to pay November tuition fees by the 15th to avoid late fees. You can view invoices and receipts from the student portal.', 21],
        ];

        foreach ($notices as [$title, $body, $daysAgo]) {
            Notice::query()->updateOrCreate(
                ['title' => $title],
                [
                    'body' => $body,
                    'audience' => NoticeAudience::All,
                    'is_active' => true,
                    'published_at' => now()->subDays($daysAgo),
                ],
            );
        }
    }

    protected function testimonials(): void
    {
        $items = [
            ['Arif Rahman', 'HSC 2026 · GPA 5.00', 'I joined with weak Physics basics. The teachers explained every concept patiently and the weekly tests kept me on track. I finished with a golden A+.', 'আমার পদার্থবিজ্ঞানের ভিত্তি দুর্বল ছিল। শিক্ষকরা ধৈর্য নিয়ে প্রতিটি বিষয় বুঝিয়েছেন আর সাপ্তাহিক পরীক্ষা আমাকে ট্র্যাকে রেখেছে। শেষে গোল্ডেন এ+ পেয়েছি।', 5],
            ['Mrs. Shamima Akter', 'Parent of a Class 9 student', 'The portal lets me check attendance and results anytime. My son has become more disciplined and his marks improved within three months.', 'পোর্টালে যেকোনো সময় উপস্থিতি ও ফলাফল দেখতে পারি। আমার ছেলে আগের চেয়ে অনেক নিয়মিত হয়েছে এবং তিন মাসের মধ্যেই নম্বর বেড়েছে।', 5],
            ['Tasnim Akter', 'SSC 2025 · GPA 5.00', 'Small batches mean teachers actually know you. Whenever I got stuck, someone helped me right after class. Best decision I made.', 'ছোট ব্যাচ হওয়ায় শিক্ষকরা সত্যিই আমাদের চেনেন। কোথাও আটকে গেলে ক্লাসের পরেই কেউ সাহায্য করতেন। আমার সেরা সিদ্ধান্ত ছিল এটি।', 5],
            ['Sakib Hasan', 'Admission Prep · DU “Ka” unit', 'The model tests felt exactly like the real admission exam. I walked into the hall calm and confident, and got a seat in my first-choice department.', 'মডেল টেস্টগুলো ঠিক আসল ভর্তি পরীক্ষার মতোই ছিল। আমি শান্ত ও আত্মবিশ্বাসী হয়ে পরীক্ষায় বসেছি এবং প্রথম পছন্দের বিভাগেই সুযোগ পেয়েছি।', 5],
            ['Mr. Anwar Hossain', 'Parent of a Class 7 student', 'Teachers call us when our daughter misses a class and share honest feedback. That personal care is rare these days.', 'আমার মেয়ে ক্লাস মিস করলে শিক্ষকরা ফোন করেন এবং সৎ মতামত জানান। আজকাল এমন ব্যক্তিগত যত্ন বিরল।', 4],
            ['Nabila Rahman', 'IELTS · Overall 7.5', 'I could barely speak in English before. Three months of speaking practice and weekly mocks got me a 7.5 overall.', 'আগে ইংরেজিতে ঠিকমতো কথাই বলতে পারতাম না। তিন মাসের স্পিকিং অনুশীলন আর সাপ্তাহিক মক টেস্টে ওভারঅল ৭.৫ পেয়েছি।', 5],
        ];

        foreach ($items as $index => [$name, $role, $quote, $quoteBn, $rating]) {
            $initials = collect(explode(' ', preg_replace('/^(Mr\.|Mrs\.|Dr\.)\s*/', '', $name)))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('');

            Testimonial::query()->updateOrCreate(
                ['name' => $name],
                [
                    'role' => $role,
                    'quote' => $quote,
                    'quote_bn' => $quoteBn,
                    'photo' => DemoImage::make("website/demo/testimonial-{$index}.webp", 128, 128, $index + 4, $initials, 0.36),
                    'rating' => $rating,
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }

    protected function faqs(): void
    {
        $items = [
            ['How do I apply for admission?', 'Click “Apply Now”, choose a course and submit the short form. Our admission team will call you within one working day to confirm a batch and explain the next steps.', 'কীভাবে ভর্তির আবেদন করব?', '“ভর্তি হোন” বাটনে ক্লিক করে একটি কোর্স বেছে নিয়ে ছোট ফর্মটি জমা দিন। আমাদের ভর্তি টিম এক কর্মদিবসের মধ্যে ফোন করে ব্যাচ নিশ্চিত করবে এবং পরের ধাপ জানাবে।'],
            ['What is the batch size?', 'We keep batches small — usually 25 to 40 students — so every student gets personal attention and doubts are cleared in class.', 'ব্যাচে কতজন শিক্ষার্থী থাকে?', 'আমরা ব্যাচ ছোট রাখি — সাধারণত ২৫ থেকে ৪০ জন — যাতে প্রত্যেকে ব্যক্তিগত মনোযোগ পায় এবং ক্লাসেই সমস্যার সমাধান হয়।'],
            ['Can I try a class before enrolling?', 'Yes. We run free demo classes before every new batch. Call us or send a message to reserve a demo slot.', 'ভর্তির আগে কি একটি ক্লাস করে দেখা যায়?', 'হ্যাঁ। প্রতিটি নতুন ব্যাচের আগে আমরা ফ্রি ডেমো ক্লাস নিই। ডেমো স্লট বুক করতে ফোন করুন বা বার্তা পাঠান।'],
            ['How are fees paid?', 'Tuition is paid monthly (or one-time for short courses) at the office, by bKash/Nagad or by bank transfer. A receipt is issued for every payment and you can view invoices in the student portal.', 'ফি কীভাবে দিতে হয়?', 'মাসিক টিউশন (ছোট কোর্সে এককালীন) অফিসে, বিকাশ/নগদে বা ব্যাংক ট্রান্সফারে দেওয়া যায়। প্রতিটি পেমেন্টের রসিদ দেওয়া হয় এবং স্টুডেন্ট পোর্টালে চালান দেখা যায়।'],
            ['Is there a scholarship or fee waiver?', 'Yes. Meritorious and needy students can get a partial tuition waiver through our scholarship tests. Watch the notice board for the next test date.', 'বৃত্তি বা ফি মওকুফের সুযোগ আছে কি?', 'হ্যাঁ। বৃত্তি পরীক্ষার মাধ্যমে মেধাবী ও দরিদ্র শিক্ষার্থীরা আংশিক টিউশন ফি মওকুফ পেতে পারে। পরবর্তী পরীক্ষার তারিখের জন্য নোটিশ বোর্ড দেখুন।'],
            ['How can parents track progress?', 'Guardians get a login to the portal where they can see attendance, exam results, notices and fee invoices at any time.', 'অভিভাবকরা কীভাবে অগ্রগতি দেখবেন?', 'অভিভাবকরা পোর্টালে লগইন করে যেকোনো সময় উপস্থিতি, পরীক্ষার ফলাফল, নোটিশ ও ফি-এর চালান দেখতে পারেন।'],
            ['What if my child misses a class?', 'Class notes are shared with the batch and teachers arrange a short catch-up session so no one falls behind.', 'ক্লাস মিস করলে কী হবে?', 'ব্যাচে ক্লাস নোট শেয়ার করা হয় এবং শিক্ষকরা সংক্ষিপ্ত ক্যাচ-আপ সেশনের ব্যবস্থা করেন, যাতে কেউ পিছিয়ে না পড়ে।'],
        ];

        foreach ($items as $index => [$question, $answer, $questionBn, $answerBn]) {
            Faq::query()->updateOrCreate(
                ['question' => $question],
                [
                    'answer' => $answer,
                    'question_bn' => $questionBn,
                    'answer_bn' => $answerBn,
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }

    protected function gallery(): void
    {
        $items = [
            ['Bright, well-equipped classrooms', 'উজ্জ্বল ও সুসজ্জিত শ্রেণিকক্ষ'],
            ['Science lab demonstration', 'বিজ্ঞান ল্যাবে ডেমোনস্ট্রেশন'],
            ['Annual prize-giving ceremony', 'বার্ষিক পুরস্কার বিতরণ অনুষ্ঠান'],
            ['Group study in the library', 'লাইব্রেরিতে দলগত পড়াশোনা'],
            ['Math Olympiad winners', 'গণিত অলিম্পিয়াডের বিজয়ীরা'],
            ['Spoken English workshop', 'স্পোকেন ইংলিশ কর্মশালা'],
            ['Cultural day celebration', 'সাংস্কৃতিক দিবস উদযাপন'],
            ['Parents’ meeting', 'অভিভাবক সমাবেশ'],
            ['Career guidance seminar', 'ক্যারিয়ার গাইডেন্স সেমিনার'],
            ['Topper felicitation', 'কৃতী শিক্ষার্থীদের সংবর্ধনা'],
            ['Computer lab session', 'কম্পিউটার ল্যাব ক্লাস'],
            ['Winter picnic', 'শীতকালীন বনভোজন'],
        ];

        foreach ($items as $index => [$title, $titleBn]) {
            $tall = $index % 3 === 0;

            GalleryImage::query()->updateOrCreate(
                ['title' => $title],
                [
                    'title_bn' => $titleBn,
                    'image' => DemoImage::make("website/demo/gallery-{$index}.webp", 640, $tall ? 800 : 480, $index + 1),
                    'sort_order' => $index,
                    'is_active' => true,
                ],
            );
        }
    }
}
