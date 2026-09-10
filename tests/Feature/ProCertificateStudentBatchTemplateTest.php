<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ProCertificateStudent;
use App\Models\Role;
use App\Models\User;
use App\Services\ProCertificateStudentArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class ProCertificateStudentBatchTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_load_private_names_without_implicit_public_names(): void
    {
        $organization = Organization::query()->create([
            'code' => 'ICGA-TEST',
            'legal_name' => 'ICGA Test Organization',
            'display_name' => 'ICGA Test',
            'status' => 'active',
            'is_root' => true,
        ]);
        $actor = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create(['name' => 'Super Administrator', 'slug' => 'super-admin']);
        $actor->roles()->attach($role);

        $this->preserveStudent($organization, 'Student One', 1);
        $this->preserveStudent($organization, 'Student Two', 2);

        $template = app(ProCertificateStudentArchive::class)->batchTemplate($actor, (int) $organization->id);

        self::assertSame(2, $template['count']);
        self::assertSame(
            "recipient_name\tpublic_name\tspecialization\nStudent One\t\t\nStudent Two\t\t\n",
            $template['tsv'],
        );
    }

    public function test_user_without_certificate_management_permission_cannot_load_private_names(): void
    {
        $organization = Organization::query()->create([
            'code' => 'ICGA-PRIVATE',
            'legal_name' => 'ICGA Private Organization',
            'display_name' => 'ICGA Private',
            'status' => 'active',
            'is_root' => true,
        ]);
        $actor = User::factory()->create(['status' => 'active']);
        $this->preserveStudent($organization, 'Private Student', 1);

        $this->expectException(HttpException::class);

        app(ProCertificateStudentArchive::class)->batchTemplate($actor, (int) $organization->id);
    }

    private function preserveStudent(Organization $organization, string $name, int $sourceId): void
    {
        ProCertificateStudent::query()->create([
            'record_uuid' => (string) Str::uuid(),
            'organization_id' => (int) $organization->id,
            'private_name' => $name,
            'name_fingerprint' => hash('sha256', $name),
            'source_record_count' => 1,
            'first_source_record_id' => $sourceId,
            'last_source_record_id' => $sourceId,
            'archived_at' => now()->utc()->startOfSecond(),
            'created_at' => now()->utc()->startOfSecond(),
        ]);
    }
}
