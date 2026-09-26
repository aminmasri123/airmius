<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileClubAccessManagementSourceTest extends TestCase
{
    public function test_native_access_management_uses_the_complete_server_contract(): void
    {
        $client = $this->source('mobile/airmius_mobile/lib/core/airmius_api_client.dart');
        $repository = $this->source('mobile/airmius_mobile/lib/core/airmius_api_repositories.dart');
        $contract = $this->source('mobile/airmius_mobile/lib/core/airmius_api_models.dart');
        $screen = $this->source('mobile/airmius_mobile/lib/screens/club_access_management_screen.dart');

        foreach ([
            '/api/v1/clubs/$clubId/members/$userId/permissions',
            '/api/v1/clubs/$clubId/role-definitions',
            '/api/v1/clubs/$clubId/role-definitions/$roleId',
            '/api/v1/clubs/$clubId/members/$userId/role-definitions',
            '/api/v1/clubs/$clubId/permission-delegations',
            '/api/v1/clubs/$clubId/permission-delegations/$delegationId/revoke',
            '/api/v1/clubs/$clubId/organization',
            '/api/v1/clubs/$clubId/access-handover-reviews',
            '/api/v1/clubs/$clubId/access-handover-reviews/$reviewId/propose',
            '/api/v1/clubs/$clubId/access-handover-reviews/$reviewId/approve',
        ] as $path) {
            $this->assertStringContainsString($path, $client);
        }

        foreach ([
            'clubMemberPermissions',
            'updateClubMemberPermissions',
            'clubRoleDefinitions',
            'createClubRoleDefinition',
            'updateClubRoleDefinition',
            'deleteClubRoleDefinition',
            'clubMemberRoleDefinitions',
            'updateClubMemberRoleDefinitions',
            'clubPermissionDelegations',
            'createClubPermissionDelegation',
            'revokeClubPermissionDelegation',
            'clubOrganization',
            'clubAccessHandoverReviews',
            'proposeClubAccessHandover',
            'approveClubAccessHandover',
        ] as $method) {
            $this->assertStringContainsString($method, $contract);
            $this->assertStringContainsString($method, $repository);
            $this->assertStringContainsString($method, $screen);
        }

        $this->assertStringContainsString('length: 4', $screen);
        $this->assertStringContainsString("'assignments': assignments", $screen);
        $this->assertStringContainsString("'scope_type': scopeType", $screen);
        $this->assertStringContainsString("'starts_at': startsAt.toIso8601String()", $screen);
        $this->assertStringContainsString("'ends_at': endsAt.toIso8601String()", $screen);
        $this->assertStringContainsString('_delegablePermissionKeys.contains', $screen);
        $this->assertStringNotContainsString('http://', $screen);
        $this->assertStringNotContainsString('https://', $screen);
    }

    public function test_member_action_is_permission_gated_and_access_copy_exists_in_every_language(): void
    {
        $membership = $this->source('mobile/airmius_mobile/lib/screens/club_membership_management_screen.dart');
        $translations = $this->source('mobile/airmius_mobile/lib/core/airmius_l10n.dart');

        $this->assertStringContainsString("import 'club_access_management_screen.dart';", $membership);
        $this->assertStringContainsString('club.management?.canEditMemberPermissions ?? false', $membership);
        $this->assertStringContainsString('ClubAccessManagementScreen(', $membership);

        foreach ([
            'title', 'permissions', 'roles', 'delegations', 'permissionHint',
            'assignments', 'roleDefinitions', 'delegationCreate', 'scopeClub',
            'scopeDepartment', 'scopeTeam', 'startsAt', 'endsAt', 'grant',
            'status.active', 'status.scheduled', 'status.expired', 'status.revoked',
            'handover', 'handoverIntro', 'handoverNone', 'decision',
            'decisionRemove', 'decisionSuccessor', 'successor', 'propose', 'approve',
            'secondPersonHint', 'handoverStatus.pending', 'handoverStatus.proposed',
            'handoverStatus.approved', 'handoverStatus.applied', 'handoverStatus.stale',
        ] as $key) {
            $this->assertSame(
                4,
                substr_count($translations, "'membership.access.$key':"),
                "membership.access.$key must exist in DE, EN, FR and AR.",
            );
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(base_path($path));

        $this->assertIsString($source, $path.' must be readable.');

        return $source;
    }
}
