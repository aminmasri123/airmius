<?php

namespace App\Jobs;

use App\Models\TrainingAiGeneration;
use App\Models\User;
use App\Services\Training\TrainingAiPreviewGenerator;
use App\Services\Training\TrainingResourceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateTrainingAiPreview implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(public string $generationId)
    {
        $this->onConnection('training_ai');
    }

    public function handle(TrainingAiPreviewGenerator $generator): void
    {
        // An expired/replayed queue delivery must never spend another AI request.
        if (! TrainingAiGeneration::whereKey($this->generationId)->where('status', 'queued')
            ->where('created_at', '>=', now()->subMinutes(15))->update(['status' => 'running', 'updated_at' => now()])) {
            return;
        }
        $generation = TrainingAiGeneration::findOrFail($this->generationId);
        $started = microtime(true);
        try {
            $user = User::findOrFail($generation->user_id);
            if (! app(TrainingResourceService::class)->canManageTrainingPlans($user)) {
                throw new \RuntimeException('Training permission revoked.');
            }
            $context = $generation->context;
            $result = $generator->generate($user, $context['data'], $context['readiness']);
            $generation->update(['status' => 'completed', 'result' => $result, 'context' => []]);
            Log::info('AI training preview completed.', ['generation_id' => $generation->id, 'duration_ms' => round((microtime(true) - $started) * 1000)]);
        } catch (Throwable $error) {
            $this->failed($error);
        }
    }

    public function failed(?Throwable $error): void
    {
        Log::warning('AI training preview job failed.', ['generation_id' => $this->generationId, 'exception' => $error?->getMessage()]);
        $message = 'Die Vorschau konnte nicht vollständig erstellt werden. Bitte versuche es später erneut.';
        if (preg_match('/in (\d+) Sekunden/u', $error?->getMessage() ?? '', $match)) {
            $message = 'Das KI-Limit wurde erreicht. Bitte warte '.(int) ceil(((int) $match[1]) / 60).' Minuten, bevor du eine neue Vorschau anforderst.';
        }
        TrainingAiGeneration::whereKey($this->generationId)->whereIn('status', ['queued', 'running'])->update([
            'status' => 'failed',
            'error' => $message,
        ]);
    }
}
