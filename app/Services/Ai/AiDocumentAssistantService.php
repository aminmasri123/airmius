<?php

namespace App\Services\Ai;

use App\Models\Club;
use App\Models\ClubPolicyDocument;
use App\Models\File;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\UploadStorage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiDocumentAssistantService
{
    /**
     * @param  iterable<int, File>  $files
     */
    public function summarizeAuthorizedFiles(User $user, iterable $files, string $operation = 'summary', ?string $targetLocale = null): array
    {
        $authorized = collect($files)
            ->filter(fn (File $file) => Gate::forUser($user)->allows('view', $file))
            ->map(fn (File $file) => $this->sourceFromFile($file))
            ->filter(fn (array $source) => trim($source['text']) !== '')
            ->values();

        return [
            'operation' => $operation === 'translation' ? 'translation' : 'summary',
            'target_locale' => $targetLocale,
            'output_label' => $operation === 'translation'
                ? 'KI-Übersetzung aus bereits berechtigten Quellen'
                : 'KI-Zusammenfassung aus bereits berechtigten Quellen',
            'answerable' => $authorized->isNotEmpty(),
            'sources_considered' => $authorized->count(),
            'sources' => $authorized->map(fn (array $source) => $this->sourceReference($source))->all(),
            'content' => $authorized->isEmpty()
                ? 'Keine berechtigten Inhalte für diese Anfrage verfügbar.'
                : $this->composeSummary($authorized, $operation, $targetLocale),
        ];
    }

    public function answerManualQuestion(User $user, Club $club, string $question): array
    {
        $terms = $this->questionTerms($question);
        $sources = $this->approvedManualSources($user, $club);
        $matches = $sources
            ->map(fn (array $source) => [
                ...$source,
                'quote' => $this->bestQuote($source['text'], $terms),
            ])
            ->filter(fn (array $source) => $source['quote'] !== null)
            ->values();

        if ($terms === [] || $matches->isEmpty()) {
            return [
                'answerable' => false,
                'answer' => 'Keine Antwort in den freigegebenen Handbuchquellen gefunden.',
                'citations' => [],
                'sources_considered' => $sources->count(),
                'policy' => 'no_answer_without_citation',
            ];
        }

        return [
            'answerable' => true,
            'answer' => $this->composeAnswer($matches),
            'citations' => $matches
                ->map(fn (array $source) => [
                    ...$this->sourceReference($source),
                    'quote' => $source['quote'],
                ])
                ->all(),
            'sources_considered' => $sources->count(),
            'policy' => 'quotes_required',
        ];
    }

    private function approvedManualSources(User $user, Club $club): Collection
    {
        $canViewPrivate = (int) $club->owner_id === (int) $user->id
            || ClubPermissions::allows($club, $user, ClubPermissions::POLICY_DOCUMENTS_EDIT)
            || ClubPermissions::allows($club, $user, ClubPermissions::POLICY_DOCUMENTS_DELETE);

        return ClubPolicyDocument::query()
            ->with('file')
            ->where('club_id', $club->id)
            ->whereNotNull('version_label')
            ->whereNotNull('valid_from')
            ->when(! $canViewPrivate, fn ($query) => $query->where('is_public', true))
            ->orderByDesc('valid_from')
            ->get()
            ->filter(fn (ClubPolicyDocument $document) => $document->file
                && Gate::forUser($user)->allows('view', $document->file))
            ->map(fn (ClubPolicyDocument $document) => [
                ...$this->sourceFromFile($document->file),
                'manual_title' => $document->title,
                'version_label' => $document->version_label,
                'valid_from' => $document->valid_from?->toDateString(),
                'is_public' => (bool) $document->is_public,
            ])
            ->filter(fn (array $source) => trim($source['text']) !== '')
            ->values();
    }

    private function sourceFromFile(File $file): array
    {
        return [
            'type' => 'file',
            'id' => $file->id,
            'title' => $file->display_name,
            'text' => $this->readPlainText($file),
        ];
    }

    private function readPlainText(File $file): string
    {
        if (! $file->path || ! Storage::disk(UploadStorage::disk())->exists($file->path)) {
            return '';
        }

        return Str::of(Storage::disk(UploadStorage::disk())->get($file->path))
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->limit(4000, '')
            ->toString();
    }

    private function composeSummary(Collection $sources, string $operation, ?string $targetLocale): string
    {
        $lines = $sources->map(function (array $source): string {
            $snippet = Str::of($source['text'])->limit(260, '...')->toString();

            return "{$source['title']}: {$snippet}";
        })->implode("\n");

        if ($operation === 'translation') {
            return "Zielsprache: ".($targetLocale ?: 'nicht angegeben')."\n".$lines;
        }

        return $lines;
    }

    private function composeAnswer(Collection $matches): string
    {
        return $matches
            ->map(fn (array $source) => ($source['manual_title'] ?? $source['title']).': '.$source['quote'])
            ->implode("\n");
    }

    private function sourceReference(array $source): array
    {
        return array_filter([
            'type' => $source['type'],
            'id' => $source['id'],
            'title' => $source['manual_title'] ?? $source['title'],
            'version_label' => $source['version_label'] ?? null,
            'valid_from' => $source['valid_from'] ?? null,
            'is_public' => $source['is_public'] ?? null,
        ], fn ($value) => $value !== null);
    }

    private function questionTerms(string $question): array
    {
        preg_match_all('/[\pL\pN]{4,}/u', Str::lower($question), $matches);

        return collect($matches[0] ?? [])
            ->reject(fn (string $term) => in_array($term, ['welche', 'wann', 'wird', 'werden', 'eine', 'einen', 'durch', 'darf', 'kann'], true))
            ->unique()
            ->values()
            ->all();
    }

    private function bestQuote(string $text, array $terms): ?string
    {
        foreach (preg_split('/(?<!\d\.)(?<=[.!?])\s+/u', $text) ?: [] as $sentence) {
            $normalized = Str::lower($sentence);
            if (collect($terms)->contains(fn (string $term) => Str::contains($normalized, $term))) {
                return Str::of($sentence)->trim()->limit(320, '...')->toString();
            }
        }

        return null;
    }
}
