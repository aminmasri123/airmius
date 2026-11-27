<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotedPostDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_points_to_original_and_excludes_private_removed_and_organic_posts(): void
    {
        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = Post::create(['user_id' => $author->id, 'content' => 'Original', 'visibility' => 'public', 'moderation_status' => 'approved']);
        AdCampaign::create(['user_id' => $author->id, 'name' => 'Boost', 'objective' => 'awareness', 'placement' => 'feed', 'budget_cents' => 10000, 'status' => 'active', 'audience' => ['promoted_post_id' => $post->id]]);
        $this->actingAs($viewer);
        $this->getJson('/ads/active?placement=feed&defer_impression=1')->assertOk()->assertJsonPath('promoted_post_id', $post->id);
        $this->getJson('/ads/active?placement=feed&exclude_posts='.$post->id)->assertJsonMissingPath('id');
        $post->update(['visibility' => 'private']);
        $this->getJson('/ads/active?placement=feed')->assertJsonMissingPath('id');
        $post->update(['visibility' => 'public', 'moderation_status' => 'removed']);
        $this->getJson('/ads/active?placement=feed')->assertJsonMissingPath('id');
    }

    public function test_campaign_is_not_repeated_in_one_feed_session(): void
    {
        AdCampaign::create(['name' => 'Only campaign', 'objective' => 'traffic', 'placement' => 'feed', 'budget_cents' => 10000, 'status' => 'active']);
        $this->getJson('/ads/active?placement=feed&feed_session=test-session')->assertJsonStructure(['id']);
        $this->getJson('/ads/active?placement=feed&feed_session=test-session')->assertJsonMissingPath('id');
    }

    public function test_boost_requires_ownership_and_public_approved_post(): void
    {
        $owner = User::factory()->create();
        $post = Post::create(['user_id' => $owner->id, 'content' => 'Private', 'visibility' => 'private', 'moderation_status' => 'approved']);
        $body = ['name' => 'Boost', 'objective' => 'awareness', 'budget_cents' => 1000, 'promoted_post_id' => $post->id];
        $this->actingAs(User::factory()->create())->postJson('/api/v1/commerce/seller/campaigns', $body)->assertForbidden();
        $this->actingAs($owner)->postJson('/api/v1/commerce/seller/campaigns', $body)->assertUnprocessable();
        $post->update(['visibility' => 'public']);
        $this->postJson('/api/v1/commerce/seller/campaigns', $body)->assertCreated();
        $campaign = AdCampaign::sole();
        $this->assertSame($post->id, $campaign->audience['promoted_post_id']);
        $this->assertSame('draft', $campaign->status);
        $this->assertSame('feed', $campaign->placement);
        $this->assertDatabaseCount('posts', 1);
    }

    public function test_impression_requires_valid_viewer_bound_token_and_is_idempotent(): void
    {
        $viewer = User::factory()->create(['ads_measurement_consent' => true]);
        AdCampaign::create(['name' => 'Visible', 'objective' => 'traffic', 'placement' => 'feed', 'budget_cents' => 10000, 'status' => 'active']);
        $token = $this->actingAs($viewer)->getJson('/ads/active?placement=feed&defer_impression=1')->json('impression_token');
        $this->assertDatabaseCount('ad_events', 0);
        $this->postJson('/ads/impression', ['token' => 'forged'])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson('/ads/impression', ['token' => $token])->assertForbidden();
        $this->actingAs($viewer)->postJson('/ads/impression', ['token' => $token])->assertNoContent();
        $this->postJson('/ads/impression', ['token' => $token])->assertNoContent();
        $this->assertDatabaseCount('ad_events', 1);
    }
}
