<?php
declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\LegacyCertificate;
use App\Services\LegacyCertificateRegistry;
use App\Services\LegacyCertificateTrash;
use App\Services\LegacyCertificatePurge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

final class LegacyCertificateController extends Controller
{
    public function index(Request $request): View
    {
        $registry = app(LegacyCertificateRegistry::class);
        try {
            $release = $registry->currentImport();
            $purge = app(LegacyCertificatePurge::class)->reconciliation($release);
            $purgeApplied = $purge['purged'] > 0;
            // Once purged, the former trash manifest can never restore or expose a row.
            $trashedIds = $purgeApplied ? [] : app(LegacyCertificateTrash::class)->activeIds();
            $originalRecords = LegacyCertificate::query()->where('import_id', $release->id)->get();
            foreach ($originalRecords as $originalRecord) {
                if (! $registry->verifyRecord($originalRecord, $release)['passed']) {
                    throw new \RuntimeException('LEGACY_CERTIFICATE_INTEGRITY_FAILED');
                }
            }
            if ($originalRecords->count() !== $purge['remaining']
                || count(array_diff($trashedIds, $originalRecords->modelKeys())) !== 0) {
                throw new \RuntimeException('LEGACY_CERTIFICATE_SCOPE_MISMATCH');
            }
        }
        catch (Throwable $error) {
            $this->logReadFailure($error);
            abort(409, trans('legacy_certificates.integrity_failure'));
        }
        $statuses = array_values(array_filter(array_keys($release->summary['stored_status_counts']),
            static fn ($status): bool => is_string($status) && $status !== '(null)'));
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', Rule::in($statuses)],
            'ambiguity' => ['nullable', Rule::in(['all', 'ambiguous', 'unique'])],
            'scope' => ['nullable', Rule::in(['active', 'trash'])],
        ]);
        $filters['scope'] = $filters['scope'] ?? 'active';
        $trashedLookup = array_fill_keys($trashedIds, true);
        $purgedCount = (int) $purge['purged'];
        $scopeRecords = $originalRecords->filter(static fn ($row): bool =>
            isset($trashedLookup[(int) $row->id]) === ($filters['scope'] === 'trash'));
        $activeCount = $scopeRecords->filter(static fn ($row): bool => $row->stored_status === 'active')->count();
        $ambiguousRecords = $scopeRecords->filter(static fn ($row): bool => $row->is_ambiguous);
        $stats = ['total' => $scopeRecords->count(), 'active' => $activeCount,
            'nonactive' => $scopeRecords->count() - $activeCount,
            'ambiguous' => $ambiguousRecords->count(),
            'ambiguous_groups' => $ambiguousRecords->map(static fn ($row): string =>
                implode(',', $row->sql_candidate_source_ids))->unique()->count()];
        $query = LegacyCertificate::query()->where('import_id', $release->id);
        if ($filters['scope'] === 'trash') { $query->whereIn('id', $trashedIds); }
        else { $query->whereNotIn('id', $trashedIds); }
        if (isset($filters['q']) && $filters['q'] !== '') {
            // SQL parameters, with LIKE wildcards escaped as literals.
            $search = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(function ($sub) use ($search): void {
                foreach (['holder_name', 'registration_number', 'source_id', 'barcode'] as $index => $column) {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $sub->{$method}("`{$column}` LIKE ? ESCAPE '!'", [$search]);
                }
            });
        }
        if (isset($filters['status']) && $filters['status'] !== '') { $query->where('stored_status', $filters['status']); }
        if (($filters['ambiguity'] ?? 'all') !== 'all') { $query->where('is_ambiguous', $filters['ambiguity'] === 'ambiguous'); }
        $recordsPaginator = $query->orderBy('id')->paginate(25)->withQueryString();
        foreach ($recordsPaginator as $record) {
            abort_unless($registry->verifyRecord($record, $release)['passed'], 409, trans('legacy_certificates.integrity_failure'));
        }
        return view('control.legacy_certificates.index', compact('recordsPaginator', 'stats', 'filters', 'statuses', 'release', 'purgeApplied', 'purgedCount')
            + ['records' => $recordsPaginator]);
    }

    public function show(Request $request): View
    {
        $registry = app(LegacyCertificateRegistry::class);
        try {
            $release = $registry->currentImport();
            $purge = app(LegacyCertificatePurge::class)->reconciliation($release);
            $purgeApplied = $purge['purged'] > 0;
            $trashedIds = $purgeApplied ? [] : app(LegacyCertificateTrash::class)->activeIds();
        }
        catch (Throwable $error) {
            $this->logReadFailure($error);
            abort(409, trans('legacy_certificates.integrity_failure'));
        }
        // A signed purge receipt distinguishes a deleted URL from an unknown record.
        $routeId = (string) $request->route('certificate');
        if (preg_match('/^[1-9][0-9]{0,17}$/D', $routeId) === 1
            && in_array((int) $routeId, $purge['local_ids'], true)) {
            abort(410, trans('legacy_certificates.permanently_deleted'));
        }
        // Locale precedes the record parameter: never rely on positional route injection.
        $record = LegacyCertificate::query()->where('import_id', $release->id)
            ->findOrFail((string) $request->route('certificate'));
        $integrity = $registry->verifyRecord($record, $release);
        abort_unless($integrity['passed'], 409, trans('legacy_certificates.integrity_failure'));
        $candidates = $record->is_ambiguous
            ? LegacyCertificate::query()->where('import_id', $release->id)
                ->where('source_database', $record->source_database)->where('source_table', $record->source_table)
                ->whereIn('source_id', $record->sql_candidate_source_ids)->orderBy('id')->get()
            : collect();
        if ($record->is_ambiguous) {
            abort_unless($candidates->count() === $record->candidate_count, 409, trans('legacy_certificates.integrity_failure'));
            foreach ($candidates as $candidate) {
                abort_unless($registry->verifyRecord($candidate, $release)['passed'], 409, trans('legacy_certificates.integrity_failure'));
            }
        }
        $trashed = in_array((int) $record->id, $trashedIds, true);
        return view('control.legacy_certificates.show', compact('record', 'candidates', 'release', 'integrity', 'trashed', 'trashedIds', 'purgeApplied'));
    }

    /** Log a redacted category only; logging must never alter the blocked response. */
    private function logReadFailure(Throwable $error): void
    {
        try {
            $message = $error->getMessage();
            if (preg_match('/^LEGACY_[A-Z0-9_]{1,160}$/D', $message) === 1) {
                $reason = $message;
            } elseif (str_contains(strtolower($message), 'open_basedir')) {
                $reason = 'PHP_PATH_RESTRICTION';
            } else {
                $class = class_basename($error);
                $reason = preg_match('/^[A-Za-z][A-Za-z0-9_]{0,100}$/D', $class) === 1
                    ? $class : 'RuntimeFailure';
            }
            Log::warning('LEGACY_CERTIFICATE_ACCESS_BLOCKED', ['reason' => $reason]);
        } catch (Throwable) {
            // Do not expose exception messages or replace the original 409 on log failures.
        }
    }
}
