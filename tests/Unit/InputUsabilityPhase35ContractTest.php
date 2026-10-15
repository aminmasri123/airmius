<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class InputUsabilityPhase35ContractTest extends TestCase
{
    private function source(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    public function test_shared_web_controls_cover_small_screens_focus_errors_and_busy_states(): void
    {
        $modal = $this->source('resources/js/Components/Modal.vue');
        self::assertStringContainsString('<dialog', $modal);
        self::assertStringContainsString('@cancel="handleCancel"', $modal);
        self::assertStringContainsString('@close="restoreFocus"', $modal);
        self::assertStringContainsString('overflow-y-auto', $modal);
        self::assertStringContainsString('100dvh', $modal);

        $error = $this->source('resources/js/Components/InputError.vue');
        self::assertStringContainsString('role="alert"', $error);
        self::assertStringContainsString('aria-live="assertive"', $error);

        $button = $this->source('resources/js/Components/UI/AppButton.vue');
        self::assertStringContainsString(":aria-busy=\"loading ? 'true' : undefined\"", $button);
        self::assertStringContainsString(':disabled="disabled || loading"', $button);
    }

    public function test_dates_and_localized_numbers_fail_explicitly_instead_of_silently_coercing(): void
    {
        $date = $this->source('resources/js/Components/DateInput.vue');
        self::assertStringContainsString("event.target.setCustomValidity('')", $date);
        self::assertStringContainsString('Bitte ein gültiges Datum im Format TT.MM.JJJJ eingeben.', $date);
        self::assertStringContainsString('Enter a valid date in DD.MM.YYYY format.', $date);

        $number = $this->source('resources/js/utils/localizedNumber.js');
        self::assertStringContainsString("text.replace(',', '.')", $number);
        self::assertStringContainsString('Number.isFinite(number)', $number);

        foreach ([
            'resources/js/Components/ClubMemberships/ClubFinanceWorkspace.vue',
            'resources/js/Components/ClubMemberships/ClubSepaFee.vue',
            'resources/js/Components/ClubMemberships/ClubDonationForm.vue',
        ] as $form) {
            self::assertStringContainsString("@/utils/localizedNumber.js", $this->source($form));
        }
    }

    public function test_api_errors_are_structured_rate_limited_and_do_not_expose_production_traces(): void
    {
        $errors = $this->source('app/Support/Api/V1/ApiErrorResponse.php');
        foreach (['unauthenticated', 'forbidden', 'not_found', 'conflict', 'unprocessable_entity', 'rate_limited', 'server_error'] as $code) {
            self::assertStringContainsString("'$code'", $errors);
        }
        self::assertStringContainsString("config('app.debug')", $errors);
        self::assertStringContainsString("'An unexpected error occurred.'", $errors);

        $mobile = $this->source('mobile/airmius_mobile/lib/core/airmius_api_client.dart');
        self::assertStringContainsString('looksTechnical', $mobile);
        self::assertStringContainsString('sqlstate|stack\\s*trace|traceback|fatal\\s+error', $mobile);
    }

    public function test_private_responses_and_offline_sensitive_actions_have_explicit_boundaries(): void
    {
        $cache = $this->source('app/Http/Middleware/EnforceApiCachePolicy.php');
        self::assertStringContainsString('private, no-store, no-cache, must-revalidate, max-age=0', $cache);

        $queue = $this->source('mobile/airmius_mobile/lib/core/airmius_service_container.dart');
        self::assertStringContainsString('offline_sensitive_request', $queue);
        self::assertStringContainsString('request.method != \'GET\'', $queue);
        self::assertStringContainsString('await queuedTransport.clear()', $queue);
    }
}
