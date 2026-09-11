<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AccountDocument;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class AccountDocumentRegistry
{
    public function query(User $actor)
    {
        abort_unless($actor->canDo('account-documents.view'), 403);

        return AccountDocument::query()->whereIn(
            'organization_id', app(InstitutionalAccess::class)->organizationIds($actor)
        );
    }

    public function issue(User $actor, array $data, UploadedFile $pdf): AccountDocument
    {
        abort_unless($actor->canDo('account-documents.manage'), 403);
        $organization = Organization::query()->findOrFail((int) $data['organization_id']);
        app(InstitutionalAccess::class)->authorizeOrganization($actor, $organization);
        abort_unless($organization->status === 'active', 409);

        $email = AccountRecordAccess::normalizeEmail($data['recipient_email']);
        $bytes = file_get_contents($pdf->getRealPath());
        if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF-')) {
            throw new RuntimeException('ACCOUNT_DOCUMENT_INVALID_PDF');
        }

        $uuid = (string) Str::uuid();
        $relative = 'account-documents/'.$uuid.'/document.pdf';
        $this->writePrivate($relative, $bytes);

        try {
            return DB::transaction(function () use ($actor, $data, $organization, $email, $bytes, $uuid, $relative): AccountDocument {
                $document = new AccountDocument([
                    'record_uuid' => $uuid,
                    'organization_id' => $organization->id,
                    'category' => $data['category'],
                    'title' => trim($data['title']),
                    'reference' => isset($data['reference']) ? trim((string) $data['reference']) ?: null : null,
                    'recipient_email' => $email,
                    'email_hmac' => AccountRecordAccess::emailHmac($email),
                    'amount' => $data['amount'] ?? null,
                    'currency' => isset($data['currency']) ? strtoupper(trim((string) $data['currency'])) ?: null : null,
                    'pdf_path' => $relative,
                    'pdf_sha256' => hash('sha256', $bytes),
                    'issued_at' => now()->utc()->startOfSecond(),
                    'issued_by' => $actor->id,
                    'created_at' => now()->utc()->startOfSecond(),
                ]);
                $document->save();
                $document->record_hash = MembershipRegistry::digest($this->snapshot($document));
                $document->saveQuietly();
                $audit = AuditTrail::record('account_document.issued', $document, [], [
                    'record_uuid' => $document->record_uuid,
                    'category' => $document->category,
                    'reference' => $document->reference,
                    'pdf_sha256' => $document->pdf_sha256,
                    'record_hash' => $document->record_hash,
                ], ['module' => 'accounting', 'organization_id' => (int) $organization->id], (int) $actor->id);
                $document->integrity_audit_id = $audit->id;
                $document->saveQuietly();

                return $document->fresh(['organization', 'integrityAudit']);
            }, 3);
        } catch (Throwable $error) {
            @unlink(storage_path('app/private/'.$relative));
            throw $error;
        }
    }

    public function verify(AccountDocument $document): bool
    {
        try {
            $current = AccountDocument::query()->with('integrityAudit')->find($document->id);
            $audit = $current?->integrityAudit;

            return $current !== null && $audit !== null
                && hash_equals((string) $current->record_hash, MembershipRegistry::digest($this->snapshot($current)))
                && hash_equals((string) $current->pdf_sha256, hash_file('sha256', $this->safePath($current->pdf_path)) ?: '')
                && $audit->auditable_type === $current->getMorphClass()
                && (int) $audit->auditable_id === (int) $current->id
                && ($audit->new_values['record_hash'] ?? null) === $current->record_hash
                && app(IntegrityService::class)->verifyAuditLog($audit)['valid'];
        } catch (Throwable) {
            return false;
        }
    }

    public function downloadPath(AccountDocument $document): string
    {
        abort_unless($this->verify($document), 409);

        return $this->safePath($document->pdf_path);
    }

    private function snapshot(AccountDocument $document): array
    {
        return [
            'schema' => 'iuoamc-account-document-v1',
            'id' => (int) $document->id,
            'record_uuid' => $document->record_uuid,
            'organization_id' => (int) $document->organization_id,
            'category' => $document->category,
            'title' => $document->title,
            'reference' => $document->reference,
            'recipient_email_encrypted' => $document->getAttributes()['recipient_email'] ?? null,
            'email_hmac' => $document->email_hmac,
            'amount' => $document->amount,
            'currency' => $document->currency,
            'pdf_path' => $document->pdf_path,
            'pdf_sha256' => $document->pdf_sha256,
            'issued_at' => $document->issued_at?->utc()->format('Y-m-d\TH:i:sP'),
            'issued_by' => (int) $document->issued_by,
        ];
    }

    private function writePrivate(string $relative, string $bytes): void
    {
        $path = storage_path('app/private/'.$relative);
        if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true) && ! is_dir(dirname($path))) {
            throw new RuntimeException('ACCOUNT_DOCUMENT_STORAGE_UNAVAILABLE');
        }
        chmod(dirname($path), 0700);
        if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) {
            throw new RuntimeException('ACCOUNT_DOCUMENT_WRITE_FAILED');
        }
        chmod($path, 0600);
    }

    private function safePath(string $relative): string
    {
        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
            throw new RuntimeException('ACCOUNT_DOCUMENT_PATH_INVALID');
        }

        return storage_path('app/private/'.$relative);
    }
}
