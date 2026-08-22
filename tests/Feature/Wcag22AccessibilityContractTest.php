<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class Wcag22AccessibilityContractTest extends TestCase
{
    public function test_shared_dialogs_are_named_localized_and_restore_keyboard_focus(): void
    {
        $modal = $this->source('resources/js/Components/Modal.vue');
        $dialogModal = $this->source('resources/js/Components/DialogModal.vue');
        $confirmationModal = $this->source('resources/js/Components/ConfirmationModal.vue');
        $confirmActionModal = $this->source('resources/js/Components/ConfirmActionModal.vue');

        $this->assertStringContainsString(':aria-label="resolvedAriaLabel"', $modal);
        $this->assertStringContainsString(':aria-labelledby="ariaLabelledby || undefined"', $modal);
        $this->assertStringContainsString(':aria-describedby="ariaDescribedby || undefined"', $modal);
        $this->assertStringContainsString('@cancel="handleCancel"', $modal);
        $this->assertStringContainsString('@close="restoreFocus"', $modal);
        $this->assertStringContainsString('window.requestAnimationFrame(() => focusTarget.focus())', $modal);
        $this->assertStringContainsString('onMounted(async () =>', $modal);

        foreach (['de:', 'en:', 'fr:', 'ar:'] as $locale) {
            $this->assertStringContainsString($locale, $modal);
        }

        $this->assertStringContainsString(':aria-labelledby="titleId"', $dialogModal);
        $this->assertStringContainsString(':id="titleId"', $dialogModal);
        $this->assertStringContainsString(':aria-labelledby="titleId"', $confirmationModal);
        $this->assertStringContainsString(':id="titleId"', $confirmationModal);
        $this->assertStringContainsString(':aria-labelledby="titleId"', $confirmActionModal);
        $this->assertStringContainsString(':aria-describedby="message ? descriptionId : undefined"', $confirmActionModal);
    }

    public function test_programmatic_dialog_traps_and_restores_focus_with_accessible_prompt_labels(): void
    {
        $service = $this->source('resources/js/services/dialogService.js');

        $this->assertStringContainsString("panel.setAttribute('aria-labelledby', titleId)", $service);
        $this->assertStringContainsString("panel.setAttribute('aria-describedby', messageId)", $service);
        $this->assertStringContainsString("input.setAttribute('aria-label', placeholder || copyText.input)", $service);
        $this->assertStringContainsString("input.setAttribute('aria-required', String(required))", $service);
        $this->assertStringContainsString("event.key !== 'Tab'", $service);
        $this->assertStringContainsString('lastElement.focus()', $service);
        $this->assertStringContainsString('firstElement.focus()', $service);
        $this->assertStringContainsString('window.requestAnimationFrame(() => previouslyFocused.focus())', $service);

        foreach (['de:', 'en:', 'fr:', 'ar:'] as $locale) {
            $this->assertStringContainsString($locale, $service);
        }
    }

    public function test_global_styles_cover_focus_motion_high_contrast_and_anchored_content(): void
    {
        $css = $this->source('resources/css/app.css');
        $button = $this->source('resources/js/Components/UI/AppButton.vue');

        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
        $this->assertStringContainsString('@media (forced-colors: active)', $css);
        $this->assertStringContainsString('scroll-margin-top: 6rem', $css);
        $this->assertStringContainsString('touch-action: manipulation', $css);

        foreach (['h-8', 'h-9', 'h-10', 'h-11'] as $minimumTargetClass) {
            $this->assertStringContainsString($minimumTargetClass, $button);
        }
    }

    public function test_vue_images_and_icon_only_buttons_keep_accessible_names(): void
    {
        $missingImageAlternatives = [];
        $unnamedIconButtons = [];
        $imageCount = 0;
        $iconButtonCount = 0;

        foreach ($this->vueFiles(resource_path('js')) as $file) {
            $source = file_get_contents($file->getPathname());
            $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());

            preg_match_all('/<img\b[^>]*>/is', $source, $imageTags);
            foreach ($imageTags[0] as $tag) {
                $imageCount++;
                if (! preg_match('/\b:?alt\s*=/i', $tag)) {
                    $missingImageAlternatives[] = $relativePath;
                }
            }

            preg_match_all('/<AppButton\b[^>]*>/is', $source, $buttonTags);
            foreach ($buttonTags[0] as $tag) {
                if (! preg_match('/\bicon-only\b/i', $tag)) {
                    continue;
                }

                $iconButtonCount++;
                if (! preg_match('/\baria-(?:label|labelledby)\s*=/i', $tag)) {
                    $unnamedIconButtons[] = $relativePath;
                }
            }
        }

        $this->assertGreaterThan(100, $imageCount);
        // Reusing the shared Modal close control intentionally reduces duplicate
        // page-level icon buttons while preserving one accessible implementation.
        $this->assertGreaterThanOrEqual(4, $iconButtonCount);
        $this->assertSame([], array_values(array_unique($missingImageAlternatives)), 'Images without alt: '.implode(', ', array_unique($missingImageAlternatives)));
        $this->assertSame([], array_values(array_unique($unnamedIconButtons)), 'Unnamed icon-only buttons: '.implode(', ', array_unique($unnamedIconButtons)));
    }

    public function test_all_custom_dialog_roles_are_modal_aware_and_named(): void
    {
        $invalidDialogs = [];
        $dialogCount = 0;

        foreach ($this->vueFiles(resource_path('js')) as $file) {
            $source = file_get_contents($file->getPathname());
            preg_match_all('/<[a-z][a-z0-9-]*\b[^>]*\brole\s*=\s*["\']dialog["\'][^>]*>/is', $source, $dialogTags);

            foreach ($dialogTags[0] as $tag) {
                $dialogCount++;
                $hasModality = preg_match('/\baria-modal\s*=/i', $tag) === 1;
                $hasName = preg_match('/\baria-(?:label|labelledby)\s*=/i', $tag) === 1;

                if (! $hasModality || ! $hasName) {
                    $invalidDialogs[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        $this->assertGreaterThanOrEqual(4, $dialogCount);
        $this->assertSame([], array_values(array_unique($invalidDialogs)), 'Unlabelled custom dialogs: '.implode(', ', array_unique($invalidDialogs)));
    }

    public function test_guest_page_buttons_never_depend_on_an_implicit_form_type(): void
    {
        $missingTypes = [];
        $buttonCount = 0;

        foreach ($this->vueFiles(resource_path('js/Pages/Guest')) as $file) {
            $source = file_get_contents($file->getPathname());
            preg_match_all('/<button\b[^>]*>/is', $source, $buttonTags);

            foreach ($buttonTags[0] as $tag) {
                $buttonCount++;
                if (! preg_match('/\btype\s*=/i', $tag)) {
                    $missingTypes[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        $this->assertGreaterThan(20, $buttonCount);
        $this->assertSame([], array_values(array_unique($missingTypes)), 'Guest buttons without explicit type: '.implode(', ', array_unique($missingTypes)));
    }

    public function test_guest_form_controls_have_programmatic_names(): void
    {
        $unnamedControls = [];
        $controlCount = 0;

        foreach ($this->vueFiles(resource_path('js/Pages/Guest')) as $file) {
            $source = file_get_contents($file->getPathname());
            preg_match_all('/<(?:input|select|textarea)\b[^>]*>/is', $source, $controlTags, PREG_OFFSET_CAPTURE);

            foreach ($controlTags[0] as [$tag, $offset]) {
                $controlCount++;

                if (preg_match('/\baria-(?:label|labelledby)\s*=/i', $tag)) {
                    continue;
                }

                if (preg_match('/\bid\s*=\s*["\']([^"\']+)["\']/i', $tag, $id)
                    && preg_match('/<label\b[^>]*\bfor\s*=\s*["\']'.preg_quote($id[1], '/').'["\']/i', $source)) {
                    continue;
                }

                $precedingSource = substr($source, 0, $offset);
                $lastOpeningLabel = strripos($precedingSource, '<label');
                $lastClosingLabel = strripos($precedingSource, '</label>');
                $isWrappedByLabel = $lastOpeningLabel !== false
                    && ($lastClosingLabel === false || $lastOpeningLabel > $lastClosingLabel);

                if (! $isWrappedByLabel) {
                    $unnamedControls[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }

        $this->assertGreaterThanOrEqual(70, $controlCount);
        $this->assertSame([], array_values(array_unique($unnamedControls)), 'Unnamed guest form controls: '.implode(', ', array_unique($unnamedControls)));
    }

    public function test_form_field_errors_are_announced_and_can_be_bound_to_controls(): void
    {
        $field = $this->source('resources/js/Components/UI/AppFormField.vue');

        $this->assertStringContainsString(':describedby="describedBy"', $field);
        $this->assertStringContainsString(':invalid="Boolean(error)"', $field);
        $this->assertStringContainsString('role="alert"', $field);
        $this->assertStringContainsString('aria-live="assertive"', $field);
    }

    public function test_friend_invitation_email_has_a_visible_name_and_bound_error(): void
    {
        $source = $this->source('resources/js/Pages/Auth/Dashboard/Friends/Index.vue');

        $this->assertStringContainsString('for="friend-invite-email"', $source);
        $this->assertStringContainsString('id="friend-invite-email"', $source);
        $this->assertStringContainsString(':aria-invalid="Boolean(inviteForm.errors.email)"', $source);
        $this->assertStringContainsString("'friend-invite-email-error'", $source);
        $this->assertStringContainsString('id="friend-invite-email-error" role="alert"', $source);
    }

    public function test_sport_matching_filters_and_tabs_have_programmatic_semantics(): void
    {
        $source = $this->source('resources/js/Pages/Auth/Dashboard/SportMatching/Index.vue');
        $searchableSelect = $this->source('resources/js/Components/SearchableSelect.vue');

        foreach (['sport-matching-filter-radius', 'sport-matching-filter-skill'] as $id) {
            $this->assertStringContainsString("for=\"{$id}\"", $source);
            $this->assertStringContainsString("id=\"{$id}\"", $source);
        }

        $this->assertStringContainsString('input-id="sport-matching-filter-sport"', $source);
        $this->assertStringContainsString(':aria-label="t(\'sport_matching.form.sport\')"', $source);
        $this->assertStringContainsString('role="tablist"', $source);
        $this->assertStringContainsString('role="tab"', $source);
        $this->assertStringContainsString(':aria-selected=', $source);
        $this->assertStringContainsString(':id="inputId || undefined"', $searchableSelect);
        $this->assertStringContainsString(':aria-label="ariaLabel || placeholder"', $searchableSelect);
    }

    public function test_settings_sections_are_exposed_as_selected_tabs(): void
    {
        $source = $this->source('resources/js/Pages/Auth/Dashboard/Settings/Index.vue');

        $this->assertStringContainsString('role="tablist"', $source);

        foreach (['profile', 'address', 'billing', 'roles', 'areas', 'activities', 'integrations', 'design', 'language', 'notifications', 'privacy', 'sport-profile', 'security'] as $tab) {
            $this->assertStringContainsString("role=\"tab\" :aria-selected=\"activeTab === '{$tab}'\"", $source);
        }
    }

    public function test_training_workspace_sections_use_tabs_and_named_panels(): void
    {
        $source = $this->source('resources/js/Pages/Auth/Dashboard/Training/Index.vue');

        $this->assertStringContainsString('role="tablist"', $source);
        $this->assertStringContainsString('role="tab"', $source);
        $this->assertStringContainsString(':aria-selected="activeTrainingSection === section.key"', $source);

        foreach (['plans', 'overview', 'logs', 'week', 'analysis'] as $section) {
            $this->assertStringContainsString("role=\"tabpanel\" aria-labelledby=\"training-section-tab-{$section}\"", $source);
        }
    }

    public function test_training_plan_audience_controls_expose_their_selected_state(): void
    {
        $modal = $this->source('resources/js/Components/Training/TrainingPlanCreateModal.vue');
        $workspace = $this->source('resources/js/composables/useTrainingWorkspace.js');

        foreach ([
            "planForm.target_type === 'self'",
            "planForm.target_type === 'private'",
            "planForm.target_type === 'team'",
            "planForm.team_mode === 'all'",
            "planForm.team_mode === 'individual'",
        ] as $state) {
            $this->assertStringContainsString(":aria-pressed=\"{$state}\"", $modal);
        }

        $this->assertStringContainsString("status: 'draft'", $workspace);
        $this->assertStringContainsString("planForm.status = 'draft'", $workspace);
    }

    public function test_learning_studio_course_and_lesson_controls_are_named(): void
    {
        $source = $this->source('resources/js/Pages/Auth/Dashboard/Learning/Studio.vue');

        foreach ([
            'learning_studio_form.category',
            'learning_studio_form.level',
            'learning_studio_form.section',
            'learning_studio_form.lesson_type',
            'learning_studio_form.duration',
            'learning_studio_form.position',
            'learning_studio_form.unlock_after',
            'learning_studio_form.status',
        ] as $key) {
            $this->assertStringContainsString(":aria-label=\"tx('{$key}'", $source);
        }

        $this->assertStringContainsString('role="tablist"', $source);
        $this->assertStringContainsString('role="tab"', $source);
        $this->assertStringContainsString(':aria-selected="activePanel === panel[0]"', $source);

        foreach (['structure', 'details', 'sales', 'quiz', 'assignments', 'students', 'questions'] as $panel) {
            $this->assertStringContainsString("id=\"learning-studio-panel-{$panel}\"", $source);
            $this->assertStringContainsString("aria-labelledby=\"learning-studio-tab-{$panel}\"", $source);
        }
    }

    public function test_learning_enrollment_action_exposes_its_busy_state(): void
    {
        $source = $this->source('resources/js/Pages/Guest/LearningCourseShow.vue');

        $this->assertStringContainsString('const enrolling = ref(false)', $source);
        $this->assertStringContainsString(':disabled="enrolling ||', $source);
        $this->assertStringContainsString(':aria-busy="enrolling"', $source);
        $this->assertStringContainsString('onStart: () => { enrolling.value = true }', $source);
        $this->assertStringContainsString('onFinish: () => { enrolling.value = false }', $source);
    }

    public function test_club_member_workspace_tabs_and_invitation_fields_are_named(): void
    {
        $source = $this->source('resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue');

        $this->assertStringContainsString('role="tablist"', $source);
        $this->assertStringContainsString('role="tab"', $source);
        $this->assertStringContainsString(':aria-selected="activeTab === tab.key"', $source);
        $this->assertStringContainsString('for="email-member-invitation-expires"', $source);
        $this->assertStringContainsString('id="email-member-invitation-expires"', $source);

        foreach (['name', 'email', 'membership-status', 'role', 'member-number', 'license-number', 'contribution', 'interval', 'next-invoice', 'sepa-iban', 'sepa-bic', 'mandate-reference', 'mandate-date', 'membership-end'] as $field) {
            $this->assertStringContainsString(":for=\"`email-member-\${index}-{$field}`\"", $source);
            $this->assertStringContainsString(":id=\"`email-member-\${index}-{$field}`\"", $source);
        }

        foreach (['membership-status', 'membership-type', 'member-number', 'license-number', 'contribution', 'interval', 'payment-method', 'next-invoice', 'sepa-iban', 'sepa-bic', 'mandate-reference', 'mandate-date', 'joined', 'membership-end', 'notes'] as $field) {
            $this->assertStringContainsString(":for=\"`club-member-\${member.id}-{$field}`\"", $source);
            $this->assertStringContainsString(":id=\"`club-member-\${member.id}-{$field}`\"", $source);
        }

        foreach (['invoiceForm.title', 'invoiceForm.amount', 'invoiceForm.due_date', 'invoiceForm.description'] as $model) {
            $this->assertMatchesRegularExpression('/v-model="'.preg_quote($model, '/').'"[^>]*:aria-label=/', $source);
        }
    }

    public function test_club_profile_tabs_and_role_selects_are_named(): void
    {
        $source = $this->source('resources/js/Pages/Auth/Dashboard/Teams/Index.vue');

        $this->assertStringContainsString('role="tablist"', $source);
        $this->assertStringContainsString('role="tab"', $source);
        $this->assertStringContainsString(':aria-selected="activeClubEditTab(club) === tab.key"', $source);
        $this->assertStringContainsString('role="tabpanel"', $source);
        $this->assertStringContainsString(":aria-label=\"`\${member.name}: \${tAuto('Teamrolle')}`\"", $source);
        $this->assertStringContainsString(":aria-label=\"`\${member.name}: \${tAuto('Vereinsrolle')}`\"", $source);
        $this->assertStringContainsString('{{ sportLabel(sport.slug || sport.name) }}', $source);
    }

    public function test_club_contribution_rule_controls_have_programmatic_names(): void
    {
        $source = $this->source('resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue');

        foreach (['membership_type', 'rule_name', 'amount_eur', 'rule_type', 'interval', 'valid_from', 'valid_until', 'age_min', 'age_max', 'notes'] as $key) {
            $this->assertStringContainsString("club_memberships.workspace.{$key}", $source);
        }
    }

    public function test_sponsor_campaign_objective_and_schedule_controls_have_programmatic_names(): void
    {
        $source = $this->source('resources/js/Pages/Auth/Dashboard/Commerce/Index.vue');

        foreach (['campaign-objective', 'campaign-starts-at', 'campaign-ends-at'] as $id) {
            $this->assertStringContainsString("for=\"{$id}\"", $source);
            $this->assertStringContainsString("id=\"{$id}\"", $source);
        }

        $this->assertStringContainsString(":aria-label=\"tAuto('Gesamtbudget in EUR')\"", $source);
        $this->assertStringContainsString('for="ad-group-placement"', $source);
        $this->assertStringContainsString('id="ad-group-placement"', $source);

        foreach (['Geschlecht', 'Mindestalter', 'Höchstalter', 'Start der Anzeigegruppe', 'Ende der Anzeigegruppe'] as $label) {
            $this->assertStringContainsString(":aria-label=\"tAuto('{$label}')\"", $source);
        }

        foreach (['Name', 'Gewicht', 'Aktiv', 'Headline', 'Anzeigentext', 'Beschreibung', 'Ziel-URL', 'Handlungsaufforderung', 'Bild-URL'] as $label) {
            $this->assertStringContainsString('Variante ${index + 1}: '.$label, $source);
            $this->assertStringContainsString('Bearbeiten – Variante ${index + 1}: '.$label, $source);
        }

        foreach (['edit-campaign-objective', 'edit-campaign-placement', 'edit-campaign-creative-format', 'edit-campaign-image-upload'] as $id) {
            $this->assertStringContainsString("for=\"{$id}\"", $source);
            $this->assertStringContainsString("id=\"{$id}\"", $source);
        }

        foreach (['Start der Kampagne bearbeiten', 'Ende der Kampagne bearbeiten', 'Mindestalter der Kampagne bearbeiten', 'Höchstalter der Kampagne bearbeiten'] as $label) {
            $this->assertStringContainsString(":aria-label=\"tAuto('{$label}')\"", $source);
        }

        foreach (['Standortart', 'Auszahlungsmethode'] as $label) {
            $this->assertStringContainsString(":aria-label=\"tAuto('{$label}')\"", $source);
        }
    }

    public function test_commerce_product_inventory_and_variant_controls_have_programmatic_names(): void
    {
        $source = $this->source('resources/js/Pages/Auth/Dashboard/Commerce/Index.vue');

        foreach (['Anbieter oder Verein', 'Produkttyp', 'Steuerklasse', 'Standortart', 'Auszahlungsmethode'] as $label) {
            $this->assertStringContainsString(":aria-label=\"tAuto('{$label}')\"", $source);
        }

        foreach (['product-main-image-url', 'product-main-image-upload', 'product-gallery-urls', 'product-gallery-upload', 'product-learning-course'] as $id) {
            $this->assertStringContainsString("for=\"{$id}\"", $source);
            $this->assertStringContainsString("id=\"{$id}\"", $source);
        }

        foreach (['Lagerland ${index + 1}', 'Lager ${index + 1}: Bestand', 'Lager ${index + 1}: Warnbestand', 'Merkmal ${index + 1}: Name', 'Merkmal ${index + 1}: Werte', 'Produktvariante ${index + 1}: Preis in EUR', 'Produktvariante ${index + 1}: Bestand', 'Produktvariante ${index + 1}: Artikelnummer', 'Produktvariante ${index + 1}: Bild-URL'] as $label) {
            $this->assertStringContainsString($label, $source);
        }

        foreach (['Bearbeiten – Lagerland ${index + 1}', 'Bearbeiten – Lager ${index + 1}: Bestand', 'Bearbeiten – Lager ${index + 1}: Warnbestand', 'Bearbeiten – Lager ${index + 1}: Lieferzeit in Tagen', 'Bearbeiten – Lager ${index + 1}: Stadt'] as $label) {
            $this->assertStringContainsString($label, $source);
        }
    }

    private function source(string $path): string
    {
        $source = file_get_contents(base_path($path));
        $this->assertIsString($source);

        return $source;
    }

    /** @return list<SplFileInfo> */
    private function vueFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'vue') {
                $files[] = $file;
            }
        }

        return $files;
    }
}
