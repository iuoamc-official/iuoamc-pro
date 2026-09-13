<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\JournalSubmission;
use App\Models\JournalSubmissionRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class JournalRevisionIntake
{
    public function __construct(private readonly JournalNotificationService $notifications) {}

    public function receive(
        JournalSubmission $submission,
        UploadedFile $manuscript,
        ?UploadedFile $responseLetter,
        ?string $authorNote,
        string $source,
    ): JournalSubmissionRevision {
        $uuid = (string) Str::uuid();
        $manuscriptHash = hash_file('sha256', $manuscript->getRealPath());
        $manuscriptPath = $manuscript->storeAs(
            'journal/submission-revisions/'.$uuid,
            $manuscriptHash.'.'.$this->safeExtension($manuscript),
            'local'
        );
        if ($manuscriptPath === false) {
            throw new RuntimeException('The revised manuscript could not be stored.');
        }

        $responseLetterHash = $responseLetter ? hash_file('sha256', $responseLetter->getRealPath()) : null;
        $responseLetterPath = $responseLetter?->storeAs(
            'journal/submission-revisions/'.$uuid,
            $responseLetterHash.'.'.$this->safeExtension($responseLetter),
            'local'
        );
        if ($responseLetter !== null && $responseLetterPath === false) {
            Storage::disk('local')->delete($manuscriptPath);
            throw new RuntimeException('The response letter could not be stored.');
        }

        try {
            return DB::transaction(function () use ($submission, $uuid, $manuscript, $manuscriptPath, $manuscriptHash, $responseLetter, $responseLetterPath, $responseLetterHash, $authorNote, $source): JournalSubmissionRevision {
                $locked = JournalSubmission::query()->with(['convertedArticle', 'journal'])->lockForUpdate()->findOrFail($submission->id);
                abort_unless($locked->status === 'converted' && $locked->convertedArticle?->status === 'revision_required', 409);
                $revisionNumber = (int) $locked->revisions()->max('revision_number') + 1;
                $revision = $locked->revisions()->create([
                    'record_uuid' => $uuid,
                    'revision_number' => $revisionNumber,
                    'manuscript_path' => $manuscriptPath,
                    'original_filename' => $this->safeFilename($manuscript),
                    'file_sha256' => $manuscriptHash,
                    'response_letter_path' => $responseLetterPath,
                    'response_letter_filename' => $responseLetter ? $this->safeFilename($responseLetter) : null,
                    'response_letter_sha256' => $responseLetterHash,
                    'author_note' => trim((string) $authorNote) ?: null,
                    'status' => 'received',
                    'received_at' => now()->utc()->startOfSecond(),
                ]);

                AuditTrail::record('journal.submission.revision_received', $revision, [], [
                    'submission_code' => $locked->submission_code,
                    'revision_number' => $revisionNumber,
                    'file_sha256' => $manuscriptHash,
                ], ['source' => $source]);

                $contactEmail = trim((string) $locked->journal->setting('contact_email'));
                if ($contactEmail !== '') {
                    $this->notifications->queue($locked->journal, 'revision_received', $contactEmail, 'en', [
                        'name' => 'Editorial Office',
                        'code' => $locked->submission_code,
                        'title' => $locked->title,
                        'revision' => $revisionNumber,
                        'workspace_url' => route('journal.control.submissions.show', ['locale' => 'en', 'submission' => $locked]),
                    ], $revision);
                }

                return $revision;
            }, 5);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(array_filter([$manuscriptPath, $responseLetterPath]));

            throw $exception;
        }
    }

    private function safeFilename(UploadedFile $file): string
    {
        return Str::limit((string) preg_replace('/[^\pL\pN._ -]+/u', '-', basename($file->getClientOriginalName())), 255, '');
    }

    private function safeExtension(UploadedFile $file): string
    {
        return match ($file->getMimeType()) {
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            default => Str::lower($file->getClientOriginalExtension()),
        };
    }
}
