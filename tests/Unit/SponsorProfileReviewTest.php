<?php

namespace Tests\Unit;

use App\Models\Sponsor;
use App\Models\User;
use App\Services\SponsorProfileReviewService;
use Tests\TestCase;
use ReflectionClass;
use Illuminate\Validation\ValidationException;

class SponsorProfileReviewTest extends TestCase
{
    public function test_legal_name_and_country_changes_are_visible_to_reviewer(): void
    {
        $service = (new ReflectionClass(SponsorProfileReviewService::class))->newInstanceWithoutConstructor();
        $sponsor = new Sponsor([
            'name' => 'Aida',
            'legal_name' => 'Aida GmbH',
            'country_code' => 'DE',
        ]);
        $before = $service->snapshot($sponsor);

        $sponsor->fill(['legal_name' => 'Aida Sport GmbH', 'country_code' => 'FR']);

        self::assertSame([
            'legal_name' => ['before' => 'Aida GmbH', 'after' => 'Aida Sport GmbH'],
            'country_code' => ['before' => 'DE', 'after' => 'FR'],
        ], $service->changes($before, $sponsor));
    }

    public function test_review_routes_are_separate_from_general_editing(): void
    {
        $root = dirname(__DIR__, 2);
        $webRoutes = file_get_contents($root.'/routes/admin.php');
        $apiRoutes = file_get_contents($root.'/routes/api.php');
        $reviewService = file_get_contents($root.'/app/Services/SponsorProfileReviewService.php');

        self::assertStringContainsString("->name('sponsors.review')", $webRoutes);
        self::assertStringContainsString("->name('sponsor-management.review')", $apiRoutes);
        self::assertStringContainsString('ensureSponsorApprovable($sponsor)', $reviewService);
        self::assertStringContainsString("$".'decision === ' . "'rejected' && blank($".'note)', $reviewService);
    }

    public function test_owner_cannot_review_own_pending_profile(): void
    {
        $service = (new ReflectionClass(SponsorProfileReviewService::class))->newInstanceWithoutConstructor();
        $sponsor = new Sponsor(['owner_user_id' => 7, 'verification_status' => 'pending_review']);
        $reviewer = new User;
        $reviewer->id = 7;

        $this->expectException(ValidationException::class);
        $service->decide($sponsor, $reviewer, 'verified', null);
    }
}
