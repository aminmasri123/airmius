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
