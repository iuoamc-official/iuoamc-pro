<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ProCertificate;
use Illuminate\Support\Collection;

final class ProCertificateWorkspace
{
    /**
     * Separate the newest certificate for each recipient, catalog type and achievement from its history.
     *
     * @param  Collection<int, ProCertificate>  $certificates
     * @return array{current: Collection<int, ProCertificate>, archive: Collection<int, ProCertificate>}
     */
    public function partition(Collection $certificates): array
    {
        $seen = [];
        $current = collect();
        $archive = collect();

        foreach ($certificates->sortByDesc(fn (ProCertificate $certificate): int => (int) $certificate->getKey()) as $certificate) {
            $key = $this->displayKey($certificate);

            if (isset($seen[$key])) {
                $archive->push($certificate);

                continue;
            }

            $seen[$key] = true;
            $current->push($certificate);
        }

        return ['current' => $current->values(), 'archive' => $archive->values()];
    }

    private function displayKey(ProCertificate $certificate): string
    {
        $typeIdentity = $certificate->catalog_type_id !== null
            ? 'catalog:'.$certificate->catalog_type_id
            : 'legacy:'.$certificate->certificate_type.'|'.$this->normalize($certificate->program_title);

        return implode('|', [
            (string) $certificate->organization_id,
            $typeIdentity,
            $certificate->achievement_date?->toDateString() ?? '',
            $this->normalize($certificate->recipient_name ?: $certificate->public_name),
        ]);
    }

    private function normalize(mixed $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';

        return mb_strtolower($value, 'UTF-8');
    }
}
