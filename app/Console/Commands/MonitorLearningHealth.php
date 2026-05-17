<?php

namespace App\Console\Commands;

use App\Models\LearningAssignmentSubmission;
use App\Models\LearningCourse;
use App\Models\LearningLesson;
use App\Models\LearningSecurityEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MonitorLearningHealth extends Command
{
    protected $signature = 'airmius:monitor-learning-health {--hours=24}';

    protected $description = 'Monitor E-Learning security events, video availability, and operational course health.';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $since = now()->subHours($hours);

        $criticalEvents = LearningSecurityEvent::query()
            ->where('created_at', '>=', $since)
            ->where('severity', 'critical')
            ->count();
        $warningEvents = LearningSecurityEvent::query()
            ->where('created_at', '>=', $since)
            ->where('severity', 'warning')
            ->count();
        $missingVideos = $this->missingLocalVideos();
        $publishedWithoutLessons = LearningCourse::query()
            ->where('status', 'published')
            ->where('is_public', true)
            ->whereDoesntHave('lessons')
            ->count();
        $openSubmissions = LearningAssignmentSubmission::query()
            ->where('status', 'submitted')
            ->where('submitted_at', '<=', now()->subDays(3))
            ->count();

        $this->table(['Check', 'Value'], [
            ['Window', "{$hours} hours"],
            ['Critical security events', $criticalEvents],
            ['Warning security events', $warningEvents],
            ['Missing local video files', $missingVideos],
            ['Published courses without lessons', $publishedWithoutLessons],
            ['Assignment submissions older than 3 days', $openSubmissions],
        ]);

        if ($criticalEvents > 0 || $missingVideos > 0 || $publishedWithoutLessons > 0) {
            $this->error('Learning monitor found issues that need attention.');

            return self::FAILURE;
        }

        $this->info('Learning monitor is healthy.');

        return self::SUCCESS;
    }

    private function missingLocalVideos(): int
    {
        return LearningLesson::query()
            ->whereNotNull('video_url')
            ->get()
            ->filter(function (LearningLesson $lesson) {
                $path = $this->publicStoragePathFromUrl($lesson->video_url);

                return $path && ! Storage::disk('public')->exists($path);
            })
            ->count();
    }

    private function publicStoragePathFromUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $marker = '/storage/';
        $position = strpos($path, $marker);

        if ($position === false) {
            return null;
        }

        return ltrim(substr($path, $position + strlen($marker)), '/');
    }
}
