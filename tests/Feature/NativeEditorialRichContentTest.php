<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use App\Support\BlogContentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class NativeEditorialRichContentTest extends TestCase
{
    use RefreshDatabase;

    private function editor(array $permissions = ['blog.view', 'blog.create', 'blog.update']): User
    {
        $user = User::factory()->create();
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        Sanctum::actingAs($user);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['title' => 'Native rich article', 'content' => '<p>Article</p>', 'content_format' => 'html', 'content_locale' => 'en', 'status' => 'draft'], $overrides);
    }

    public function test_web_semantic_html_round_trips_through_native_save_and_preview(): void
    {
        $this->editor();
        $html = '<p class="blog-lead" dir="rtl">مقدمة <strong>مهمة</strong></p><div class="blog-callout"><strong>Notice</strong><p><span class="blog-text-success">Good</span> <span class="blog-mark">mark</span></p></div><figure class="blog-image"><img src="https://example.test/photo.png" alt="A &amp; B"><figcaption>Caption <em>detail</em></figcaption></figure><h2>Heading</h2><ol><li>One<ul><li>Nested</li></ul></li></ol><pre><code>code &lt;tag&gt;</code></pre>';
        $created = $this->postJson('/api/v1/editorial/posts', $this->payload(['content' => $html]))->assertCreated();
        $id = $created->json('data.id');
        $sanitized = $created->json('data.content_html');
        $this->assertStringContainsString('blog-callout', $sanitized);
        $this->assertStringContainsString('<figcaption>Caption <em>detail</em></figcaption>', $sanitized);
        $this->assertStringContainsString('dir="rtl"', $sanitized);
        $this->putJson("/api/v1/editorial/posts/{$id}", $this->payload(['content' => $sanitized, 'title' => 'Metadata changed']))
            ->assertOk()->assertJsonPath('data.content_html', $sanitized);
        $this->assertSame($sanitized, BlogPost::findOrFail($id)->content);
        $this->postJson('/api/v1/editorial/preview', ['content' => $sanitized])
            ->assertOk()->assertJsonPath('data.content_html', $sanitized);
        $this->assertDatabaseCount('blog_posts', 1);
    }

    public function test_rich_input_and_preview_strip_executable_html_and_obfuscated_urls(): void
    {
        $this->editor();
        $html = '<p class="blog-lead evil" style=color:red onclick="evil()">Safe</p><script>evil()</script><img src="jav&#x09;ascript:evil()" onerror=evil() srcset="bad"><a href="data:text/html,bad" ping="bad">Bad</a><a href="https://example.test" target="_blank">Good</a><svg><script>bad()</script></svg><iframe srcdoc="bad"></iframe>';
        $expected = app(BlogContentSanitizer::class)->sanitize($html);
        foreach (['script', 'onclick', 'onerror', 'srcset', 'srcdoc', 'style=', 'data:', 'ping='] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $expected);
        }
        $this->assertStringContainsString('rel="noopener noreferrer"', $expected);
        $this->postJson('/api/v1/editorial/preview', ['content' => $html])->assertOk()->assertJsonPath('data.content_html', $expected);
        $this->postJson('/api/v1/editorial/posts', $this->payload(['content' => $html]))->assertCreated()->assertJsonPath('data.content_html', $expected);
        $this->postJson('/api/v1/editorial/posts', $this->payload(['content' => '<script>only unsafe</script>']))
            ->assertUnprocessable()->assertJsonValidationErrors('content');
    }

    public function test_inline_and_cover_uploads_return_safe_html_and_store_real_images(): void
    {
        Storage::fake('public');
        $this->editor();
        foreach (['inline', 'cover'] as $kind) {
            $response = $this->post('/api/v1/editorial/images', [
                'kind' => $kind, 'image' => UploadedFile::fake()->image('image.png', 80, 60),
                'alt' => '"><script>alert(1)</script>',
            ], ['Accept' => 'application/json'])->assertCreated();
            $this->assertStringContainsString($kind === 'cover' ? '/blog/covers/' : '/blog/content/', $response->json('data.url'));
            $this->assertStringNotContainsString('<script>', $response->json('data.content_html'));
            $this->assertStringContainsString('&lt;script&gt;', $response->json('data.content_html'));
        }
        $this->assertCount(2, Storage::disk('public')->allFiles('blog'));
    }

    public function test_image_upload_rejects_wrong_mime_oversize_files_and_invalid_fields(): void
    {
        Storage::fake('public');
        $this->editor();
        foreach (['inline', 'cover'] as $kind) {
            foreach ([UploadedFile::fake()->create('image.svg', 1, 'image/svg+xml'), UploadedFile::fake()->create('fake.png', 1, 'text/html'), UploadedFile::fake()->image('large.png')->size(8193)] as $file) {
                $this->post('/api/v1/editorial/images', ['kind' => $kind, 'image' => $file], ['Accept' => 'application/json'])
                    ->assertUnprocessable()->assertJsonValidationErrors('image');
            }
        }
        $this->postJson('/api/v1/editorial/images', ['kind' => 'other', 'alt' => str_repeat('x', 161)])
            ->assertUnprocessable()->assertJsonValidationErrors(['image', 'kind', 'alt']);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_upload_and_preview_require_authentication_and_editor_permissions(): void
    {
        $this->postJson('/api/v1/editorial/preview', ['content' => '<p>x</p>'])->assertUnauthorized();
        $this->postJson('/api/v1/editorial/images', ['kind' => 'cover'])->assertUnauthorized();
        $this->editor(['blog.view']);
        $this->postJson('/api/v1/editorial/preview', ['content' => '<p>x</p>'])->assertForbidden();
        foreach (['inline', 'cover'] as $kind) {
            $this->postJson('/api/v1/editorial/images', ['kind' => $kind])->assertForbidden();
        }
        $this->postJson('/api/v1/editorial/posts', $this->payload())->assertForbidden();
    }

    public function test_update_only_editor_can_preview_and_upload_but_cannot_create_or_publish(): void
    {
        Storage::fake('public');
        $this->editor(['blog.update']);
        $this->postJson('/api/v1/editorial/preview', ['content' => '<p>Draft</p>'])->assertOk();
        $this->post('/api/v1/editorial/images', ['kind' => 'inline', 'image' => UploadedFile::fake()->image('image.jpg')], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson('/api/v1/editorial/posts', $this->payload())->assertForbidden();
        $this->editor();
        $this->postJson('/api/v1/editorial/posts', $this->payload(['status' => 'published']))->assertForbidden();
    }

    public function test_locale_filter_translation_creation_and_related_category_search_match_web(): void
    {
        $this->editor();
        $category = BlogCategory::create(['name' => 'Searchable association', 'slug' => 'searchable-association', 'is_active' => true, 'sort_order' => 0]);
        $created = $this->postJson('/api/v1/editorial/posts', $this->payload(['blog_category_id' => $category->id]))->assertCreated();
        $id = $created->json('data.id');
        BlogPost::findOrFail($id)->update(['category' => 'Stale denormalized name']);
        $this->getJson('/api/v1/editorial/posts?q=Searchable%20association')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/editorial/posts', $this->payload(['translation_of_id' => $id, 'content_locale' => 'fr']))
            ->assertCreated()->assertJsonPath('data.content_locale', 'fr');
        $this->getJson('/api/v1/editorial/posts?content_locale=fr')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/editorial/posts', $this->payload(['translation_of_id' => $id, 'content_locale' => 'fr']))
            ->assertUnprocessable()->assertJsonValidationErrors('content_locale');
        $this->getJson('/api/v1/editorial/posts?content_locale=invalid')->assertUnprocessable();
    }
}
