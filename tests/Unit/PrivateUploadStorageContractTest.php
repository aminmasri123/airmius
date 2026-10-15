<?php

namespace Tests\Unit;

use App\Support\UploadStorage;
use PHPUnit\Framework\TestCase;

class PrivateUploadStorageContractTest extends TestCase
{
    public function test_private_paths_have_an_explicit_non_public_namespace(): void
    {
        $path = UploadStorage::privatePath('clubs/42/member-application.pdf');

        self::assertSame('private-files/clubs/42/member-application.pdf', $path);
        self::assertTrue(UploadStorage::isPrivate($path));
        self::assertFalse(UploadStorage::isPrivate('clubs/42/logo.webp'));
    }

    public function test_private_r2_disk_has_no_public_url_and_uses_private_visibility(): void
    {
        $config = file_get_contents(__DIR__.'/../../config/filesystems.php');

        self::assertIsString($config);
        self::assertStringContainsString("'r2_private' => [", $config);
        self::assertStringContainsString("'visibility' => 'private'", $config);
        self::assertStringNotContainsString("'r2_private' => [\n            'url'", $config);
    }

    public function test_sensitive_upload_services_select_private_storage(): void
    {
        foreach ([
            'app/Services/FileService.php',
            'app/Services/ChatService.php',
            'app/Services/ClubReceiptUploadService.php',
        ] as $relativePath) {
            $source = file_get_contents(__DIR__.'/../../'.$relativePath);

            self::assertIsString($source);
            self::assertStringContainsString('UploadStorage::private', $source, $relativePath);
        }
    }

    public function test_api_resources_use_authorized_preview_routes_for_private_files(): void
    {
        $resource = file_get_contents(__DIR__.'/../../app/Http/Resources/Api/V1/FileResource.php');

        self::assertIsString($resource);
        self::assertStringContainsString('UploadStorage::isPrivate($this->path)', $resource);
        self::assertStringContainsString("route('api.v1.files.preview'", $resource);
    }
}
