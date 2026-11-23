<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TariffAccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function plans(): iterable
    {
        foreach (['free', 'starter', 'club', 'pro', 'elite', 'sportler-free', 'sportler-pro', 'trainer-free', 'trainer-pro', 'eltern-free', 'sponsor-local', 'anbieter-marketplace', 'enterprise-verband'] as $slug) {
            yield $slug => [$slug];
        }
    }

    #[DataProvider('plans')]
    public function test_basic_workspaces_are_available_to_verified_members(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $this->actingAs($user);
        foreach (['auth.conversations.index', 'auth.badges.index', 'auth.feed.index', 'auth.notifications.index', 'auth.files.index', 'guest.marketplace'] as $route) {
            $this->get(route($route))->assertOk();
        }
        foreach (['chat/conversations', 'badges', 'feed', 'notifications', 'files', 'commerce/products'] as $endpoint) {
            $this->getJson('/api/v1/'.$endpoint)->assertOk();
        }
    }

    #[DataProvider('plans')]
    public function test_personal_posts_comments_and_folders_can_be_created_edited_and_deleted(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $this->actingAs($user);
        foreach (['web', 'api'] as $surface) {
            $api = $surface === 'api';
            $prefix = $api ? '/api/v1' : '';
            $postData = ['visibility' => 'public', 'post_type' => 'normal', 'content_origin' => 'self', 'content' => 'Mein Training heute'];
            $send = function (string $method, string $uri, array $data = []) use ($api, $prefix) {
                $response = $api ? $this->json($method, $prefix.$uri, $data) : $this->call($method, $prefix.$uri, $data);
                if ($api) {
                    $response->assertSuccessful();
                } else {
                    $response->assertRedirect()->assertSessionHasNoErrors();
                }
            };

            $folders = $api ? '/files/folders' : '/folders';
            $send('POST', $folders, ['scope' => 'user', 'name' => 'Training']);
            $folder = \App\Models\Folder::where('user_id', $user->id)->where('name', 'Training')->sole();
            $send($api ? 'PATCH' : 'PUT', $folders.'/'.$folder->id, ['name' => 'Trainingsarchiv']);
            $this->assertSame('Trainingsarchiv', $folder->fresh()->name);
            $send('DELETE', $folders.'/'.$folder->id);
            $this->assertDatabaseMissing('folders', ['id' => $folder->id]);

            $send('POST', $api ? '/feed' : '/posts', $postData);
            $post = \App\Models\Post::where('user_id', $user->id)->where('content', $postData['content'])->sole();
            $postData['content'] = 'Mein aktualisiertes Training';
            $send('PUT', '/posts/'.$post->id, $postData);
            $this->assertSame($postData['content'], $post->fresh()->content);

            $send('POST', '/posts/'.$post->id.'/comments', ['content' => 'Meine Trainingsnotiz']);
            $comment = $post->comments()->where('user_id', $user->id)->where('content', 'Meine Trainingsnotiz')->sole();
            $send('PUT', '/comments/'.$comment->id, ['content' => 'Meine neue Notiz']);
            $this->assertSame('Meine neue Notiz', $comment->fresh()->content);
            $send('DELETE', '/comments/'.$comment->id);
            $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
            $send('DELETE', '/posts/'.$post->id);
            $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        }
    }

    #[DataProvider('plans')]
    public function test_shared_folders_and_foreign_content_rules(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $club = $user->clubs()->first() ?? Club::factory()->create();
        $club->users()->syncWithoutDetaching([$user->id => [
            'role' => 'member', 'membership_status' => 'active',
            'permission_overrides' => ['files.view' => true, 'files.edit' => true, 'files.delete' => true],
        ]]);
        $team = \App\Models\Team::factory()->create(['club_id' => $club->id]);
        $this->actingAs($user);

        foreach (['club' => $club->id, 'team' => $team->id] as $scope => $id) {
            foreach (['web', 'api'] as $surface) {
                $payload = ['scope' => $scope, $scope.'_id' => $id, 'name' => 'Trainingsnotizen'];
                if ($surface === 'api') {
                    $this->postJson('/api/v1/files/folders', $payload)->assertCreated();
                } else {
                    $this->post(route('auth.folders.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
                }
                $folder = \App\Models\Folder::where('club_id', $club->id)
                    ->where('team_id', $scope === 'team' ? $id : null)->where('name', $payload['name'])->sole();
                $updated = ['name' => 'Saisonnotizen'];
                if ($surface === 'api') {
                    $this->patchJson('/api/v1/files/folders/'.$folder->id, $updated)->assertOk();
                } else {
                    $this->put(route('auth.folders.update', $folder), $updated)->assertRedirect()->assertSessionHasNoErrors();
                }
                $this->assertSame($updated['name'], $folder->fresh()->name);
                if ($surface === 'api') {
                    $this->deleteJson('/api/v1/files/folders/'.$folder->id)->assertOk();
                } else {
                    $this->delete(route('auth.folders.destroy', $folder))->assertRedirect()->assertSessionHasNoErrors();
                }
                $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
            }
        }

        $author = User::factory()->create();
        $post = \App\Models\Post::create(['user_id' => $author->id, 'content' => 'Testbeitrag', 'visibility' => 'public', 'post_type' => 'normal', 'content_origin' => 'self', 'moderation_status' => 'approved']);
        $comment = $post->comments()->create(['user_id' => $author->id, 'content' => 'Testkommentar']);
        $privateFolder = \App\Models\Folder::create(['user_id' => $author->id, 'name' => 'Persönlich']);
        foreach (['posts' => $post, 'comments' => $comment, 'folders' => $privateFolder] as $collection => $record) {
            $web = '/'.$collection.'/'.$record->id;
            $api = '/api/v1'.($collection === 'folders' ? '/files' : '').$web;
            $data = $collection === 'folders' ? ['name' => 'Änderung'] : ['content' => 'Änderung'];
            $original = $record->fresh()->getAttributes();
            $this->putJson($web, $data)->assertForbidden();
            $this->deleteJson($web)->assertForbidden();
            $this->json($collection === 'folders' ? 'PATCH' : 'PUT', $api, $data)->assertForbidden();
            $this->deleteJson($api)->assertForbidden();
            $this->assertSame($original, $record->fresh()->getAttributes());
        }
        foreach ([$post, $comment, $privateFolder] as $foreign) {
            $this->assertFalse($user->can('update', $foreign));
            $this->assertFalse($user->can('delete', $foreign));
        }
    }

    #[DataProvider('plans')]
    public function test_file_access_for_scoped_roles(string $slug): void
    {
        \Illuminate\Support\Facades\Storage::fake(\App\Support\UploadStorage::disk());
        \Illuminate\Support\Facades\Storage::fake(\App\Support\UploadStorage::privateDisk());
        $user = $this->accountForPlan($slug);
        $club = $user->clubs()->first() ?? Club::factory()->create();
        $team = \App\Models\Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($user, ['role' => 'member']);
        $this->actingAs($user);

        foreach (['view', 'edit', 'export', 'share'] as $role) {
            $this->travel(2)->minutes();
            $rights = ['files.view' => true, 'files.edit' => false, 'files.export' => false, 'files.share' => false];
            $rights['files.'.$role] = true;
            $club->users()->syncWithoutDetaching([$user->id => [
                'role' => 'member', 'membership_status' => 'active', 'permission_overrides' => $rights,
            ]]);
            $this->actingAs($user->fresh());

            foreach (['club' => $club->id, 'team' => $team->id] as $scope => $id) {
                foreach (['web', 'api'] as $surface) {
                    $api = $surface === 'api';
                    $name = "$slug-$role-$scope-$surface.pdf";
                    $upload = ['scope' => $scope, $scope.'_id' => $id,
                        'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent($name, "%PDF-1.4\nTestinhalt\n")];
                    $response = $this->postJson($api ? '/api/v1/uploads' : route('auth.files.store'), $upload);
                    $query = \App\Models\File::where('display_name', $name)->where('club_id', $club->id);
                    if ($role === 'edit') {
                        if ($api) {
                            $response->assertCreated();
                        } else {
                            $response->assertRedirect()->assertSessionHasNoErrors();
                        }
                        $file = $query->sole();
                    } else {
                        $response->assertForbidden();
                        $this->assertFalse($query->exists());
                        $file = \App\Models\File::create(['user_id' => $club->owner_id, 'club_id' => $club->id,
                            'team_id' => $scope === 'team' ? $team->id : null, 'display_name' => $name,
                            'path' => 'tarif-test/'.$name, 'type' => 'application/pdf', 'size' => 9]);
                        \Illuminate\Support\Facades\Storage::disk(\App\Support\UploadStorage::disk($file->path))->put($file->path, 'Testinhalt');
                    }

                    if ($api) {
                        $preview = $this->get('/api/v1/files/'.$file->id.'/preview')->assertOk();
                        $expected = $role === 'edit' ? "%PDF-1.4\nTestinhalt\n" : 'Testinhalt';
                        $this->assertSame($expected, $preview->streamedContent());
                    } else {
                        $download = $this->getJson(route('auth.files.download', $file));
                        if ($role === 'export') {
                            $download->assertOk()->assertDownload($name);
                            $this->assertSame('Testinhalt', $download->streamedContent());
                        } else {
                            $download->assertForbidden();
                        }
                    }

                    if ($api) {
                        $endpoint = '/api/v1/uploads/'.$file->id.'/public-share';
                        $link = $this->postJson($endpoint, ['expires_in_days' => 1]);
                        if ($role === 'share') {
                            $link->assertCreated();
                            $url = $link->json('data.url');
                            $laterUrl = $this->postJson($endpoint)->assertCreated()->json('data.url');
                            $otherFile = $file->replicate();
                            $otherFile->display_name = 'Andere-Datei.pdf';
                            $otherFile->save();
                            $otherEndpoint = "/api/v1/uploads/{$otherFile->id}/public-share";
                            $otherUrl = $this->postJson($otherEndpoint)->assertCreated()->json('data.url');
                            $colleague = User::factory()->create();
                            $club->users()->attach($colleague, ['role' => 'member', 'membership_status' => 'active',
                                'permission_overrides' => $rights]);
                            $team->users()->attach($colleague, ['role' => 'member']);
                            $colleagueUrl = $this->actingAs($colleague)->postJson($endpoint)->assertCreated()->json('data.url');
                            auth()->forgetGuards();
                            auth()->shouldUse('web');
                            $this->assertGuest();
                            $publicDownload = $this->get($url)->assertOk()->assertDownload($name);
                            $this->assertSame('Testinhalt', $publicDownload->streamedContent());
                            $this->travelTo(now()->addHours(25), function () use ($url) {
                                $expired = $this->get($url)->assertStatus(410);
                                $this->assertNull($expired->headers->get('Content-Disposition'));
                                $this->assertFalse(str_starts_with(
                                    (string) $expired->headers->get('Content-Type'),
                                    'application/pdf',
                                ));
                            });
                            $this->actingAs($user->fresh());
                            $this->assertDatabaseHas('file_shares', ['file_id' => $file->id, 'email' => 'public-link']);
                            $this->deleteJson($endpoint)->assertOk()->assertJsonPath('data.count', 2);
                            $this->deleteJson($endpoint)->assertOk()->assertJsonPath('data.count', 0);
                            auth()->forgetGuards();
                            auth()->shouldUse('web');
                            $this->assertGuest();
                            $this->get($url)->assertNotFound();
                            $this->get($laterUrl)->assertNotFound();
                            $this->get($otherUrl)->assertOk()->assertDownload('Andere-Datei.pdf');
                            $this->assertSame('Testinhalt', $this->get($colleagueUrl)->assertOk()->assertDownload($name)->streamedContent());
                            $this->actingAs($colleague)->deleteJson($endpoint)->assertOk()->assertJsonPath('data.count', 1);
                            $this->actingAs($user->fresh());
                        } else {
                            $link->assertForbidden();
                            $this->deleteJson($endpoint)->assertForbidden();
                        }
                        $this->assertDatabaseMissing('file_shares', ['file_id' => $file->id, 'email' => 'public-link']);
                    }
                    $friend = User::factory()->create();
                    \App\Models\Friendship::create(['user_id' => $user->id, 'friend_id' => $friend->id]);
                    $share = $api
                        ? $this->postJson('/api/v1/uploads/'.$file->id.'/share', ['target_user_id' => $friend->id])
                        : $this->postJson(route('auth.files.share', $file), ['target_type' => 'user', 'target_id' => $friend->id]);
                    $copy = ['user_id' => $friend->id, 'club_id' => null, 'path' => $file->path];
                    if ($role === 'share') {
                        $api ? $share->assertSuccessful() : $share->assertRedirect();
                        $this->assertDatabaseHas('files', $copy);
                        $received = \App\Models\File::where($copy)->sole();
                        $publicShares = "/api/v1/uploads/{$file->id}/public-share";
                        $publicUrl = $this->postJson($publicShares)->assertCreated()->json('data.url');
                        $this->deleteJson($publicShares)->assertOk()->assertJsonPath('data.count', 1);
                        auth()->forgetGuards();
                        auth()->shouldUse('web');
                        $this->assertGuest();
                        $this->get($publicUrl)->assertNotFound();
                        $this->actingAs($friend);
                        $this->getJson('/api/v1/files?scope=user')->assertOk()->assertJsonPath('data.files.0.id', $received->id);
                        $preview = $this->get('/api/v1/files/'.$received->id.'/preview')->assertOk();
                        $this->assertSame('Testinhalt', $preview->streamedContent());
                        $download = $this->get(route('auth.files.download', $received))->assertOk()->assertDownload($name);
                        $this->assertSame('Testinhalt', $download->streamedContent());
                        $newName = 'Meine-Kopie.pdf';
                        $rename = ['display_name' => $newName];
                        if ($api) {
                            $this->patchJson("/api/v1/uploads/{$received->id}", $rename)->assertOk();
                        } else {
                            $this->put(route('auth.files.update', $received), $rename)->assertRedirect()->assertSessionHasNoErrors();
                        }
                        $this->assertSame($name, $file->fresh()->display_name);
                        $this->assertSame($newName, $received->fresh()->display_name);
                        $this->assertSame('Testinhalt', $this->get(route('auth.files.download', $received))
                            ->assertOk()->assertDownload($newName)->streamedContent());
                        if ($api) {
                            $this->deleteJson("/api/v1/uploads/{$received->id}")->assertOk()->assertJsonPath('data.deleted', true);
                        } else {
                            $this->delete(route('auth.files.destroy', $received))->assertRedirect()->assertSessionHasNoErrors();
                        }
                        $this->assertNull($received->fresh());
                        $this->actingAs($user->fresh());
                        $this->assertSame('Testinhalt', $this->get("/api/v1/files/{$file->id}/preview")
                            ->assertOk()->streamedContent());
                    } else {
                        $share->assertForbidden();
                        $this->assertDatabaseMissing('files', $copy);
                    }
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_trainer_cockpit_access_by_role(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $this->actingAs($user);
        $this->get(route('auth.trainer-cockpit.index'))->assertForbidden();
        $this->getJson('/api/v1/trainer-cockpit')->assertForbidden();

        $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('coach', 'web'));
        $this->actingAs($user->fresh());
        $this->get(route('auth.trainer-cockpit.index'))->assertOk();
        $this->getJson('/api/v1/trainer-cockpit')->assertOk()->assertJsonStructure(['data']);
    }

    #[DataProvider('plans')]
    public function test_personal_dashboard_and_notifications(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $this->actingAs($user);
        $this->get(route('auth.dashboard'))->assertOk();
        $this->getJson('/api/v1/dashboard/daily-flow')->assertOk()->assertJsonStructure(['data' => ['attention']]);
        $other = User::factory()->create();
        $foreign = \App\Models\Notification::create(['user_id' => $other->id, 'type' => 'event.reminder', 'read' => false, 'data' => ['title' => 'Termin']]);
        foreach (['web', 'api'] as $surface) {
            $own = \App\Models\Notification::create(['user_id' => $user->id, 'type' => 'event.reminder', 'read' => false, 'data' => ['title' => 'Mein Termin']]);
            foreach (['read' => true, 'unread' => false, 'read-all' => true] as $action => $expected) {
                if ($surface === 'web') {
                    $this->post(route('auth.notifications.'.$action, $action === 'read-all' ? [] : [$own]))->assertRedirect()->assertSessionHasNoErrors();
                } else {
                    $path = $action === 'read-all' ? $action : $own->id.'/'.$action;
                    $this->postJson('/api/v1/notifications/'.$path)->assertOk();
                }
                $this->assertSame($expected, $own->fresh()->read);
                $this->assertFalse($foreign->fresh()->read);
            }
            if ($surface === 'web') {
                $this->delete(route('auth.notifications.destroy', $own))->assertRedirect()->assertSessionHasNoErrors();
                $this->deleteJson(route('auth.notifications.destroy', $foreign))->assertForbidden();
            } else {
                $this->deleteJson('/api/v1/notifications/'.$own->id)->assertOk();
                $this->deleteJson('/api/v1/notifications/'.$foreign->id)->assertNotFound();
            }
            $this->assertDatabaseMissing('notifications', ['id' => $own->id]);
            $this->assertDatabaseHas('notifications', ['id' => $foreign->id]);
        }
    }

    #[DataProvider('plans')]
    public function test_sponsor_deliverables_in_club_with_separate_edit_and_delete_rights(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $club = $user->clubs()->first() ?? Club::factory()->create();
        $sponsor = \App\Models\Sponsor::create(['name' => 'Testpartner', 'scope' => 'club', 'club_id' => $club->id]);
        foreach (['auth.sponsor-workspace', 'api.v1.sponsor-management'] as $workspace) {
            $url = route($workspace.'.deliverables.store', $sponsor);
            $payload = ['title' => 'Bandenwerbung', 'status' => 'planned'];
            $setRights = function (bool $edit, bool $delete) use ($user, $club) {
                $club->users()->syncWithoutDetaching([$user->id => [
                    'role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['sponsors.edit' => $edit, 'sponsors.delete' => $delete],
                ]]);
                $this->actingAs($user->fresh());
            };
            $setRights(true, false);
            $id = $this->postJson($url, $payload)->assertCreated()->json('data.id');
            $params = [$sponsor, $id];
            $update = route($workspace.'.deliverables.update', $params);
            $delete = route($workspace.'.deliverables.destroy', $params);
            $payload['title'] = 'Logo im Vereinsheft';
            $this->putJson($update, $payload)->assertOk();
            $this->assertDatabaseHas('sponsor_deliverables', ['id' => $id, 'title' => $payload['title'], 'club_id' => $club->id]);
            $completed = array_merge($payload, ['status' => 'fulfilled', 'responsible_user_id' => $user->id]);
            $this->putJson($update, $completed)->assertUnprocessable()->assertJsonValidationErrors('fulfillment_evidence');
            $this->assertDatabaseHas('sponsor_deliverables', ['id' => $id, 'status' => 'planned', 'fulfilled_at' => null]);
            $completed['fulfillment_evidence'] = 'Logo in der November-Ausgabe abgedruckt.';
            $this->putJson($update, $completed)->assertOk()->assertJsonPath('data.fulfilled_by', $user->id);
            $this->assertDatabaseHas('sponsor_deliverables', ['id' => $id, 'status' => 'fulfilled',
                'fulfillment_evidence' => $completed['fulfillment_evidence'], 'responsible_user_id' => $user->id]);
            $this->deleteJson($delete)->assertForbidden();
            $setRights(false, true);
            $this->postJson($url, $payload)->assertForbidden();
            $this->putJson($update, $payload)->assertForbidden();
            $this->deleteJson($delete)->assertOk();
            $this->assertDatabaseMissing('sponsor_deliverables', ['id' => $id]);
        }
    }

    #[DataProvider('plans')]
    public function test_club_sponsor_management_lists(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $club = $user->clubs()->first() ?? Club::factory()->create();
        $own = \App\Models\Sponsor::create(['club_id' => $club->id, 'scope' => 'club', 'name' => 'Vereinspartner']);
        $foreign = \App\Models\Sponsor::create(['club_id' => Club::factory()->create()->id, 'scope' => 'club', 'name' => 'Anderer Vereinspartner']);
        foreach (['edit', 'delete', 'none'] as $right) {
            $club->users()->syncWithoutDetaching([$user->id => [
                'role' => 'member', 'membership_status' => 'active',
                'permission_overrides' => ['sponsors.edit' => $right === 'edit', 'sponsors.delete' => $right === 'delete'],
            ]]);
            $this->actingAs($user->fresh());
            $list = $this->getJson('/api/v1/sponsor-management');
            if ($right === 'none') {
                $list->assertForbidden();
            } else {
                $list->assertOk()->assertJsonPath('data.0.id', $own->id)
                    ->assertJsonPath('data.0.can_edit', $right === 'edit')
                    ->assertJsonPath('data.0.can_delete', $right === 'delete')
                    ->assertJsonMissing(['name' => $foreign->name]);
            }
            $this->getJson(route('sponsors.index'))->assertForbidden();
        }
    }

    #[DataProvider('plans')]
    public function test_club_sponsor_crud_by_club_plan(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $existing = $user->clubs()->first();
        foreach ($existing ? [$slug] : ['free', 'club'] as $clubSlug) {
            $club = $existing ?? Club::factory()->create();
            $club->currentSubscription()->updateOrCreate([], [
                'subscription_plan_id' => SubscriptionPlan::where('slug', $clubSlug)->firstOrFail()->id,
                'status' => 'active', 'billing_interval' => 'monthly',
            ]);
            $club->users()->syncWithoutDetaching([$user->id => [
                'role' => 'member', 'membership_status' => 'active',
                'permission_overrides' => ['sponsors.edit' => true, 'sponsors.delete' => true],
            ]]);
            $this->actingAs($user->fresh());
            $included = in_array($clubSlug, ['club', 'pro', 'elite'], true);
            foreach (['web', 'api'] as $surface) {
                $api = $surface === 'api';
                $url = $api ? '/api/v1/sponsor-management' : route('auth.clubs.sponsors.store', $club);
                $payload = ['scope' => 'club', 'club_id' => $club->id, 'name' => 'Testpartner '.$surface];
                $created = $this->postJson($url, $payload);
                if ($included) {
                    $api ? $created->assertCreated() : $created->assertRedirect()->assertSessionHasNoErrors();
                    $sponsor = $club->sponsors()->where('name', $payload['name'])->sole();
                } else {
                    $created->assertUnprocessable()->assertJsonValidationErrors('general');
                    $this->assertFalse($club->sponsors()->where('name', $payload['name'])->exists());
                    $sponsor = $club->sponsors()->create($payload);
                }
                $item = $api ? $url.'/'.$sponsor->id : route('auth.clubs.sponsors.update', [$club, $sponsor]);
                $payload['name'] .= ' aktualisiert';
                $payload['contact_name'] = 'Mara Muster';
                $payload['email'] = 'kontakt@example.test';
                $payload['renewal_notice_days'] = 45;
                $updated = $this->putJson($item, $payload);
                if ($included) {
                    $api ? $updated->assertOk() : $updated->assertRedirect()->assertSessionHasNoErrors();
                    $this->assertSame($payload['name'], $sponsor->fresh()->name);
                    $this->assertDatabaseHas('sponsors', ['id' => $sponsor->id,
                        'contact_name' => 'Mara Muster', 'email' => 'kontakt@example.test',
                        'renewal_notice_days' => $api ? 45 : $sponsor->renewal_notice_days]);
                } else {
                    $updated->assertUnprocessable()->assertJsonValidationErrors('general');
                    $this->assertSame($sponsor->name, $sponsor->fresh()->name);
                    $this->assertDatabaseMissing('sponsors', ['id' => $sponsor->id, 'email' => 'kontakt@example.test']);
                }
                $approval = $payload + ['contract_approval_status' => 'approved'];
                if ($included && $api) {
                    $this->putJson($item, $approval)->assertForbidden();
                    $rights = ['sponsors.edit' => true, 'finance.approve' => true, 'sponsors.delete' => true];
                    $membership = ['role' => 'member', 'membership_status' => 'active', 'permission_overrides' => $rights];
                    $club->users()->syncWithoutDetaching([$user->id => $membership]);
                    $this->actingAs($user->fresh())->putJson($item, $approval)->assertUnprocessable();
                    $this->assertNull($sponsor->fresh()->contract_approved_by);
                    $reviewer = User::factory()->create();
                    $club->users()->attach($reviewer, $membership);
                    $this->actingAs($reviewer)->putJson($item, $approval)->assertOk();
                    $this->assertDatabaseHas('sponsors', ['id' => $sponsor->id, 'contract_approval_status' => 'approved',
                        'contract_approved_by' => $reviewer->id, 'contract_submitted_by' => $user->id]);
                    $this->actingAs($user->fresh());
                } elseif ($included) {
                    $this->putJson($item, $approval)->assertRedirect()->assertSessionHasNoErrors();
                    $this->assertNotSame('approved', $sponsor->fresh()->contract_approval_status);
                } else {
                    $this->putJson($item, $approval)->assertUnprocessable()->assertJsonValidationErrors('general');
                }
                if (! $api) {
                    $sponsor->update(['contract_approval_status' => 'pending_review', 'contract_submitted_by' => $user->id]);
                    $reviewer = User::factory()->create();
                    $club->users()->attach($reviewer, ['role' => 'member', 'membership_status' => 'active',
                        'permission_overrides' => ['sponsors.edit' => true, 'finance.approve' => true]]);
                    $approval['contact_name'] = 'Kontakt aus zweiter Prüfung';
                    $result = $this->actingAs($reviewer)->putJson($item, $approval);
                    if ($included) {
                        $result->assertRedirect()->assertSessionHasNoErrors();
                        $this->assertSame($approval['contact_name'], $sponsor->fresh()->contact_name);
                    } else {
                        $result->assertUnprocessable()->assertJsonValidationErrors('general');
                    }
                    $this->assertDatabaseHas('sponsors', ['id' => $sponsor->id, 'contract_approval_status' => 'pending_review',
                        'contract_submitted_by' => $user->id, 'contract_approved_by' => null, 'contract_approved_at' => null]);
                    $this->actingAs($user->fresh());
                }
                $deleted = $this->deleteJson($item);
                if ($api || $included) {
                    $api ? $deleted->assertOk() : $deleted->assertRedirect()->assertSessionHasNoErrors();
                    $this->assertDatabaseMissing('sponsors', ['id' => $sponsor->id]);
                } else {
                    $deleted->assertUnprocessable()->assertJsonValidationErrors('general');
                    $this->assertDatabaseHas('sponsors', ['id' => $sponsor->id]);
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_club_file_listing_respects_scope_and_view_right(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $club = $user->clubs()->first() ?? Club::factory()->create();
        $other = Club::factory()->create();
        foreach ([$club, $other] as $source) {
            $sourceFile = \App\Models\File::create([
                'club_id' => $source->id, 'user_id' => $source->owner_id, 'display_name' => 'Vereinsordnung.pdf',
                'path' => 'tarif-test/'.$source->id.'/ordnung.pdf', 'type' => 'application/pdf', 'size' => 9,
            ]);
            if ($source->is($club)) {
                $ownFile = $sourceFile;
            }
        }
        foreach ([true, false] as $view) {
            $club->users()->syncWithoutDetaching([$user->id => [
                'role' => 'member', 'membership_status' => 'active', 'permission_overrides' => ['files.view' => $view],
            ]]);
            $this->actingAs($user->fresh());
            $params = ['scope' => 'club', 'club_id' => $club->id];
            $web = $this->get(route('auth.files.index', $params));
            $api = $this->getJson('/api/v1/files?'.http_build_query($params));
            if ($view) {
                $web->assertOk()->assertInertia(fn ($page) => $page->has('files.data', 1)->where('files.data.0.id', $ownFile->id));
                $api->assertOk()->assertJsonCount(1, 'data.files')->assertJsonPath('data.files.0.id', $ownFile->id);
            } else {
                $web->assertNotFound();
                $api->assertNotFound();
            }
        }
    }

    #[DataProvider('plans')]
    public function test_sport_places_on_map_and_ownership(string $slug): void
    {
        $member = $this->accountForPlan($slug);
        $other = User::factory()->create();
        foreach (['web', 'api'] as $surface) {
            $this->actingAs($member);
            $api = $surface === 'api';
            $url = $api ? '/api/v1/sport-places' : route('auth.sport-places.store');
            $data = ['name' => 'Testbahn '.$surface, 'type' => 'running_track', 'latitude' => 49.1, 'longitude' => 7.1,
                'address' => 'Teststraße 1', 'postal_code' => '00123', 'city' => 'Teststadt', 'country_code' => 'DE'];
            $created = $this->postJson($url, $data);
            $api ? $created->assertCreated() : $created->assertRedirect()->assertSessionHasNoErrors();
            $place = \App\Models\SportPlace::where('name', $data['name'])->where('user_id', $member->id)->sole();
            $this->get(route('auth.sport-map.index'))->assertOk()->assertInertia(fn ($page) => $page->where('places.0.id', $place->id));
            $this->getJson('/api/v1/sport-places?type=running_track')->assertOk()->assertJsonPath('data.0.id', $place->id);
            $item = $url.'/'.$place->id;
            $data['name'] .= ' neu';
            $this->actingAs($other);
            ($api ? $this->patchJson($item, $data) : $this->putJson($item, $data))->assertForbidden();
            $this->deleteJson($item)->assertForbidden();
            $this->assertSame($place->name, $place->fresh()->name);
            $this->actingAs($member);
            $api ? $this->patchJson($item, $data)->assertOk() : $this->putJson($item, $data)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($data['name'], $place->fresh()->name);
            $deleted = $this->deleteJson($item);
            $api ? $deleted->assertOk() : $deleted->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSoftDeleted($place);
        }
    }

    #[DataProvider('plans')]
    public function test_training_log_by_owner_and_status(string $slug): void
    {
        $owner = $this->accountForPlan($slug);
        $other = User::factory()->create();
        foreach (['web', 'api'] as $surface) {
            foreach (($surface === 'web' ? ['draft', 'completed'] : ['planned', 'completed']) as $status) {
                $api = $surface === 'api';
                $this->actingAs($owner);
                $data = ['title' => "Training $surface $status", 'sport_type' => 'laufen', 'status' => $status,
                    'performed_at' => now()->toIso8601String(), 'privacy_scope' => 'private', 'notify_people' => false];
                $collection = $api ? '/api/v1/training/logs' : route('auth.training.logs.store');
                if ($status === 'draft') {
                    $this->get(route('auth.training.logs.create'))->assertOk();
                    $log = \App\Models\TrainingLog::where('created_by', $owner->id)->where('status', 'draft')->sole();
                    $data['status'] = 'planned';
                } else {
                    $create = $this->postJson($collection, $data);
                    $api ? $create->assertCreated() : $create->assertRedirect()->assertSessionHasNoErrors();
                    $log = \App\Models\TrainingLog::where('user_id', $owner->id)->where('title', $data['title'])->sole();
                }
                $this->assertSame($status, $log->status);
                $details = $api ? $collection.'/'.$log->id : route('auth.training.logs.show', $log);
                $this->get($details)->assertOk();
                $item = $api ? $collection.'/'.$log->id : route('auth.training.logs.draft.update', $log);
                $data['title'] .= ' korrigiert';
                $this->actingAs($other);
                $this->get($details)->assertStatus($api ? 404 : 403);
                $this->putJson($item, $data)->assertForbidden();
                $this->deleteJson($item)->assertForbidden();
                $this->actingAs($owner);
                if (! $api && $status === 'completed') {
                    $this->putJson($item, $data)->assertForbidden();
                    $this->deleteJson($item)->assertForbidden();
                    $this->assertSame($log->title, $log->fresh()->title);
                    continue;
                }
                $update = $this->putJson($item, $data);
                $update->assertOk();
                $this->assertSame($data['title'], $log->fresh()->title);
                $delete = $this->deleteJson($item);
                $api ? $delete->assertSuccessful() : $delete->assertRedirect()->assertSessionHasNoErrors();
                $this->assertDatabaseMissing('training_logs', ['id' => $log->id]);
            }
        }
    }

    #[DataProvider('plans')]
    public function test_personal_exercise_library_permissions(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $other = User::factory()->create();
        $this->actingAs($user);
        $this->get(route('auth.training.index'))->assertOk();
        $this->getJson('/api/v1/training/plans')->assertOk()->assertJsonStructure(['data']);
        foreach (['web', 'api'] as $surface) {
            $api = $surface === 'api';
            $this->actingAs($user);
            $url = $api ? '/api/v1/training/exercises' : route('auth.training.exercises.store');
            $data = ['name' => 'Tarifkniebeuge'.$surface, 'scope' => 'personal', 'sport_type' => 'strength', 'difficulty' => 'beginner'];
            $created = $this->postJson($url, $data);
            $api ? $created->assertCreated() : $created->assertRedirect()->assertSessionHasNoErrors();
            $exercise = \App\Models\TrainingExercise::where('name', $data['name'])->where('created_by', $user->id)->sole();
            $webList = route('auth.training.exercises.index', ['q' => $data['name']]);
            $apiList = '/api/v1/training/exercises?q='.$data['name'];
            $this->get($webList)->assertOk()->assertInertia(fn ($page) => $page->where('exercises.0.id', $exercise->id));
            $this->getJson($apiList)->assertOk()->assertJsonPath('data.0.id', $exercise->id);
            $item = $url.'/'.$exercise->id;
            $this->actingAs($other);
            $this->get($webList)->assertOk()->assertInertia(fn ($page) => $page->has('exercises', 0));
            $this->getJson($apiList)->assertOk()->assertJsonCount(0, 'data');
            $this->putJson($item, $data)->assertForbidden();
            $this->actingAs($user);
            $data['name'] .= ' neu';
            $updated = $this->putJson($item, $data);
            $api ? $updated->assertOk() : $updated->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($data['name'], $exercise->fresh()->name);
            if ($api) {
                $this->deleteJson($item)->assertOk();
                $this->assertFalse($exercise->fresh()->is_active);
                $this->getJson($apiList)->assertOk()->assertJsonCount(0, 'data');
            }
        }
    }

    #[DataProvider('plans')]
    public function test_shared_exercise_creation_by_plan_and_role(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $existing = $user->clubs()->first();
        foreach ($existing ? [$slug] : ['free', 'starter'] as $clubSlug) {
            $club = $existing ?? Club::factory()->create();
            $club->currentSubscription()->updateOrCreate([], [
                'subscription_plan_id' => SubscriptionPlan::where('slug', $clubSlug)->firstOrFail()->id,
                'status' => 'active', 'billing_interval' => 'monthly',
            ]);
            $team = \App\Models\Team::factory()->create(['club_id' => $club->id]);
            $team->users()->attach($user, ['role' => \App\Support\TeamRoles::COACH]);
            foreach (['coach', 'editor', 'denied'] as $role) {
                $rights = $role === 'coach' ? [] : ['training_exercises.edit' => $role === 'editor'];
                $club->users()->syncWithoutDetaching([$user->id => ['role' => 'member', 'membership_status' => 'active', 'permission_overrides' => $rights]]);
                $this->actingAs($user->fresh());
                foreach (['club' => $club->id, 'team' => $team->id] as $scope => $id) {
                    foreach (['web', 'api'] as $surface) {
                        $name = "$clubSlug $role $scope $surface";
                        $url = $surface === 'web' ? route('auth.training.exercises.store') : '/api/v1/training/exercises';
                        $response = $this->postJson($url, ['name' => $name, 'scope' => $scope, $scope.'_id' => $id, 'difficulty' => 'beginner']);
                        $record = ['club_id' => $club->id, 'name' => $name];
                        if ($role === 'denied' || ($role === 'coach' && $scope === 'club')) {
                            $response->assertForbidden();
                        } elseif ($clubSlug === 'free') {
                            $response->assertUnprocessable()->assertJsonValidationErrors('general');
                        } else {
                            $surface === 'web' ? $response->assertRedirect()->assertSessionHasNoErrors() : $response->assertCreated();
                            $this->assertDatabaseHas('training_exercises', $record + ['team_id' => $scope === 'team' ? $id : null]);
                            continue;
                        }
                        $this->assertDatabaseMissing('training_exercises', $record);
                    }
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_existing_shared_exercise_edit_and_removal(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $club = $user->clubs()->first() ?? Club::factory()->create();
        $team = \App\Models\Team::factory()->create(['club_id' => $club->id]);
        foreach ([null, $team->id] as $teamId) {
            $exercise = \App\Models\TrainingExercise::create([
                'created_by' => $club->owner_id, 'club_id' => $club->id, 'team_id' => $teamId,
                'name' => 'Staffellauf', 'difficulty' => 'all', 'is_active' => true,
            ]);
            $api = '/api/v1/training/exercises/'.$exercise->id;
            $web = route('auth.training.exercises.update', $exercise);
            foreach (['edit', 'delete'] as $right) {
                $club->users()->syncWithoutDetaching([$user->id => [
                    'role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['training_exercises.edit' => $right === 'edit', 'training_exercises.delete' => $right === 'delete'],
                ]]);
                $this->actingAs($user->fresh());
                foreach ([$web, $api] as $url) {
                    $name = $url === $web ? 'Staffellauf im Web' : 'Staffellauf in der App';
                    $update = $this->putJson($url, ['name' => $name, 'difficulty' => 'beginner']);
                    if ($right === 'edit') {
                        $url === $web ? $update->assertRedirect()->assertSessionHasNoErrors() : $update->assertOk();
                        $this->assertSame($name, $exercise->fresh()->name);
                    } else {
                        $update->assertForbidden();
                    }
                }
                $delete = $this->deleteJson($api);
                $right === 'edit' ? $delete->assertForbidden() : $delete->assertOk();
                $this->assertSame($right === 'edit', $exercise->fresh()->is_active);
            }
        }
    }

    #[DataProvider('plans')]
    public function test_club_policy_document_file_lifecycle(string $slug): void
    {
        // Storage::fake clears its directory. Separate simultaneous test runs.
        config(['filesystems.uploads_disk' => 'policy-public-'.getmypid(),
            'filesystems.private_uploads_disk' => 'policy-private-'.getmypid()]);
        $user = $this->accountForPlan($slug);
        $club = $user->clubs()->first() ?? Club::factory()->create();
        $this->actingAs($user);
        \Illuminate\Support\Facades\Storage::fake(\App\Support\UploadStorage::disk());
        \Illuminate\Support\Facades\Storage::fake(\App\Support\UploadStorage::privateDisk());
        $rights = ['files.edit' => true, 'files.view' => true, 'policy_documents.view' => true,
            'policy_documents.edit' => true, 'policy_documents.download' => true, 'policy_documents.delete' => false];
        $club->users()->syncWithoutDetaching([$user->id => ['role' => 'member', 'membership_status' => 'active',
            'permission_overrides' => $rights]]);
        $this->actingAs($user->fresh());
        $contents = (new \App\Services\AirmiusPdfDocument())
            ->text("Vereinsordnung - Tarif $slug", 48, 760, 18)->render();
        $filename = "ordnung-$slug.pdf";
        $this->postJson('/api/v1/uploads', ['scope' => 'club', 'club_id' => $club->id,
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent($filename, $contents)])->assertCreated();
        $file = \App\Models\File::where('club_id', $club->id)->where('display_name', $filename)->sole();
        $base = "/api/v1/clubs/{$club->id}/policy-documents";
        $data = ['type' => 'regulation', 'title' => 'Vereinsordnung', 'version_label' => '1',
            'valid_from' => today()->toDateString(), 'file_id' => $file->id, 'is_public' => false];
        $id = $this->postJson($base, $data)->assertCreated()->json('data.id');
        $data['title'] = 'Neue Vereinsordnung';
        $this->putJson("$base/$id", $data)->assertOk()->assertJsonPath('data.title', $data['title']);
        $download = $this->get("$base/$id/download")->assertOk()->assertDownload($filename);
        $this->assertSame($contents, $download->streamedContent());
        $this->deleteJson("$base/$id")->assertForbidden();
        $club->update(['is_listed' => true]);
        $outsider = $this->accountForPlan($slug);
        $otherClub = $outsider->clubs()->first() ?? Club::factory()->create();
        $otherClub->users()->syncWithoutDetaching([$outsider->id => ['role' => 'member', 'membership_status' => 'active']]);
        $otherClub->users()->syncWithoutDetaching([$user->id => ['role' => 'member', 'membership_status' => 'active',
            'permission_overrides' => $rights]]);
        $this->actingAs($user->fresh())->postJson("/api/v1/clubs/{$otherClub->id}/policy-documents", $data)
            ->assertUnprocessable()->assertJsonValidationErrors('file_id');
        $this->assertDatabaseMissing('club_policy_documents', ['club_id' => $otherClub->id, 'file_id' => $file->id]);
        foreach ([false, true, false] as $published) {
            $data['is_public'] = $published;
            $this->actingAs($user)->putJson("$base/$id", $data)->assertOk();
            $list = $this->actingAs($outsider)->getJson($base)->assertOk();
            $result = $this->get("$base/$id/download");
            if ($published) {
                $list->assertJsonCount(1, 'data.documents')->assertJsonPath('data.documents.0.id', $id);
                $result->assertOk()->assertDownload($filename);
                $this->assertSame($contents, $result->streamedContent());
                $query = ['scope' => 'club', 'club_id' => $club->id, 'search' => $filename];
                $this->getJson('/api/v1/files?'.http_build_query($query))->assertNotFound();
                $this->get(route('auth.files.index', $query))->assertNotFound();
            } else {
                $list->assertJsonCount(0, 'data.documents');
                $result->assertNotFound();
            }
        }
        $rights['policy_documents.delete'] = true;
        $club->users()->updateExistingPivot($user->id, ['permission_overrides' => $rights]);
        $this->actingAs($user->fresh())->deleteJson("$base/$id")->assertOk()->assertJsonPath('data.deleted', true);
        $this->assertDatabaseMissing('club_policy_documents', ['id' => $id]);
    }

    #[DataProvider('plans')]
    public function test_membership_waitlist_requires_club_reviewer(string $slug): void
    {
        $reviewer = $this->accountForPlan($slug);
        $club = $reviewer->clubs()->first() ?? Club::factory()->create();
        foreach (['web', 'api'] as $surface) {
            $applicant = User::factory()->create();
            $application = \App\Models\ClubMembershipRequest::create(['club_id' => $club->id, 'user_id' => $applicant->id,
                'type' => 'membership', 'status' => 'pending']);
            $url = $surface === 'web' ? route('auth.club-membership-requests.waitlist', $application)
                : "/api/v1/clubs/{$club->id}/membership-requests/{$application->id}/waitlist";
            foreach ([false, true] as $allowed) {
                $club->users()->syncWithoutDetaching([$reviewer->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['members.approve' => $allowed]]]);
                $response = $this->actingAs($reviewer->fresh())->postJson($url, ['review_note' => 'Gruppe voll']);
                if (! $allowed) {
                    $response->assertForbidden();
                    $this->assertSame('pending', $application->fresh()->status);
                    continue;
                }
                $surface === 'web' ? $response->assertRedirect()->assertSessionHasNoErrors() : $response->assertOk();
                $this->assertDatabaseHas('club_membership_requests', ['id' => $application->id, 'status' => 'waitlisted',
                    'waitlisted_by' => $reviewer->id, 'review_note' => 'Gruppe voll']);
                $this->assertFalse($club->users()->whereKey($applicant->id)->exists());
            }
        }
    }

    #[DataProvider('plans')]
    public function test_waitlisted_membership_decisions(string $slug): void
    {
        $reviewer = $this->accountForPlan($slug);
        $club = $reviewer->clubs()->first() ?? Club::factory()->create();
        foreach (['web', 'api'] as $surface) {
            foreach (['approve' => 'approved', 'decline' => 'declined'] as $action => $status) {
                $applicant = User::factory()->create();
                $application = \App\Models\ClubMembershipRequest::create(['club_id' => $club->id, 'user_id' => $applicant->id,
                    'type' => 'membership', 'status' => 'waitlisted']);
                $url = $surface === 'web' ? route("auth.club-membership-requests.$action", $application)
                    : "/api/v1/clubs/{$club->id}/membership-requests/{$application->id}/$action";
                foreach ([false, true] as $allowed) {
                    $club->users()->syncWithoutDetaching([$reviewer->id => ['role' => 'member', 'membership_status' => 'active',
                        'permission_overrides' => ['members.approve' => $allowed]]]);
                    $response = $this->actingAs($reviewer->fresh())->postJson($url, ['review_note' => 'Entscheidung geprüft']);
                    if (! $allowed) {
                        $response->assertForbidden();
                        $this->assertSame('waitlisted', $application->fresh()->status);
                        continue;
                    }
                    $surface === 'web' ? $response->assertRedirect()->assertSessionHasNoErrors() : $response->assertOk();
                    $this->assertDatabaseHas('club_membership_requests', ['id' => $application->id, 'status' => $status,
                        'reviewed_by' => $reviewer->id, 'review_note' => 'Entscheidung geprüft']);
                    $membership = ['club_id' => $club->id, 'user_id' => $applicant->id];
                    if ($action === 'approve') {
                        $this->assertDatabaseHas('club_user', $membership + ['membership_status' => 'active']);
                    } else {
                        $this->assertDatabaseMissing('club_user', $membership);
                    }
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_club_contribution_rule_creation_and_update(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $club = $user->clubs()->first() ?? Club::factory()->create();
        foreach (['web' => 'finance.edit', 'api' => 'members.manage'] as $surface => $needed) {
            $url = $surface === 'web' ? route('auth.club-memberships.contribution-rules.store', $club)
                : "/api/v1/clubs/{$club->id}/membership/contribution-rules";
            $data = ['name' => "Jahresbeitrag $surface", 'valid_from' => today()->toDateString(),
                'billing_interval' => 'yearly', 'amount' => 240, 'factor_key' => 'family'];
            foreach ([false, true] as $allowed) {
                $rights = ['finance.edit' => false, 'members.manage' => false];
                $rights[$needed] = $allowed;
                $club->users()->syncWithoutDetaching([$user->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => $rights]]);
                $result = $this->actingAs($user->fresh())->postJson($url, $data);
                if (! $allowed) {
                    $result->assertForbidden();
                    $this->assertFalse($club->contributionRules()->where('name', $data['name'])->exists());
                    continue;
                }
                $surface === 'web' ? $result->assertRedirect()->assertSessionHasNoErrors() : $result->assertCreated();
                $rule = $club->contributionRules()->where('name', $data['name'])->sole();
                $data['name'] .= ' geändert';
                $result = $this->putJson("$url/{$rule->id}", $data);
                $surface === 'web' ? $result->assertRedirect()->assertSessionHasNoErrors() : $result->assertOk();
                $this->assertSame($data['name'], $rule->fresh()->name);
                $this->deleteJson("$url/{$rule->id}")->assertMethodNotAllowed();
                $rights[$needed] = false;
                $club->users()->updateExistingPivot($user->id, ['permission_overrides' => $rights]);
                $this->actingAs($user->fresh())->putJson("$url/{$rule->id}", array_replace($data, [
                    'name' => 'Änderung nach Rechteentzug',
                ]))->assertForbidden();
                $this->assertSame($data['name'], $rule->fresh()->name);
            }
        }
    }

    #[DataProvider('plans')]
    public function test_application_documents_preserve_optional_choice(string $slug): void
    {
        $applicant = $this->accountForPlan($slug);
        $this->actingAs($applicant);
        foreach (['web', 'api'] as $surface) {
            foreach ([false, true] as $acceptPhoto) {
                $document = ['type' => 'privacy', 'url' => 'https://example.test/datenschutz', 'is_visible' => true];
                $club = Club::factory()->create(['membership_requests_enabled' => true, 'membership_application_fields' => ['gender' => 'required'], 'membership_application_documents' => [
                    $document + ['id' => 'privacy', 'title' => 'Datenschutzhinweis', 'is_required' => true],
                    $document + ['id' => 'photo', 'title' => 'Einwilligung Vereinsfotos', 'is_required' => false],
                ]]);
                $url = $surface === 'web' ? route('auth.club-membership-requests.store', $club)
                    : "/api/v1/clubs/{$club->id}/membership-requests";
                $data = ['type' => 'membership', 'application_data' => ['gender' => 'diverse'], 'accepted_documents' => ['photo' => $acceptPhoto]];
                $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('accepted_documents.privacy');
                $data['accepted_documents']['privacy'] = true;
                $result = $this->postJson($url, $data);
                $surface === 'web' ? $result->assertRedirect()->assertSessionHasNoErrors() : $result->assertCreated();
                $application = \App\Models\ClubMembershipRequest::where('club_id', $club->id)->where('user_id', $applicant->id)->sole();
                $ids = array_column($application->accepted_documents, 'id');
                $this->assertContains('privacy', $ids);
                $this->assertSame($acceptPhoto, in_array('photo', $ids, true));
                $this->assertSame('pending', $application->status);
                $action = fn ($name) => $surface === 'web' ? route("auth.club-membership-requests.$name", $application)
                    : "$url/{$application->id}/$name";
                $query = $this->actingAs($club->owner)->postJson($action('request-information'), ['message' => 'Bitte Auswahl prüfen']);
                $surface === 'web' ? $query->assertRedirect()->assertSessionHasNoErrors() : $query->assertOk();
                $reply = $this->actingAs($applicant)->postJson($action('respond'), ['accepted_documents' => ['photo' => ! $acceptPhoto]]);
                $surface === 'web' ? $reply->assertRedirect()->assertSessionHasNoErrors() : $reply->assertOk();
                $application->refresh();
                $ids = array_column($application->accepted_documents, 'id');
                $this->assertSame(! $acceptPhoto, in_array('photo', $ids, true));
                $this->assertContains('privacy', $ids);
                $this->assertSame('pending', $application->status);
                $approved = $this->actingAs($club->owner)->postJson($action('approve'));
                $surface === 'web' ? $approved->assertRedirect()->assertSessionHasNoErrors() : $approved->assertOk();
                $this->assertDatabaseHas('club_user', ['club_id' => $club->id, 'user_id' => $applicant->id, 'membership_status' => 'active']);
                $this->actingAs($applicant)->postJson($action('respond'), ['accepted_documents' => ['photo' => $acceptPhoto]])
                    ->assertUnprocessable();
                $application->refresh();
                $this->assertSame('approved', $application->status);
                $this->assertSame($ids, array_column($application->accepted_documents, 'id'));
                $applicant->forceFill(['product_analytics_consent' => true])->save();
                $withdraw = $surface === 'web' ? route('auth.settings.privacy.withdraw-consents') : '/api/v1/privacy/withdraw-consents';
                $result = $this->postJson($withdraw, ['consents' => ['all']]);
                $surface === 'web' ? $result->assertRedirect()->assertSessionHas('success') : $result->assertOk();
                $this->assertFalse((bool) $applicant->fresh()->product_analytics_consent);
                $this->assertSame($ids, array_column($application->fresh()->accepted_documents, 'id'));
            }
        }
    }

    #[DataProvider('plans')]
    public function test_membership_application_information_exchange(string $slug): void
    {
        $reviewer = $this->accountForPlan($slug);
        $applicant = $this->accountForPlan($slug);
        $club = $reviewer->clubs()->first() ?? Club::factory()->create();
        foreach (['web', 'api'] as $surface) {
            $application = \App\Models\ClubMembershipRequest::create(['club_id' => $club->id, 'user_id' => $applicant->id,
                'type' => 'membership', 'status' => 'pending']);
            $url = fn ($action) => $surface === 'web' ? route("auth.club-membership-requests.$action", $application)
                : "/api/v1/clubs/{$club->id}/membership-requests/{$application->id}/$action";
            foreach ([false, true] as $allowed) {
                $club->users()->syncWithoutDetaching([$reviewer->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['members.approve' => $allowed]]]);
                $request = $this->actingAs($reviewer->fresh())->postJson($url('request-information'), ['message' => 'Welche Gruppe?']);
                if (! $allowed) {
                    $request->assertForbidden();
                    $this->assertSame('pending', $application->fresh()->status);
                } else {
                    $surface === 'web' ? $request->assertRedirect()->assertSessionHasNoErrors() : $request->assertOk();
                    $this->assertSame('information_requested', $application->fresh()->status);
                }
            }
            $this->postJson($url('respond'), ['message' => 'Fremde Antwort'])->assertForbidden();
            $answer = $this->actingAs($applicant)->postJson($url('respond'), ['message' => 'Laufgruppe']);
            $surface === 'web' ? $answer->assertRedirect()->assertSessionHasNoErrors() : $answer->assertOk();
            $this->assertDatabaseHas('club_membership_requests', ['id' => $application->id, 'status' => 'pending',
                'applicant_response_message' => 'Laufgruppe', 'information_requested_by' => $reviewer->id]);
            if ($surface === 'web') {
                $application->delete();
            }
        }
    }

    #[DataProvider('plans')]
    public function test_team_join_request_decisions(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $club = $user->clubs()->first() ?? Club::factory()->create();
        $club->users()->syncWithoutDetaching([$user->id => ['role' => 'member', 'membership_status' => 'active']]);
        foreach (['web', 'api'] as $surface) {
            foreach (['approve', 'decline'] as $action) {
                $team = \App\Models\Team::factory()->create(['club_id' => $club->id]);
                $api = $surface === 'api';
                $this->actingAs($user->fresh());
                $base = '/api/v1/teams/'.$team->id.'/join-requests';
                $request = $this->postJson($api ? $base : route('auth.teams.join-requests.store', $team));
                $api ? $request->assertSuccessful() : $request->assertRedirect()->assertSessionHasNoErrors();
                $join = $team->joinRequests()->where('user_id', $user->id)->sole();
                $this->assertSame('pending', $join->status);
                $membership = ['team_id' => $team->id, 'user_id' => $user->id];
                $this->assertDatabaseMissing('team_user', $membership);
                $url = $api ? $base.'/'.$join->id.'/'.$action : route('auth.team-join-requests.'.$action, $join);
                $this->postJson($url)->assertForbidden();
                $this->assertSame('pending', $join->fresh()->status);
                $this->actingAs(User::findOrFail($club->owner_id));
                $decision = $api ? $this->postJson($url) : $this->post($url);
                $api ? $decision->assertOk() : $decision->assertRedirect()->assertSessionHasNoErrors();
                $this->assertSame($action === 'approve' ? 'accepted' : 'declined', $join->fresh()->status);
                if ($action === 'approve') {
                    $this->assertDatabaseHas('team_user', $membership);
                } else {
                    $this->assertDatabaseMissing('team_user', $membership);
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_public_club_sponsor_directory_by_tariff(string $slug): void
    {
        $viewer = $this->accountForPlan($slug);
        $club = Club::factory()->create();
        $partner = $club->sponsors()->create(['name' => 'Aktiver Partner', 'scope' => 'club',
            'verification_status' => 'verified', 'starts_at' => today(), 'ends_at' => today()]);
        foreach ([
            ['verification_status' => 'pending'],
            ['verification_status' => 'verified', 'starts_at' => today()->addDay()],
            ['verification_status' => 'verified', 'ends_at' => today()->subDay()],
        ] as $index => $attributes) {
            $club->sponsors()->create($attributes + ['name' => "Inaktiver Partner $index", 'scope' => 'club']);
        }
        $this->actingAs($viewer);
        $this->get(route('guest.sponsors'))->assertOk()->assertInertia(fn ($page) => $page
            ->has('sponsors', 1)->where('sponsors.0.id', $partner->id)->where('sponsors.0.club.id', $club->id));
        $this->getJson('/api/v1/public/sponsors')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $partner->id)->assertJsonPath('data.0.club.id', $club->id);
    }

    #[DataProvider('plans')]
    public function test_member_team_directory_and_club_sponsor_list(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $club = $user->clubs()->first() ?? Club::factory()->create();
        $club->users()->syncWithoutDetaching([$user->id => [
            'role' => 'member', 'membership_status' => 'active',
            'permission_overrides' => ['sponsors.edit' => false, 'sponsors.delete' => false],
        ]]);
        $team = \App\Models\Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($user, ['role' => 'member']);
        $sponsor = $club->sponsors()->create(['scope' => 'club', 'name' => 'Testpartner']);
        $foreign = Club::factory()->create();
        \App\Models\Team::factory()->create(['club_id' => $foreign->id]);
        $foreign->sponsors()->create(['scope' => 'club', 'name' => 'Fremder Partner']);
        $this->actingAs($user->fresh());
        $this->get(route('auth.teams.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->has('clubs', 1)->where('clubs.0.id', $club->id)
            ->has('clubs.0.teams', 1)->where('clubs.0.teams.0.id', $team->id)
            ->has('clubs.0.sponsors', 1)->where('clubs.0.sponsors.0.id', $sponsor->id));
        $this->get(route('auth.teams.show', $team))->assertOk()->assertInertia(fn ($page) => $page->where('teamProfile.id', $team->id));
        $this->getJson('/api/v1/teams')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $team->id);
        $this->getJson('/api/v1/teams/'.$team->id)->assertOk()->assertJsonPath('data.id', $team->id);
    }

    #[DataProvider('plans')]
    public function test_club_team_limits_at_creation(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $token = $user->createToken('tariff-team-test')->plainTextToken;
        $isClubPlan = $user->clubs()->exists();
        foreach ($isClubPlan ? [$slug] : ['free', 'starter'] as $clubSlug) {
            $plan = SubscriptionPlan::where('slug', $clubSlug)->firstOrFail();
            $limit = match ($clubSlug) { 'free' => 1, 'starter' => 5, default => null };
            $this->assertSame($limit, $plan->team_limit);
            foreach (['web', 'api'] as $surface) {
                $club = Club::factory()->create();
                $club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => $plan->id, 'status' => 'active', 'billing_interval' => 'monthly']);
                $club->users()->attach($user, ['role' => 'member', 'membership_status' => 'active', 'permission_overrides' => ['teams.edit' => true]]);
                $this->actingAs($user->fresh());
                $before = ($limit ?? 7) - 1;
                if ($before > 0) {
                    \App\Models\Team::factory()->count($before)->create(['club_id' => $club->id]);
                }
                foreach (['Grenzteam', 'Weiteres Team'] as $name) {
                    $data = ['club_id' => $club->id, 'name' => $name, 'sport_type' => 'laufen'];
                    $response = $surface === 'api'
                        ? $this->postJson('/api/v1/teams', $data, ['Authorization' => 'Bearer '.$token])
                        : $this->postJson(route('auth.teams.store'), $data);
                    if ($limit !== null && $name === 'Weiteres Team') {
                        $response->assertUnprocessable()->assertJsonValidationErrors('general');
                        $this->assertSame($limit, $club->teams()->count());
                    } else {
                        $surface === 'api' ? $response->assertCreated() : $response->assertRedirect()->assertSessionHasNoErrors();
                        $this->assertDatabaseHas('teams', ['club_id' => $club->id, 'name' => $name]);
                    }
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_manual_club_member_capacity(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        foreach ($user->clubs()->exists() ? [$slug] : ['free', 'starter'] as $clubSlug) {
            $plan = SubscriptionPlan::where('slug', $clubSlug)->firstOrFail();
            $limit = match ($clubSlug) { 'free' => 25, 'starter' => 50, 'club' => 150, 'pro' => 500, default => null };
            $this->assertEquals($limit, $plan->member_limit);
            foreach (['web', 'api'] as $surface) {
                $club = Club::factory()->create();
                $club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => $plan->id, 'status' => 'active', 'billing_interval' => 'monthly']);
                $club->users()->attach($user, ['role' => 'member', 'membership_status' => 'active', 'permission_overrides' => ['members.edit' => true, 'members.manage' => true]]);
                $this->actingAs($user->fresh());
                $existing = ($limit ?? 501) - 1 - $club->memberUsageCount();
                $rows = [];
                for ($i = 0; $i < $existing; $i++) {
                    $rows[] = ['club_id' => $club->id, 'email' => "bestand-$i@example.test", 'created_at' => now()->subWeek()];
                }
                foreach (array_chunk($rows, 100) as $chunk) {
                    \App\Models\ClubExternalMember::insert($chunk);
                }
                $url = $surface === 'api' ? "/api/v1/clubs/{$club->id}/members/bulk" : route('auth.club-memberships.email-members.store', $club);
                foreach (['letzter', 'weiterer'] as $entry) {
                    $email = "$entry@example.test";
                    $licence = "L-$clubSlug-$surface-$entry";
                    $expires = now()->addYear()->toDateString();
                    $response = $this->postJson($url, [
                        'email' => $email, 'membership_status' => 'active', 'send_invitation' => false,
                        'athlete_license_number' => $licence, 'athlete_license_valid_until' => $expires,
                    ]);
                    $key = ['club_id' => $club->id, 'email' => $email];
                    if ($limit !== null && $entry === 'weiterer') {
                        $response->assertUnprocessable()->assertJsonValidationErrors('general');
                        $this->assertStringContainsString('Mitgliederlimit', $response->json('errors.general.0'));
                        $this->assertDatabaseMissing('club_external_members', $key);
                        $this->assertSame($limit, $club->memberUsageCount());
                    } else {
                        $surface === 'api' ? $response->assertCreated() : $response->assertRedirect()->assertSessionHasNoErrors();
                        $this->assertDatabaseHas('club_external_members', $key + ['athlete_license_number' => $licence]);
                        $saved = $club->externalMembers()->where('email', $email)->sole();
                        $this->assertSame($expires, $saved->athlete_license_valid_until->toDateString());
                    }
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_external_member_number_rights(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $club = $user->clubs()->first() ?? Club::factory()->create();
        foreach (['web', 'api'] as $surface) {
            $external = $club->externalMembers()->create(['email' => "$surface@example.test", 'membership_status' => 'active']);
            $api = $surface === 'api';
            $route = $api ? 'api.v1.clubs.external-members.member-number.store' : 'auth.club-memberships.external-members.member-number';
            $url = route($route, [$club, $external]);
            foreach ([[false, true], [true, false], [true, true]] as [$metadata, $manage]) {
                $club->users()->syncWithoutDetaching([$user->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['metadata.edit' => $metadata, 'members.manage' => $manage]]]);
                $this->actingAs($user->fresh());
                $response = $this->postJson($url);
                if ($metadata && (! $api || $manage)) {
                    $api ? $response->assertOk() : $response->assertRedirect()->assertSessionHasNoErrors();
                    $this->assertNotEmpty($external->fresh()->member_number);
                } else {
                    $response->assertForbidden();
                    $this->assertNull($external->fresh()->member_number);
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_registered_member_numbers_are_scoped_to_the_club(string $slug): void
    {
        $actor = $this->accountForPlan($slug);
        $club = $actor->clubs()->first() ?? Club::factory()->create();
        $otherClub = Club::factory()->create();
        foreach (['web', 'api'] as $surface) {
            $member = User::factory()->create();
            $club->users()->attach($member, ['role' => 'member', 'membership_status' => 'active']);
            $otherClub->users()->attach($member, ['role' => 'member', 'membership_status' => 'active', 'member_number' => 'Andere-17']);
            $api = $surface === 'api';
            $route = $api ? 'api.v1.clubs.members.member-number.store' : 'auth.club-memberships.members.member-number';
            foreach ([[false, true], [true, false], [true, true]] as [$metadata, $manage]) {
                $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['metadata.edit' => $metadata, 'members.manage' => $manage]]]);
                $this->actingAs($actor->fresh());
                $response = $this->postJson(route($route, [$club, $member]));
                $number = \Illuminate\Support\Facades\DB::table('club_user')->where('club_id', $club->id)->where('user_id', $member->id)->value('member_number');
                if ($metadata && (! $api || $manage)) {
                    $api ? $response->assertOk() : $response->assertRedirect()->assertSessionHasNoErrors();
                    $this->assertNotEmpty($number);
                } else {
                    $response->assertForbidden();
                    $this->assertNull($number);
                }
                $this->assertDatabaseHas('club_user', ['club_id' => $otherClub->id, 'user_id' => $member->id, 'member_number' => 'Andere-17']);
            }
        }
    }

    #[DataProvider('plans')]
    public function test_membership_file_import_by_plan(string $slug): void
    {
        $actor = $this->accountForPlan($slug);
        $this->actingAs($actor);
        $excel = $this->get('/api/v1/club-members/import-template')->assertOk()->streamedContent();
        foreach ($actor->clubs()->exists() ? [$slug] : ['free', 'starter'] as $clubSlug) {
            foreach (['web', 'api'] as $surface) {
                $club = Club::factory()->create();
                $club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => SubscriptionPlan::where('slug', $clubSlug)->firstOrFail()->id,
                    'status' => 'active', 'billing_interval' => 'monthly']);
                $club->users()->attach($actor, ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['members.edit' => true, 'members.manage' => true]]);
                $this->actingAs($actor->fresh());
                $url = $surface === 'api' ? "/api/v1/clubs/{$club->id}/members/import" : route('auth.club-memberships.email-members.import', $club);
                foreach (['csv' => "Name;E-Mail\nTestperson;csv@example.test", 'xlsx' => $excel] as $format => $contents) {
                    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent("mitglieder.$format", $contents);
                    $response = $this->postJson($url, ['file' => $file, 'send_invitation' => false]);
                    $key = ['club_id' => $club->id, 'email' => $format === 'xlsx' ? 'max@example.org' : 'csv@example.test'];
                    if ($clubSlug === 'free') {
                        $response->assertUnprocessable()->assertJsonValidationErrors('general');
                        $this->assertDatabaseMissing('club_external_members', $key);
                    } else {
                        $surface === 'api' ? $response->assertCreated() : $response->assertRedirect()->assertSessionHasNoErrors();
                        $this->assertDatabaseHas('club_external_members', $key);
                    }
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_existing_invoice_payment_by_tariff(string $slug): void
    {
        $actor = $this->accountForPlan($slug);
        foreach ($actor->clubs()->exists() ? [$slug] : ['free', 'starter'] as $clubSlug) {
            $club = Club::factory()->create();
            $club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => SubscriptionPlan::where('slug', $clubSlug)->firstOrFail()->id,
                'status' => 'active', 'billing_interval' => 'monthly']);
            foreach (['web', 'api'] as $surface) {
                $api = $surface === 'api';
                $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['finance.edit' => ! $api, 'finance.manage' => $api]]]);
                $this->actingAs($actor->fresh());
                $invoice = \App\Models\Invoice::create(['club_id' => $club->id, 'number' => "$surface-$clubSlug",
                    'title' => 'Testbeitrag', 'amount' => 25, 'status' => 'open', 'due_date' => now()->addWeek()]);
                $url = $api ? route('api.v1.clubs.membership-invoices.payments.store', [$club, $invoice]) : route('auth.club-memberships.invoices.payments.store', $invoice);
                $result = $this->postJson($url, ['method' => 'manual', 'amount' => 25]);
                if ($api && $clubSlug === 'free') {
                    $result->assertUnprocessable()->assertJsonValidationErrors('general');
                    $this->assertSame('open', $invoice->fresh()->status);
                    $this->assertSame(0, $invoice->payments()->count());
                } else {
                    $api ? $result->assertOk() : $result->assertRedirect()->assertSessionHasNoErrors();
                    $this->assertSame('paid', $invoice->fresh()->status);
                    $this->assertSame(2500, $invoice->fresh()->receivedCents());
                    $this->assertSame(1, $invoice->payments()->count());
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_member_invoice_creation_by_tariff(string $slug): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $actor = $this->accountForPlan($slug);
        foreach ($actor->clubs()->exists() ? [$slug] : ['free', 'starter'] as $clubSlug) {
            $club = Club::factory()->create();
            $club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => SubscriptionPlan::where('slug', $clubSlug)->firstOrFail()->id,
                'status' => 'active', 'billing_interval' => 'monthly']);
            $member = User::factory()->create();
            $club->users()->attach($member, ['role' => 'member', 'membership_status' => 'active']);
            foreach (['web', 'api'] as $surface) {
                $api = $surface === 'api';
                $url = route($api ? 'api.v1.clubs.members.invoices.store' : 'auth.club-memberships.invoices.store', [$club, $member]);
                foreach ([[false, true], [true, false], [true, true]] as [$edit, $manage]) {
                    $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active',
                        'permission_overrides' => ['finance.edit' => $edit, 'finance.manage' => $manage]]]);
                    $this->actingAs($actor->fresh());
                    $title = "Beitrag $surface ".(int) $edit.(int) $manage;
                    $result = $this->postJson($url, ['title' => $title, 'amount' => 27.50, 'due_date' => now()->addMonth()->toDateString()]);
                    $key = ['club_id' => $club->id, 'title' => $title];
                    if (! $edit || ($api && ! $manage)) {
                        $result->assertForbidden();
                    } elseif ($clubSlug === 'free') {
                        $result->assertUnprocessable()->assertJsonValidationErrors('general');
                    } else {
                        $api ? $result->assertCreated() : $result->assertRedirect()->assertSessionHasNoErrors();
                        $invoice = \App\Models\Invoice::where($key)->sole();
                        $this->assertSame($member->id, $invoice->membership_user_id);
                        $this->assertSame('27.50', (string) $invoice->amount);
                        $this->assertSame('open', $invoice->status);
                        $this->assertNotEmpty($invoice->number);
                        continue;
                    }
                    $this->assertDatabaseMissing('invoices', $key);
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_club_payment_reminders(string $slug): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $actor = $this->accountForPlan($slug);
        foreach ($actor->clubs()->exists() ? [$slug] : ['starter', 'club'] as $clubSlug) {
            $club = Club::factory()->create();
            $club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => SubscriptionPlan::where('slug', $clubSlug)->firstOrFail()->id,
                'status' => 'active', 'billing_interval' => 'monthly']);
            foreach (['web', 'api'] as $surface) {
                $api = $surface === 'api';
                $member = User::factory()->create();
                $club->users()->attach($member, ['role' => 'member', 'membership_status' => 'active']);
                $invoice = \App\Models\Invoice::create(['club_id' => $club->id, 'user_id' => $member->id, 'title' => 'Testbeitrag',
                    'number' => "M-$surface-$clubSlug", 'amount' => 23, 'status' => 'open', 'due_date' => now()->subWeek()]);
                $url = $api ? route('api.v1.clubs.membership-invoices.reminder.store', [$club, $invoice]) : route('auth.club-memberships.invoices.reminder', $invoice);
                $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['finance.edit' => true, 'finance.manage' => $api]]]);
                $this->actingAs($actor->fresh());
                $response = $this->postJson($url);
                $recipient = ['user_id' => $member->id, 'type' => 'invoice.reminder'];
                if (in_array($clubSlug, ['free', 'starter'], true)) {
                    $response->assertUnprocessable()->assertJsonValidationErrors('general');
                    $this->assertNull($invoice->fresh()->reminder_sent_at);
                    $this->assertDatabaseMissing('notifications', $recipient);
                } else {
                    $api ? $response->assertOk() : $response->assertRedirect()->assertSessionHasNoErrors();
                    $this->assertSame('overdue', $invoice->fresh()->status);
                    $this->assertNotNull($invoice->fresh()->reminder_sent_at);
                    $this->assertDatabaseHas('notifications', $recipient);
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_datev_export_by_club_plan_and_permission(string $slug): void
    {
        $actor = $this->accountForPlan($slug);
        foreach ($actor->clubs()->exists() ? [$slug] : ['club', 'pro'] as $clubSlug) {
            $club = Club::factory()->create();
            $club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => SubscriptionPlan::where('slug', $clubSlug)->firstOrFail()->id,
                'status' => 'active', 'billing_interval' => 'monthly']);
            $number = 'TEST-DATEV-'.$club->id;
            $invoice = \App\Models\Invoice::create(['club_id' => $club->id, 'user_id' => $actor->id, 'title' => 'Testbeitrag',
                'number' => $number, 'amount' => 47.25, 'status' => 'paid', 'due_date' => now()->subDay()]);
            $invoice->payments()->create(['club_id' => $club->id, 'user_id' => $actor->id, 'purpose' => 'Testbeitrag',
                'amount' => 47.25, 'status' => 'paid', 'method' => 'manual', 'paid_at' => now()]);
            foreach ([false, true] as $permission) {
                $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['finance.export' => $permission]]]);
                $this->actingAs($actor->fresh());
                foreach (['auth.club-memberships.datev-export', 'api.v1.clubs.membership.datev-export'] as $route) {
                    $export = $this->getJson(route($route, $club));
                    if (! $permission) {
                        $export->assertForbidden();
                    } elseif (! in_array($clubSlug, ['pro', 'elite'], true)) {
                        $export->assertUnprocessable()->assertJsonValidationErrors('general');
                    } else {
                        $csv = $export->assertOk()->assertDownload()->streamedContent();
                        $this->assertStringContainsString($number, $csv);
                        $this->assertStringContainsString('47,25', $csv);
                    }
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_bank_import_and_confirmation(string $slug): void
    {
        $actor = $this->accountForPlan($slug);
        foreach ($actor->clubs()->exists() ? [$slug] : ['club', 'pro'] as $clubSlug) {
            $club = Club::factory()->create();
            $club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => SubscriptionPlan::where('slug', $clubSlug)->firstOrFail()->id,
                'status' => 'active', 'billing_interval' => 'monthly']);
            foreach (['web', 'api'] as $surface) {
                $api = $surface === 'api';
                $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['finance.edit' => true, 'finance.manage' => $api]]]);
                $this->actingAs($actor->fresh());
                $number = "RE-$clubSlug-$surface";
                $invoice = \App\Models\Invoice::create(['club_id' => $club->id, 'user_id' => $actor->id, 'number' => $number,
                    'title' => 'Beitrag', 'status' => 'open', 'amount' => 32, 'due_date' => now()->addDay()]);
                $url = route($api ? 'api.v1.clubs.bank-transactions.import' : 'auth.club-memberships.bank-transactions.import', $club);
                $csv = "Buchungstag;Betrag;Verwendungszweck\n".now()->toDateString().";32,00;$number";
                $result = $this->postJson($url, ['file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('bank.csv', $csv)]);
                $key = ['club_id' => $club->id, 'purpose' => $number];
                if (! in_array($clubSlug, ['pro', 'elite'], true)) {
                    $result->assertUnprocessable()->assertJsonValidationErrors('general');
                    $this->assertDatabaseMissing('bank_transactions', $key);
                    continue;
                }
                $api ? $result->assertCreated() : $result->assertRedirect()->assertSessionHasNoErrors();
                $transaction = \App\Models\BankTransaction::where($key)->sole();
                $this->assertEquals($invoice->id, $transaction->invoice_id);
                $this->assertNull($transaction->payment_id);
                $confirm = $api ? route('api.v1.clubs.bank-transactions.confirm', [$club, $transaction]) : route('auth.club-memberships.bank-transactions.confirm', $transaction);
                $result = $this->postJson($confirm);
                $api ? $result->assertOk() : $result->assertRedirect()->assertSessionHasNoErrors();
                $this->assertSame('matched', $transaction->fresh()->status);
                $this->assertSame('paid', $invoice->fresh()->status);
                $this->assertEquals($invoice->payments()->sole()->id, $transaction->fresh()->payment_id);
            }
        }
    }

    #[DataProvider('plans')]
    public function test_sepa_xml_by_tariff_and_export_right(string $slug): void
    {
        $actor = $this->accountForPlan($slug);
        foreach ($actor->clubs()->exists() ? [$slug] : ['club', 'pro'] as $clubSlug) {
            $club = Club::factory()->create(['sepa_creditor_id' => 'DE98ZZZ09999999999', 'sepa_iban' => 'DE02120300000000202051']);
            $club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => SubscriptionPlan::where('slug', $clubSlug)->firstOrFail()->id,
                'status' => 'active', 'billing_interval' => 'monthly']);
            $member = User::factory()->create();
            $club->users()->attach($member, ['role' => 'member', 'membership_status' => 'active', 'sepa_iban' => 'DE12500105170648489890',
                'sepa_mandate_reference' => 'TEST-MANDAT', 'sepa_mandate_signed_on' => now()->subMonth(), 'sepa_mandate_active' => true]);
            $number = 'TEST-SEPA-'.$club->id;
            \App\Models\Invoice::create(['club_id' => $club->id, 'user_id' => $member->id, 'number' => $number,
                'title' => 'Testbeitrag', 'amount' => 19.90, 'status' => 'open', 'due_date' => now()->addWeek()]);
            foreach ([false, true] as $permission) {
                $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['finance.export' => $permission]]]);
                $this->actingAs($actor->fresh());
                foreach (['auth.club-memberships.sepa-export', 'api.v1.clubs.membership.sepa-export'] as $route) {
                    $response = $this->getJson(route($route, $club));
                    if (! $permission) {
                        $response->assertForbidden();
                    } elseif (! in_array($clubSlug, ['pro', 'elite'], true)) {
                        $response->assertUnprocessable()->assertJsonValidationErrors('general');
                    } else {
                        $response->assertOk()->assertHeader('content-type', 'application/xml; charset=UTF-8')->assertSee($number);
                        $xml = simplexml_load_string($response->getContent());
                        $this->assertNotFalse($xml);
                        $this->assertSame('19.90', (string) $xml->xpath('//*[local-name()="InstdAmt"]')[0]);
                        $this->assertSame('TEST-MANDAT', (string) $xml->xpath('//*[local-name()="MndtId"]')[0]);
                    }
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_club_audit_history_in_membership_and_billing(string $slug): void
    {
        $actor = $this->accountForPlan($slug);
        $club = $actor->clubs()->first() ?? Club::factory()->create();
        $other = Club::factory()->create();
        $entry = \App\Support\ClubAuditLog::record($club, $actor, 'club.invoice.reminder_sent');
        \App\Support\ClubAuditLog::record($other, $other->owner, 'club.invoice.reminder_sent');
        foreach (['none', 'finance.view', 'members.manage', 'members.roles'] as $right) {
            $rights = ['finance.view' => false, 'members.manage' => false, 'members.roles' => false];
            if ($right !== 'none') {
                $rights[$right] = true;
            }
            $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active', 'permission_overrides' => $rights]]);
            $this->actingAs($actor->fresh());
            $web = $this->get(route('auth.club-memberships.index'));
            $api = $this->getJson("/api/v1/clubs/{$club->id}/billing");
            if ($right === 'none') {
                $web->assertForbidden();
            } else {
                $web->assertOk()->assertInertia(fn ($page) => $page->has('clubs', 1)
                    ->has('clubs.0.audit_logs', 1)->where('clubs.0.audit_logs.0.id', $entry->id));
            }
            if ($right === 'finance.view') {
                $api->assertOk()->assertJsonCount(1, 'data.audit_logs')->assertJsonPath('data.audit_logs.0.id', $entry->id);
            } else {
                $api->assertForbidden();
            }
        }
    }

    #[DataProvider('plans')]
    public function test_multiple_club_locations_by_permission(string $slug): void
    {
        $actor = $this->accountForPlan($slug);
        $club = $actor->clubs()->first() ?? Club::factory()->create();
        $base = "/api/v1/clubs/{$club->id}/organization/locations";
        $rights = function (bool $edit, bool $delete) use ($actor, $club): void {
            $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active',
                'permission_overrides' => ['organization.edit' => $edit, 'organization.delete' => $delete]]]);
            $this->actingAs($actor->fresh());
        };
        $address = ['country' => 'DE', 'city' => 'Köln', 'postal_code' => '50667', 'street' => 'Testweg', 'house_number' => '1', 'is_public' => false];
        $rights(true, false);
        $first = $this->postJson($base, ['name' => 'Halle Nord'] + $address)->assertCreated()->json('data.id');
        $second = $this->postJson($base, ['name' => 'Halle Süd'] + $address)->assertCreated()->json('data.id');
        $this->assertNotEquals($first, $second);
        $this->putJson("$base/$second", ['name' => 'Halle West'] + $address)->assertOk()->assertJsonPath('data.name', 'Halle West');
        $this->deleteJson("$base/$second")->assertForbidden();
        $rights(false, true);
        $this->postJson($base, ['name' => 'Halle Ost'] + $address)->assertForbidden();
        $this->putJson("$base/$second", ['name' => 'Halle Ost'] + $address)->assertForbidden();
        $this->assertDatabaseHas('club_locations', ['id' => $second, 'club_id' => $club->id, 'name' => 'Halle West']);
        $this->deleteJson("$base/$second")->assertOk();
        $this->assertDatabaseMissing('club_locations', ['id' => $second]);
        $team = \App\Models\Team::factory()->create(['club_id' => $club->id, 'club_location_id' => $first]);
        $this->deleteJson("$base/$first")->assertUnprocessable()->assertJsonValidationErrors('location');
        $this->assertSame((int) $first, (int) $team->fresh()->club_location_id);
        $this->assertDatabaseHas('club_locations', ['id' => $first, 'club_id' => $club->id, 'name' => 'Halle Nord']);
    }

    #[DataProvider('plans')]
    public function test_club_training_group_lifecycle(string $slug): void
    {
        $actor = $this->accountForPlan($slug);
        $club = $actor->clubs()->first() ?? Club::factory()->create();
        $location = $club->locations()->create(['name' => 'Nord', 'country' => 'DE', 'is_public' => false]);
        $base = "/api/v1/clubs/{$club->id}/organization/training-groups";
        $data = ['name' => 'Jugendtraining', 'club_location_id' => $location->id, 'is_public' => false];
        $actor->assignRole(\Spatie\Permission\Models\Role::findOrCreate('coach', 'web'));
        $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active',
            'permission_overrides' => ['organization.edit' => false, 'organization.delete' => false]]]);
        $this->actingAs($actor->fresh())->postJson($base, $data)->assertForbidden();
        $this->assertDatabaseMissing('club_training_groups', ['club_id' => $club->id, 'name' => $data['name']]);
        $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active',
            'permission_overrides' => ['organization.edit' => true, 'organization.delete' => false]]]);
        $this->actingAs($actor->fresh());
        $id = $this->postJson($base, $data + ['club_id' => null])->assertCreated()->json('data.id');
        $this->assertDatabaseHas('club_training_groups', ['id' => $id, 'club_id' => $club->id]);
        $this->assertDatabaseMissing('club_training_groups', ['club_id' => null, 'name' => $data['name']]);
        $data['name'] = 'Jugendtraining Mittwoch';
        $this->putJson("$base/$id", $data)->assertOk();
        $this->assertDatabaseHas('club_training_groups', ['id' => $id, 'club_id' => $club->id, 'name' => $data['name'], 'club_location_id' => $location->id]);
        $this->deleteJson("$base/$id")->assertForbidden();
        $club->users()->updateExistingPivot($actor->id, ['permission_overrides' => ['organization.edit' => false, 'organization.delete' => true]]);
        $this->actingAs($actor->fresh());
        $this->putJson("$base/$id", $data)->assertForbidden();
        $this->postJson($base, array_merge($data, ['name' => 'Weitere Gruppe']))->assertForbidden();
        $team = \App\Models\Team::factory()->create(['club_id' => $club->id, 'club_training_group_id' => $id]);
        $this->deleteJson("$base/$id")->assertUnprocessable()->assertJsonValidationErrors('training_group');
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'club_training_group_id' => $id]);
        $team->update(['club_training_group_id' => null]);
        $this->deleteJson("$base/$id")->assertOk();
        $this->assertDatabaseMissing('club_training_groups', ['id' => $id]);
    }

    #[DataProvider('plans')]
    public function test_public_club_profile_and_mobile_directory(string $slug): void
    {
        $viewer = $this->accountForPlan($slug);
        $club = $viewer->clubs()->first() ?? Club::factory()->create();
        $club->update(['name' => 'Tarifprofil A', 'verification_status' => 'verified', 'is_listed' => true]);
        $hidden = Club::factory()->create(['name' => 'Tarifprofil B', 'verification_status' => 'verified', 'is_listed' => false]);
        $pending = Club::factory()->create(['name' => 'Tarifprofil C', 'verification_status' => 'pending', 'is_listed' => true]);
        foreach (['guest', 'member'] as $role) {
            if ($role === 'member') {
                $this->actingAs($viewer);
            }
            $this->get(route('guest.clubs.show', $club))->assertOk()->assertInertia(fn ($page) => $page
                ->where('entity.name', $club->name)->missing('entity.sepa_iban'));
            $this->get(route('guest.clubs.show', $hidden))->assertNotFound();
            $this->get(route('guest.clubs.show', $pending))->assertNotFound();
            $this->getJson('/api/v1/public/clubs?q=Tarifprofil')->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $club->id)->assertJsonMissingPath('data.0.sepa_iban');
        }
    }

    #[DataProvider('plans')]
    public function test_personal_event_directory_visibility(string $slug): void
    {
        $viewer = $this->accountForPlan($slug);
        $other = User::factory()->create();
        foreach ([$viewer, $other] as $owner) {
            $record = \App\Models\Event::create(['user_id' => $owner->id, 'title' => 'Tariftermin '.$owner->id, 'visibility' => 'private',
                'type' => 'training', 'status' => 'scheduled', 'start_time' => now()->addDay(), 'end_time' => now()->addDay()->addHour()]);
            if ($owner->is($viewer)) {
                $own = $record;
            } else {
                $foreign = $record;
            }
        }
        $this->actingAs($viewer);
        $this->get(route('auth.events.index'))->assertOk()->assertInertia(fn ($page) => $page->has('events.data', 1)->where('events.data.0.id', $own->id));
        $this->getJson('/api/v1/events')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->get(route('auth.events.show', $own))->assertOk();
        $this->getJson('/api/v1/events/'.$own->id)->assertOk()->assertJsonPath('data.id', $own->id);
        $this->getJson(route('auth.events.show', $foreign))->assertForbidden();
        $this->getJson('/api/v1/events/'.$foreign->id)->assertNotFound();
    }

    #[DataProvider('plans')]
    public function test_private_event_lifecycle(string $slug): void
    {
        $owner = $this->accountForPlan($slug);
        $other = User::factory()->create();
        $club = $owner->clubs()->first() ?? Club::factory()->create();
        $club->users()->syncWithoutDetaching([$owner->id => ['role' => 'member', 'membership_status' => 'active',
            'permission_overrides' => ['events.edit' => true, 'events.delete' => true]]]);
        $team = \App\Models\Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($owner, ['role' => 'member']);
        foreach (['web', 'api'] as $surface) {
            $this->actingAs($owner->fresh());
            $club->users()->updateExistingPivot($owner->id, ['permission_overrides' => ['events.edit' => true, 'events.delete' => false]]);
            $this->actingAs($owner->fresh());
            $api = $surface === 'api';
            $base = $api ? '/api/v1/events' : route('auth.events.store');
            $data = ['title' => "Privater Termin $surface", 'type' => 'meeting', 'visibility' => 'private',
                'start_time' => now()->addDay()->toISOString()];
            $this->postJson($base, $data)->assertUnprocessable();
            $data['team_id'] = $team->id;
            $data['qr_check_in_enabled'] = true;
            $created = $this->postJson($base, $data);
            $api ? $created->assertSuccessful() : $created->assertRedirect()->assertSessionHasNoErrors();
            $event = \App\Models\Event::where('user_id', $owner->id)->where('title', $data['title'])->sole();
            $this->assertTrue((bool) $event->qr_check_in_enabled);
            $member = User::factory()->create();
            $club->users()->attach($member, ['role' => 'member', 'membership_status' => 'active']);
            $team->users()->attach($member, ['role' => 'member']);
            $qr = "/api/v1/events/{$event->id}";
            $this->actingAs(User::findOrFail($club->owner_id));
            $token = $this->postJson($qr.'/check-in-tokens', ['user_id' => $member->id, 'device_id' => 'testgeraet'])
                ->assertCreated()->json('data.token');
            $this->actingAs($member);
            $this->postJson($qr.'/check-in', ['token' => $token, 'device_id' => 'testgeraet'])
                ->assertOk()->assertJsonPath('data.checked_in', true);
            $this->assertDatabaseHas('event_participants', ['event_id' => $event->id, 'user_id' => $member->id, 'attendance_status' => 'present']);
            $item = $base.'/'.$event->id;
            $data['title'] .= ' verschoben';
            $this->actingAs($other);
            $this->assertContains($this->putJson($item, $data)->status(), [403, 404]);
            $this->assertContains($this->deleteJson($item)->status(), [403, 404]);
            $this->assertSame($event->title, $event->fresh()->title);
            $this->actingAs($owner->fresh());
            $updated = $this->putJson($item, $data);
            $api ? $updated->assertSuccessful() : $updated->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame($data['title'], $event->fresh()->title);
            $this->deleteJson($item)->assertForbidden();
            $club->users()->updateExistingPivot($owner->id, ['permission_overrides' => ['events.edit' => false, 'events.delete' => true]]);
            $this->actingAs($owner->fresh());
            $this->putJson($item, $data)->assertForbidden();
            $deleted = $this->deleteJson($item);
            $api ? $deleted->assertSuccessful() : $deleted->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseMissing('events', ['id' => $event->id]);
        }
    }

    #[DataProvider('plans')]
    public function test_public_event_monthly_limit_by_account(string $slug): void
    {
        foreach (['web', 'api'] as $surface) {
            $actor = $this->accountForPlan($slug);
            $this->actingAs($actor);
            $limited = $actor->clubs()->exists();
            $api = $surface === 'api';
            $this->getJson('/api/v1/events')->assertOk()->assertJsonPath('event_creation.monthly_limit', $limited ? 2 : null);
            for ($i = 1; $i <= 3; $i++) {
                $data = ['title' => "Termin $i", 'type' => 'meeting', 'visibility' => 'public', 'start_time' => now()->addDay()->toISOString()];
                $response = $this->postJson($api ? '/api/v1/events' : route('auth.events.store'), $data);
                if ($limited && $i === 3) {
                    $response->assertForbidden();
                    $this->assertDatabaseMissing('events', ['user_id' => $actor->id, 'title' => $data['title']]);
                } else {
                    $api ? $response->assertSuccessful() : $response->assertRedirect()->assertSessionHasNoErrors();
                    $this->assertDatabaseHas('events', ['user_id' => $actor->id, 'title' => $data['title'], 'club_id' => null, 'team_id' => null]);
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_club_dashboard_right_and_web_plan_lock(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $club = $user->clubs()->first() ?? Club::factory()->create();
        $clubPlans = in_array($slug, ['free', 'starter', 'club', 'pro', 'elite'], true)
            ? [$slug] : ['free', 'starter', 'club', 'pro', 'elite'];
        foreach ($clubPlans as $clubPlan) {
            $proFeatures = in_array($clubPlan, ['pro', 'elite'], true);
            $expected = [
                'media_rights' => $proFeatures, 'material_management' => $proFeatures,
                'training_load_analysis' => $proFeatures, 'injury_risk_warnings' => $proFeatures,
                'ai_rule_review' => $proFeatures, 'member_onboarding' => $clubPlan !== 'free',
            ];
            $club->currentSubscription()->updateOrCreate([], [
                'subscription_plan_id' => SubscriptionPlan::where('slug', $clubPlan)->sole()->id,
                'status' => 'active', 'billing_interval' => 'monthly',
            ]);
            foreach ([false, true] as $allowed) {
                $club->users()->syncWithoutDetaching([$user->id => [
                    'role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['cockpit.view' => $allowed],
                ]]);
                $this->actingAs($user->fresh());
                $web = $this->get(route('auth.club-cockpit.index'));
                if ($allowed) {
                    $web->assertOk()->assertInertia(fn ($page) => $page
                        ->where('clubs.0.id', $club->id)
                        ->where('clubs.0.plan.slug', $clubPlan)
                        ->where('clubs.0.capabilities.sponsor_crm', $clubPlan === 'elite')
                        ->where('clubs.0.capabilities.custom_rules', $clubPlan === 'elite')
                        ->where('clubs.0.capabilities', fn ($actual) => collect($actual)->only(array_keys($expected))->sortKeys()->all()
                            === collect($expected)->sortKeys()->all())
                        ->where('clubs.0.locked', in_array($clubPlan, ['free', 'starter'], true)));
                } else {
                    $web->assertForbidden();
                }
                $detail = $this->getJson("/api/v1/clubs/{$club->id}")->assertOk()
                    ->assertJsonPath('data.subscription_capabilities.sponsor_crm', $clubPlan === 'elite')
                    ->assertJsonPath('data.subscription_capabilities.custom_rules', $clubPlan === 'elite')
                    ->assertJsonPath('data.can_view_cockpit', $allowed);
                foreach ($expected as $feature => $enabled) {
                    $detail->assertJsonPath("data.subscription_capabilities.$feature", $enabled);
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_team_chat_message_from_team_member(string $slug): void
    {
        $member = $this->accountForPlan($slug);
        $club = $member->clubs()->first() ?? Club::factory()->create();
        $recipient = User::factory()->create();
        $this->actingAs($member);
        foreach (['web', 'api'] as $surface) {
            $team = \App\Models\Team::factory()->create(['club_id' => $club->id]);
            $team->users()->attach($recipient, ['role' => 'Player']);
            $url = $surface === 'web' ? route('auth.conversations.store') : '/api/v1/chat/conversations';
            $data = ['type' => 'team', 'team_id' => $team->id, 'message' => "Training am Mittwoch ($surface)"];
            $this->postJson($url, $data)->assertNotFound();
            $this->assertDatabaseMissing('conversations', ['team_id' => $team->id]);
            $team->users()->attach($member, ['role' => 'Player']);
            $response = $this->postJson($url, $data);
            $conversation = \App\Models\Conversation::where('team_id', $team->id)->sole();
            if ($surface === 'web') {
                $response->assertRedirect(route('auth.conversations.index', ['conversation' => $conversation->id]));
            } else {
                $response->assertCreated()->assertJsonPath('data.id', $conversation->id);
            }
            $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'message' => $data['message']]);
            $this->assertDatabaseHas('conversation_users', ['conversation_id' => $conversation->id, 'user_id' => $recipient->id]);
        }
    }

    #[DataProvider('plans')]
    public function test_sport_track_lifecycle(string $slug): void
    {
        $member = $this->accountForPlan($slug);
        $other = User::factory()->create();
        foreach (['web', 'api'] as $surface) {
            $this->actingAs($member);
            $api = $surface === 'api';
            $url = $api ? '/api/v1/sport-tracks' : route('auth.sport-tracks.store');
            $data = ['title' => 'Trainingsstrecke', 'status' => 'recording',
                'track_points' => [['latitude' => 50.0, 'longitude' => 8.0]]];
            $response = $this->postJson($url, $data);
            $api ? $response->assertCreated() : $response->assertRedirect()->assertSessionHasNoErrors();
            $track = \App\Models\SportRouteTrack::where('user_id', $member->id)->sole();
            $item = $url.'/'.$track->id;
            $method = $api ? 'PATCH' : 'PUT';
            $this->actingAs($other);
            $this->json($method, $item, ['title' => 'Fremde Änderung'])->assertForbidden();
            $this->deleteJson($item)->assertForbidden();
            $this->assertSame('Trainingsstrecke', $track->fresh()->title);
            $this->actingAs($member);
            $response = $this->json($method, $item, ['title' => 'Abendrunde']);
            $api ? $response->assertOk() : $response->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('Abendrunde', $track->fresh()->title);
            if ($api) {
                $this->postJson($item.'/points', ['track_points' => [['latitude' => 50.001, 'longitude' => 8.001]]])->assertOk();
                $this->assertCount(2, $track->fresh()->track_points);
                $this->postJson($item.'/complete')->assertOk();
                $this->assertSame('completed', $track->fresh()->status);
            }
            $response = $this->deleteJson($item);
            $api ? $response->assertOk() : $response->assertRedirect();
            $this->assertSoftDeleted($track);
        }
    }

    #[DataProvider('plans')]
    public function test_route_generation_quota_on_web_and_api(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $this->actingAs($user);
        $limit = in_array($slug, ['sportler-pro', 'trainer-pro', 'club', 'pro', 'elite'], true) ? 150 : 10;
        config(['sport_map.routing.route_generator_provider' => 'graphhopper',
            'sport_map.routing.graphhopper_mode' => 'cloud',
            'sport_map.routing.graphhopper_api_key' => 'test-key']);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['paths' => [[
            'distance' => 5000, 'time' => 1800000,
            'points' => ['coordinates' => [[7.1, 49.1], [7.12, 49.12], [7.1, 49.1]]], 'instructions' => [],
        ]]])]);
        $payload = ['sport_type' => 'running', 'route_type' => 'roundtrip', 'target_mode' => 'distance',
            'distance_km' => 5, 'start' => ['latitude' => 49.1, 'longitude' => 7.1]];
        $event = ['user_id' => $user->id, 'area' => 'routing', 'service' => 'directions', 'operation' => 'route',
            'provider' => 'graphhopper', 'occurred_at' => now()];
        \App\Models\ExternalProviderUsageEvent::insert(array_fill(0, $limit - 2, $event));
        $urls = [route('auth.sport-route-proposals.store'), '/api/v1/sport-route-proposals'];
        foreach ($urls as $url) {
            $this->postJson($url, $payload)->assertOk()->assertJsonStructure(['data']);
        }
        foreach ($urls as $url) {
            $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('route_generation');
        }
        \Illuminate\Support\Facades\Http::assertSentCount(2);
        $this->travelTo(now()->addMonthNoOverflow()->startOfMonth());
        $this->postJson($urls[1], $payload)->assertOk();
    }

    #[DataProvider('plans')]
    public function test_personal_sport_profile_lifecycle(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $other = User::factory()->create();
        $sport = \App\Models\Sport::create(['name' => 'Laufen', 'slug' => 'tariff-running',
            'category' => 'endurance', 'is_active' => true]);
        $initial = ['status' => 'active', 'experience_level' => 'beginner', 'visibility' => 'private'];
        $otherKey = ['user_id' => $other->id, 'sport_id' => $sport->id];
        $ownKey = ['user_id' => $user->id, 'sport_id' => $sport->id];
        $api = "/api/v1/sport-profiles/{$sport->id}";
        $this->actingAs($other)->putJson($api, $initial)->assertOk();
        $this->actingAs($user);
        foreach ([route('auth.settings.sport-profiles.update', $sport), $api] as $url) {
            foreach (['private', 'trainer', 'public'] as $visibility) {
                $data = array_merge($initial, ['visibility' => $visibility]);
                $response = $this->putJson($url, $data);
                $url === $api ? $response->assertOk() : $response->assertRedirect()->assertSessionHasNoErrors();
                $this->assertDatabaseHas('user_sports', $ownKey + $data);
                $this->assertDatabaseHas('user_sports', $otherKey + $initial);
            }
            $response = $this->deleteJson($url);
            $url === $api ? $response->assertOk() : $response->assertRedirect();
            $this->assertDatabaseMissing('user_sports', $ownKey);
            $this->assertDatabaseHas('user_sports', $otherKey + $initial);
        }
    }

    #[DataProvider('plans')]
    public function test_club_inventory_item_rights(string $slug): void
    {
        config(['filesystems.uploads_disk' => 'public']);
        $disk = \Illuminate\Support\Facades\Storage::fake('public');
        $member = $this->accountForPlan($slug);
        $club = $member->clubs()->first() ?? Club::factory()->create();
        $url = "/api/v1/clubs/{$club->id}/inventory";
        $data = ['name' => 'Trainingsball', 'sku' => 'BALL-01', 'quantity_total' => 3,
            'condition' => 'good', 'status' => 'active', 'is_loanable' => true];
        $existing = \App\Models\ClubInventoryItem::create(array_merge($data, [
            'sku' => 'BALL-02', 'club_id' => $club->id, 'quantity_available' => 3,
        ]));
        foreach (['view', 'edit', 'delete'] as $right) {
            $club->users()->syncWithoutDetaching([$member->id => ['role' => 'member', 'membership_status' => 'active',
                'permission_overrides' => ['inventory.view' => true, 'inventory.edit' => $right === 'edit',
                    'inventory.delete' => $right === 'delete'] ]]);
            $this->actingAs($member->fresh());
            $this->get(route('auth.club-inventory.index'))->assertOk()
                ->assertInertia(fn ($page) => $page->where('clubs.0.id', $club->id));
            $this->getJson($url)->assertOk()->assertJsonFragment(['sku' => 'BALL-02']);
            $imageUrl = "$url/{$existing->id}/image";
            $upload = $this->postJson($imageUrl, ['image' => \Illuminate\Http\UploadedFile::fake()->image('ball.jpg')]);
            if ($right === 'edit') {
                $upload->assertOk();
                $path = $existing->fresh()->image_path;
                $disk->assertExists($path);
                $this->deleteJson($imageUrl)->assertOk();
                $this->assertNull($existing->fresh()->image_path);
                $disk->assertMissing($path);
            } else {
                $upload->assertForbidden();
                $this->deleteJson($imageUrl)->assertForbidden();
            }
            if ($right === 'view') {
                $this->postJson($url, $data)->assertForbidden();
                $this->putJson("$url/{$existing->id}", array_merge($data, ['name' => 'Geändert']))->assertForbidden();
                $this->deleteJson("$url/{$existing->id}")->assertForbidden();
                $this->assertSame('Trainingsball', $existing->fresh()->name);
                $this->assertDatabaseMissing('club_inventory_items', ['club_id' => $club->id, 'sku' => 'BALL-01']);
            } elseif ($right === 'edit') {
                $id = $this->postJson($url, $data)->assertCreated()->json('data.id');
                $data['name'] = 'Spielball';
                $this->putJson("$url/$id", $data)->assertOk();
                $this->deleteJson("$url/$id")->assertForbidden();
                $this->assertDatabaseHas('club_inventory_items', ['id' => $id, 'name' => 'Spielball']);
            } else {
                $this->postJson($url, array_merge($data, ['sku' => 'BALL-03']))->assertForbidden();
                $this->assertDatabaseMissing('club_inventory_items', ['club_id' => $club->id, 'sku' => 'BALL-03']);
                $this->putJson("$url/$id", $data)->assertForbidden();
                $this->deleteJson("$url/$id")->assertOk()->assertJsonPath('data.deleted', true);
                $this->assertNull(\App\Models\ClubInventoryItem::find($id));
            }
        }
    }

    #[DataProvider('plans')]
    public function test_season_planning_requires_club_plan_and_team_right(string $slug): void
    {
        $actor = $this->accountForPlan($slug);
        $club = $actor->clubs()->first() ?? Club::factory()->create();
        $team = \App\Models\Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($actor, ['role' => 'Player']);
        $period = \App\Models\ClubYearPeriod::create(['club_id' => $club->id, 'type' => 'sport',
            'name' => 'Saison', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31']);
        $data = ['name' => 'Herbst', 'sport_year_period_id' => $period->id, 'status' => 'draft',
            'phases' => [['name' => 'Vorbereitung', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31']], 'event_ids' => []];
        $base = "/api/v1/teams/{$team->id}/season-plans";
        $published = \App\Models\SeasonPlan::create(['team_id' => $team->id, 'sport_year_period_id' => $period->id,
            'name' => 'Bestandsplan', 'goals' => 'Technik', 'status' => 'published', 'phases' => $data['phases'],
            'revision' => 1, 'updated_by' => $actor->id]);
        $plans = in_array($slug, ['free', 'starter', 'club', 'pro', 'elite'], true) ? [$slug] : ['free', 'pro'];
        foreach ($plans as $plan) {
            $club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => SubscriptionPlan::where('slug', $plan)->sole()->id,
                'status' => 'active', 'billing_interval' => 'monthly']);
            foreach ([false, true] as $edit) {
                $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['training_plans.edit' => $edit]]]);
                $this->actingAs($actor->fresh());
                $enabled = in_array($plan, ['pro', 'elite'], true);
                $this->getJson($base)->assertOk()->assertJsonPath('data.can_edit', $edit && $enabled)
                    ->assertJsonPath('data.plans.0.id', $published->id);
                if (! $edit || ! $enabled) {
                    $entry = "$base/{$published->id}";
                    $status = $edit ? 422 : 403;
                    $this->putJson($entry, $data + ['revision' => 1, 'goals' => 'Ausdauer'])->assertStatus($status);
                    $this->deleteJson($entry)->assertStatus($status);
                    $this->assertDatabaseHas('season_plans', ['id' => $published->id, 'goals' => 'Technik', 'revision' => 1]);
                }
                $response = $this->postJson($base, $data);
                if (! $edit) {
                    $response->assertForbidden();
                } elseif (! $enabled) {
                    $response->assertUnprocessable()->assertJsonValidationErrors('general');
                } else {
                    $id = $response->assertCreated()->json('data.id');
                    $this->putJson("$base/$id", $data + ['revision' => 1, 'goals' => 'Ausdauer'])->assertOk();
                    $this->assertDatabaseHas('season_plans', ['id' => $id, 'goals' => 'Ausdauer']);
                    $this->deleteJson("$base/$id")->assertOk();
                }
                $this->assertDatabaseMissing('season_plans', ['team_id' => $team->id, 'name' => 'Herbst']);
            }
        }
    }

    #[DataProvider('plans')]
    public function test_room_booking_and_cancellation_for_a_club_member(string $slug): void
    {
        $member = $this->accountForPlan($slug);
        $club = $member->clubs()->first() ?? Club::factory()->create();
        $club->users()->syncWithoutDetaching([$member->id => ['role' => 'member', 'membership_status' => 'active']]);
        $base = "/api/v1/clubs/{$club->id}/inventory";
        $start = now()->addDays(2)->startOfHour();
        $window = ['quantity' => 1, 'starts_at' => $start->toIso8601String(), 'due_at' => $start->copy()->addHour()->toIso8601String()];
        foreach ([false, true] as $approval) {
            $room = \App\Models\ClubInventoryItem::create(['club_id' => $club->id, 'name' => 'Sporthalle',
                'resource_type' => 'room', 'quantity_total' => 1, 'quantity_available' => 1, 'status' => 'active',
                'condition' => 'good', 'requires_approval' => $approval, 'is_loanable' => true]);
            $this->actingAs($member);
            $id = $this->postJson("$base/{$room->id}/checkout", $window)->assertCreated()
                ->assertJsonPath('data.status', $approval ? 'pending' : 'active')->json('data.id');
            $cancel = "$base/loans/$id/request";
            if ($approval) {
                $this->deleteJson($cancel)->assertOk()->assertJsonPath('data.status', 'cancelled');
            } else {
                $this->postJson("$base/{$room->id}/checkout", $window)->assertUnprocessable();
                $this->deleteJson($cancel)->assertUnprocessable();
                $this->assertDatabaseHas('club_inventory_loans', ['id' => $id, 'status' => 'active']);
            }
        }
    }

    #[DataProvider('plans')]
    public function test_room_decision_rights(string $slug): void
    {
        $reviewer = $this->accountForPlan($slug);
        $club = $reviewer->clubs()->first() ?? Club::factory()->create();
        $borrower = User::factory()->create();
        $club->users()->attach($borrower, ['role' => 'member', 'membership_status' => 'active']);
        $base = "/api/v1/clubs/{$club->id}/inventory";
        foreach (['approve' => 'active', 'reject' => 'rejected'] as $action => $status) {
            $room = \App\Models\ClubInventoryItem::create(['club_id' => $club->id, 'name' => 'Halle',
                'resource_type' => 'room', 'quantity_total' => 1, 'quantity_available' => 1, 'status' => 'active',
                'condition' => 'good', 'requires_approval' => true, 'is_loanable' => true]);
            $this->actingAs($borrower);
            $id = $this->postJson("$base/{$room->id}/checkout", ['quantity' => 1,
                'starts_at' => now()->addDays(2)->startOfHour(), 'due_at' => now()->addDays(2)->startOfHour()->addHour()])
                ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
            foreach ([false, true] as $allowed) {
                $club->users()->syncWithoutDetaching([$reviewer->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['inventory.approve' => $allowed]]]);
                $this->actingAs($reviewer->fresh());
                $result = $this->postJson("$base/loans/$id/$action");
                if ($allowed) {
                    $result->assertOk()->assertJsonPath('data.status', $status);
                } else {
                    $result->assertForbidden();
                }
                $this->assertDatabaseHas('club_inventory_loans', ['id' => $id, 'status' => $allowed ? $status : 'pending']);
            }
        }
    }

    #[DataProvider('plans')]
    public function test_own_sponsor_brand_profile(string $slug): void
    {
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        foreach (['auth', 'api.v1'] as $surface) {
            $user = $this->accountForPlan($slug);
            $url = route($surface.'.sponsor-workspace.profile.update');
            $this->actingAs($user);
            $this->putJson($url, ['name' => 'Erste Marke'])->assertForbidden();
            $this->assertDatabaseMissing('sponsors', ['owner_user_id' => $user->id]);
            $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('sponsor', 'web'));
            $this->actingAs($user->fresh());
            foreach (['Erste Marke', 'Neue Marke'] as $name) {
                $result = $this->putJson($url, ['name' => $name, 'email' => $user->email]);
                $surface === 'auth' ? $result->assertRedirect()->assertSessionHasNoErrors() : $result->assertOk();
                $profile = \App\Models\Sponsor::where('owner_user_id', $user->id)->sole();
                $this->assertSame($name, $profile->name);
                $this->assertSame('pending_review', $profile->verification_status);
            }
        }
    }

    #[DataProvider('plans')]
    public function test_sponsor_campaign_reporting_by_tariff(string $slug): void
    {
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $user = $this->accountForPlan($slug);
        $this->actingAs($user);
        $web = route('auth.sponsor-workspace.index');
        $api = route('api.v1.sponsor-workspace.index');
        $this->get($web)->assertForbidden();
        $this->getJson($api)->assertForbidden();
        $user->assignRole('sponsor');
        foreach ([$user, User::factory()->create()] as $owner) {
            $brand = \App\Models\Sponsor::create(['owner_user_id' => $owner->id, 'scope' => 'platform',
                'name' => "Marke $owner->id", 'email' => $owner->email]);
            \App\Models\AdCampaign::create(['user_id' => $owner->id, 'sponsor_id' => $brand->id, 'name' => "Kampagne $owner->id",
                'status' => 'active', 'impressions' => 800, 'clicks' => 40]);
        }
        $this->actingAs($user->fresh());
        $this->get($web)->assertOk()->assertInertia(fn ($page) => $page
            ->has('workspace.campaigns', 1)->where('workspace.campaigns.0.name', "Kampagne $user->id")
            ->where('workspace.summary.impressions', 800)->where('workspace.summary.clicks', 40)->where('workspace.summary.ctr', 5));
        $this->getJson($api)->assertOk()->assertJsonCount(1, 'data.campaigns')
            ->assertJsonPath('data.campaigns.0.name', "Kampagne $user->id")->assertJsonPath('data.summary.impressions', 800)
            ->assertJsonPath('data.summary.clicks', 40)->assertJsonPath('data.summary.ctr', 5);
        $brand = \App\Models\Sponsor::where('owner_user_id', $user->id)->sole();
        foreach (['web', 'api'] as $surface) {
            $apiSurface = $surface === 'api';
            $base = $apiSurface ? '/api/v1/commerce/seller/campaigns' : route('auth.commerce.campaigns.store');
            $data = ['sponsor_id' => $brand->id, 'name' => "Markenaktion $surface", 'objective' => 'traffic',
                'placement' => 'sponsor_section', 'creative_format' => 'banner_wide', 'target_url' => 'https://example.test/sport',
                'budget_cents' => 2500, 'start_payment' => false];
            $result = $this->postJson($base, $data);
            $apiSurface ? $result->assertCreated() : $result->assertRedirect()->assertSessionHasNoErrors();
            $campaign = \App\Models\AdCampaign::where('name', $data['name'])->sole();
            $this->assertSame($brand->id, $campaign->sponsor_id);
            $this->assertNull($campaign->club_id);
            $this->assertSame('draft', $campaign->status);
            $data['name'] .= ' aktualisiert';
            $url = "$base/{$campaign->id}";
            ($apiSurface ? $this->putJson($url, $data) : $this->postJson($url, $data))->assertOk();
            $this->assertSame($data['name'], $campaign->fresh()->name);
            foreach (['viewer', 'finance', 'campaign_manager', 'administrator'] as $teamRole) {
                $staff = $this->accountForPlan($slug);
                $staff->assignRole('sponsor');
                \App\Models\SponsorMembership::create(['sponsor_id' => $brand->id, 'user_id' => $staff->id,
                    'email' => $staff->email, 'role' => $teamRole, 'status' => 'active']);
                $data['name'] = "Sommer $surface $teamRole";
                $this->actingAs($staff->fresh());
                $edit = $apiSurface ? $this->putJson($url, $data) : $this->postJson($url, $data);
                $draft = array_merge($data, ['name' => "Entwurf $surface $teamRole"]);
                $create = $this->postJson($base, $draft);
                if (in_array($teamRole, ['administrator', 'campaign_manager'], true)) {
                    $edit->assertOk();
                    $this->assertSame($data['name'], $campaign->fresh()->name);
                    $apiSurface ? $create->assertCreated() : $create->assertRedirect()->assertSessionHasNoErrors();
                    $ownDraft = \App\Models\AdCampaign::where('sponsor_id', $brand->id)->where('name', $draft['name'])->sole();
                    $this->assertSame('draft', $ownDraft->status);
                    $this->deleteJson("$base/{$ownDraft->id}", ['confirmation' => 'delete'])->assertOk();
                    $this->assertDatabaseMissing('ad_campaigns', ['id' => $ownDraft->id]);
                } else {
                    $edit->assertForbidden();
                    $create->assertForbidden();
                    $this->assertDatabaseMissing('ad_campaigns', ['name' => $draft['name']]);
                    $this->deleteJson($url, ['confirmation' => 'delete'])->assertForbidden();
                    $this->assertNotSame($data['name'], $campaign->fresh()->name);
                }
            }
            $this->actingAs($user);
            $this->deleteJson($url, ['confirmation' => 'delete'])->assertOk();
            $this->assertDatabaseMissing('ad_campaigns', ['id' => $campaign->id]);
        }
        foreach (['auth', 'api.v1'] as $prefix) {
            $club = Club::factory()->create(['owner_id' => $user->id]);
            $data = ['sponsor_id' => $brand->id, 'club_id' => $club->id, 'rights_package' => ['Banner']];
            $request = $this->postJson(route("$prefix.sponsor-workspace.partnerships.store"), $data);
            $prefix === 'auth' ? $request->assertRedirect()->assertSessionHasNoErrors() : $request->assertCreated();
            $partnership = \App\Models\SponsorPartnership::where('sponsor_id', $brand->id)->where('club_id', $club->id)->sole();
            $this->assertSame(\App\Models\SponsorPartnership::STATUS_PENDING, $partnership->status);
            $this->assertSame(['Banner'], $partnership->rights_package);
            $withdraw = $this->deleteJson(route("$prefix.sponsor-workspace.partnerships.withdraw", $partnership));
            $prefix === 'auth' ? $withdraw->assertRedirect() : $withdraw->assertSuccessful();
            $this->assertSame('withdrawn', $partnership->fresh()->status);
        }
        $reviewer = $this->accountForPlan($slug);
        $club = Club::factory()->create();
        $club->users()->attach($reviewer, ['role' => 'member', 'membership_status' => 'active',
            'permission_overrides' => ['sponsors.edit' => true]]);
        foreach (['approve' => 'active', 'reject' => 'rejected'] as $decision => $status) {
            $record = \App\Models\SponsorPartnership::create(['sponsor_id' => $brand->id, 'club_id' => $club->id,
                'created_by' => $user->id, 'status' => \App\Models\SponsorPartnership::STATUS_PENDING]);
            $this->actingAs($reviewer);
            $this->postJson("/admin/sponsor-partnerships/{$record->id}/decision", ['decision' => $decision])->assertForbidden();
            $url = route('api.v1.sponsor-management.partnerships.decision', $record);
            $this->postJson($url, ['decision' => $decision])->assertOk()->assertJsonPath('data.status', $status);
            $this->assertSame($reviewer->id, (int) $record->fresh()->responded_by);
            $this->postJson($url, ['decision' => $decision])->assertConflict();
        }
        $operator = $this->accountForPlan($slug);
        $operator->givePermissionTo('system.manage');
        $this->actingAs($operator->fresh());
        foreach (['approve' => 'active', 'reject' => 'rejected'] as $decision => $status) {
            $record = \App\Models\SponsorPartnership::create(['sponsor_id' => $brand->id, 'club_id' => $club->id,
                'created_by' => $user->id, 'status' => \App\Models\SponsorPartnership::STATUS_PENDING]);
            $this->post(route('sponsors.partnerships.decision', $record), ['decision' => $decision])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('sponsor_partnerships', ['id' => $record->id, 'status' => $status,
                'responded_by' => $operator->id]);
        }
    }

    #[DataProvider('plans')]
    public function test_feed_ad_measurement_by_tariff(string $slug): void
    {
        $viewer = $this->accountForPlan($slug);
        $this->withCookie(config('session.cookie'), \Illuminate\Support\Str::random(40))->withCredentials();
        $campaign = \App\Models\AdCampaign::create(['name' => 'Messung', 'objective' => 'traffic', 'placement' => 'feed',
            'creative_format' => 'feed_square', 'target_url' => 'https://example.test/sport', 'budget_cents' => 10000,
            'billing_event' => 'click', 'status' => 'active']);
        foreach ([false, true, true] as $consent) {
            $viewer->forceFill(['ads_measurement_consent' => $consent])->save();
            $this->actingAs($viewer);
            $this->getJson(route('ads.active', ['placement' => 'feed']))->assertOk()->assertJsonPath('id', $campaign->id);
            $this->get(route('ads.click', $campaign))->assertRedirect('https://example.test/sport');
            $campaign->refresh();
            $this->assertSame((int) $consent, (int) $campaign->impressions);
            $this->assertSame((int) $consent, (int) $campaign->clicks);
            $this->assertSame($consent ? 2 : 0, \App\Models\AdEvent::where('ad_campaign_id', $campaign->id)->count());
        }
    }

    #[DataProvider('plans')]
    public function test_provider_profile_and_pickup_locations(string $slug): void
    {
        foreach (['web', 'api'] as $surface) {
            $owner = $this->accountForPlan($slug);
            $this->actingAs($owner);
            $api = $surface === 'api';
            $profileUrl = $api ? '/api/v1/commerce/seller/provider-profile' : route('auth.commerce.provider-profile.store');
            foreach (['Sportshop', 'Sporthaus'] as $name) {
                $this->json($api ? 'PUT' : 'POST', $profileUrl, ['display_name' => $name, 'provider_type' => 'business',
                    'legal_country' => 'DE', 'support_email' => $owner->email])->assertSuccessful();
                $this->assertDatabaseHas('marketplace_provider_profiles', ['user_id' => $owner->id, 'display_name' => $name]);
            }
            $base = $api ? '/api/v1/commerce/seller/provider-locations' : route('auth.commerce.provider-locations.store');
            foreach (['branch', 'pickup'] as $type) {
                $data = ['name' => 'Standort Nord', 'type' => $type, 'country' => 'DE', 'pickup_enabled' => $type === 'pickup'];
                $id = $this->postJson($base, $data)->assertCreated()->json('data.id');
                $data['name'] = 'Standort Süd';
                $this->putJson("$base/$id", $data)->assertOk();
                $this->actingAs(User::factory()->create());
                $this->putJson("$base/$id", array_merge($data, ['name' => 'Fremde Änderung']))->assertForbidden();
                $this->deleteJson("$base/$id")->assertForbidden();
                $this->assertDatabaseHas('marketplace_provider_locations', ['id' => $id, 'name' => 'Standort Süd', 'type' => $type]);
                $this->actingAs($owner);
                $this->deleteJson("$base/$id")->assertOk();
                $this->assertDatabaseMissing('marketplace_provider_locations', ['id' => $id]);
            }
        }
    }

    #[DataProvider('plans')]
    public function test_marketplace_offers_require_seller_approval(string $slug): void
    {
        foreach (['web', 'api'] as $surface) {
            $seller = $this->accountForPlan($slug);
            $this->actingAs($seller);
            $api = $surface === 'api';
            $base = $api ? '/api/v1/commerce/seller/products' : route('auth.commerce.products.store');
            $data = ['title' => 'Testangebot', 'stock_quantity' => 10, 'price_cents' => 1900, 'category' => 'equipment', 'offer_type' => 'physical_product'];
            $this->postJson($base, $data)->assertUnprocessable()->assertJsonValidationErrors('seller_application');
            \App\Models\MarketplaceSellerApplication::create(['user_id' => $seller->id, 'applicant_type' => 'private',
                'status' => 'approved', 'accepted_rules' => ['accepted_at' => now()->toJSON()]]);
            foreach (['physical_product' => 'equipment', 'online_course' => 'course'] as $type => $category) {
                $data['offer_type'] = $type;
                $data['category'] = $category;
                if ($type === 'physical_product') {
                    $data += ['catalog_group' => 'apparel', 'catalog_subcategory' => 'shorts', 'sports' => ['running', 'fitness']];
                } else {
                    unset($data['catalog_group'], $data['catalog_subcategory'], $data['sports']);
                }
                $this->postJson($base, $data)->assertSuccessful();
                $product = \App\Models\MarketplaceProduct::where('user_id', $seller->id)->sole();
                $this->assertSame($type, $product->offer_type);
                $this->assertSame('review', $product->status);
                $url = $api ? "$base/{$product->id}" : route('auth.commerce.my-products.update', $product);
                if ($type === 'physical_product') {
                    $this->assertSame('apparel', $product->catalog_group);
                    $this->assertSame('shorts', $product->catalog_subcategory);
                    $this->assertEqualsCanonicalizing(['running', 'fitness'], $product->sports);
                    $data['catalog_group'] = 'shoes';
                    $data['catalog_subcategory'] = 'running_shoes';
                }
                $this->json($api ? 'PUT' : 'POST', $url, array_merge($data, ['title' => 'Geändertes Angebot']))->assertSuccessful();
                $this->assertSame('Geändertes Angebot', $product->fresh()->title);
                if ($type === 'physical_product') {
                    $product->refresh();
                    $this->assertSame('shoes', $product->catalog_group);
                    $this->assertSame('running_shoes', $product->catalog_subcategory);
                    $this->assertEqualsCanonicalizing(['running', 'fitness'], $product->sports);
                }
                $this->deleteJson($url)->assertSuccessful();
                $this->assertDatabaseMissing('marketplace_products', ['id' => $product->id]);
            }
        }
    }

    #[DataProvider('plans')]
    public function test_marketplace_buyer_catalog_visibility(string $slug): void
    {
        $seller = User::factory()->create();
        $this->actingAs($this->accountForPlan($slug));
        $base = ['user_id' => $seller->id, 'category' => 'product', 'price_cents' => 2000, 'currency' => 'EUR',
            'manages_stock' => true, 'stock_quantity' => 4, 'status' => 'published', 'moderation_status' => 'approved'];
        $tags = ['catalog_group' => 'apparel', 'catalog_subcategory' => 'shorts', 'sports' => ['running', 'fitness']];
        $legacy = \App\Models\MarketplaceProduct::create($base + ['title' => 'Tarifprobe Bestand']);
        $shorts = \App\Models\MarketplaceProduct::create($base + $tags + ['title' => 'Tarifprobe Shorts']);
        foreach (['draft', 'review'] as $status) {
            \App\Models\MarketplaceProduct::create(array_merge($base, $tags, ['title' => "Tarifprobe $status", 'status' => $status]));
        }
        foreach (['', 'running', 'fitness'] as $sport) {
            $query = ['search' => 'Tarifprobe'];
            if ($sport !== '') {
                $query += ['catalog_group' => 'apparel', 'catalog_subcategory' => 'shorts', 'sport' => $sport];
            }
            $ids = $sport === '' ? [$legacy->id, $shorts->id] : [$shorts->id];
            $web = $this->get(route('guest.marketplace', $query))->assertOk();
            $this->assertEqualsCanonicalizing($ids, collect($web->inertiaProps('products.data'))->pluck('id')->all());
            $api = $this->getJson('/api/v1/commerce/products?'.http_build_query($query))->assertOk();
            $this->assertEqualsCanonicalizing($ids, collect($api->json('data'))->pluck('id')->all());
        }
    }

    #[DataProvider('plans')]
    public function test_checkout_by_bank_transfer_for_products_and_courses(string $slug): void
    {
        \App\Models\Setting::setValue('marketplace_category_commissions', '{"equipment":12,"course":8}');
        \Illuminate\Support\Facades\Notification::fake();
        \App\Models\Setting::setValue('billing_bank_account_holder', 'Testverein');
        \App\Models\Setting::setValue('billing_iban', 'DE89370400440532013000');
        $buyer = $this->accountForPlan($slug);
        $seller = $this->accountForPlan($slug);
        $this->actingAs($buyer);
        $payload = ['provider' => 'bank_transfer', 'accepted_terms' => true, 'quantity' => 1,
            'customer_type' => 'consumer', 'shipping_country' => 'DE', 'shipping_street' => 'Testweg',
            'shipping_house_number' => '1', 'shipping_postal_code' => '10115', 'shipping_city' => 'Berlin'];
        foreach (['physical_product', 'online_course'] as $type) {
            foreach (['web', 'api'] as $surface) {
                $physical = $type === 'physical_product';
                $product = \App\Models\MarketplaceProduct::create(['user_id' => $seller->id, 'title' => "$type $surface",
                    'category' => $physical ? 'equipment' : 'course', 'offer_type' => $type, 'is_shippable' => $physical,
                    'manages_stock' => false, 'price_cents' => 1900, 'currency' => 'EUR', 'status' => 'published',
                    'moderation_status' => 'approved']);
                $url = route('auth.commerce.products.checkout', $product);
                if ($surface === 'api') {
                    $this->postJson("/api/v1/commerce/cart/items/{$product->id}", ['quantity' => 1])->assertCreated();
                    $url = '/api/v1/commerce/cart/checkout';
                }
                $this->postJson($url, $payload, ['Idempotency-Key' => (string) \Illuminate\Support\Str::uuid()])->assertCreated();
                $order = \App\Models\CommerceOrder::where('user_id', $buyer->id)->latest('id')->firstOrFail();
                $this->assertSame('awaiting_transfer', $order->status);
                $this->assertSame('bank_transfer', $order->provider);
                $this->assertSame($physical ? 228 : 152, $order->commission_cents);
                $this->assertSame($product->id, (int) $order->items()->sole()->orderable_id);
                $this->actingAs($seller);
                $report = $this->getJson('/api/v1/commerce/seller')->assertOk()
                    ->assertJsonPath('data.orders.0.id', $order->id)->json('data.orders.0');
                // The current cart report applies the product's 10% rate to the total including shipping.
                $expectedDisplay = $surface === 'web' ? $order->commission_cents : ($physical ? 239 : 190);
                $this->assertSame($expectedDisplay, $report['seller_commission_cents']);
                $this->actingAs($buyer);
            }
        }
    }

    #[DataProvider('plans')]
    public function test_feedback_from_assigned_trainer(string $slug): void
    {
        $trainer = $this->accountForPlan($slug);
        $athlete = User::factory()->create();
        $club = $trainer->clubs()->first() ?? Club::factory()->create();
        $team = \App\Models\Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($trainer, ['role' => \App\Support\TeamRoles::COACH]);
        $team->users()->attach($athlete, ['role' => \App\Support\TeamRoles::PLAYER]);
        $other = User::factory()->create();
        $log = \App\Models\TrainingLog::create(['user_id' => $athlete->id, 'created_by' => $athlete->id,
            'team_id' => $team->id, 'trainer_id' => $trainer->id, 'title' => 'Lauftraining', 'status' => 'completed',
            'performed_at' => now(), 'metrics' => ['privacy_scope' => 'trainer']]);
        foreach ([route('auth.training.logs.feedback.store', $log), "/api/v1/training/logs/{$log->id}/feedback"] as $url) {
            $this->actingAs($other)->postJson($url, ['body' => 'Fremder Kommentar'])->assertForbidden();
            $this->assertFalse($log->feedbacks()->where('user_id', $other->id)->exists());
            $id = $this->actingAs($trainer)->postJson($url, ['body' => 'Gute Einheit'])->assertCreated()
                ->assertJsonPath('data.role', 'trainer')->json('data.id');
            $this->assertSame('Gute Einheit', $log->feedbacks()->where('user_id', $trainer->id)->findOrFail($id)->body);
        }
        $log->update(['status' => 'draft']);
        foreach ([route('auth.training.logs.feedback.store', $log), "/api/v1/training/logs/{$log->id}/feedback"] as $url) {
            $this->actingAs($athlete)->postJson($url, ['body' => 'Entwurfsfeedback'])->assertUnprocessable();
        }
        $this->assertSame(2, $log->feedbacks()->count());
    }

    #[DataProvider('plans')]
    public function test_external_email_invitation_limit(string $slug): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $actor = $this->accountForPlan($slug);
        $club = $actor->clubs()->first() ?? Club::factory()->create();
        $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active',
            'permission_overrides' => ['members.manage' => true, 'members.edit' => true]]]);
        $this->actingAs($actor->fresh());
        $free = ! in_array($slug, ['starter', 'club', 'pro', 'elite'], true);
        foreach (['web', 'api'] as $surface) {
            $this->travel(1)->days();
            for ($i = 1; $i <= 4; $i++) {
                $record = \App\Models\ClubExternalMember::create(['club_id' => $club->id, 'name' => 'Gast',
                    'email' => "$surface-$i@example.test", 'membership_status' => 'active']);
                $url = $surface === 'web' ? route('auth.club-memberships.email-members.invite', $record)
                    : "/api/v1/clubs/{$club->id}/external-members/{$record->id}/invite";
                $response = $this->postJson($url);
                if ($i === 4 && $free) {
                    $response->assertUnprocessable();
                    $this->assertNull($record->fresh()->invited_at);
                } else {
                    $surface === 'web' ? $response->assertRedirect()->assertSessionHasNoErrors() : $response->assertOk();
                    $this->assertNotNull($record->fresh()->invited_at);
                    \Illuminate\Support\Facades\Notification::assertSentOnDemand(
                        \App\Notifications\ExternalClubMembershipInvitation::class,
                        fn ($notification, $channels, $recipient) => $recipient->routes['mail'] === $record->email
                    );
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_invitation_rights_by_channel(string $slug): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $actor = $this->accountForPlan($slug);
        $club = $actor->clubs()->first() ?? Club::factory()->create();
        foreach ([[false, false], [false, true], [true, false], [true, true]] as [$manage, $edit]) {
            $this->travel(1)->days();
            $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active',
                'permission_overrides' => ['members.manage' => $manage, 'members.edit' => $edit]]]);
            $this->actingAs($actor->fresh());
            foreach (['web', 'api'] as $surface) {
                $record = \App\Models\ClubExternalMember::create(['club_id' => $club->id, 'name' => 'Gast',
                    'email' => sprintf('%s-%d-%d@example.test', $surface, $manage, $edit), 'membership_status' => 'active']);
                $url = $surface === 'web' ? route('auth.club-memberships.email-members.invite', $record)
                    : "/api/v1/clubs/{$club->id}/external-members/{$record->id}/invite";
                $response = $this->postJson($url);
                if ($edit && ($surface === 'web' || $manage)) {
                    $surface === 'web' ? $response->assertRedirect()->assertSessionHasNoErrors() : $response->assertOk();
                    $this->assertNotNull($record->fresh()->invited_at);
                } else {
                    $response->assertForbidden();
                    $this->assertNull($record->fresh()->invited_at);
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_invited_account_joins_correct_club_once(string $slug): void
    {
        $member = $this->accountForPlan($slug);
        $member->forceFill(['birth_date' => now()->subYears(15), 'guardian_consent_at' => null])->save();
        foreach (['web', 'api'] as $surface) {
            $club = Club::factory()->create();
            $team = \App\Models\Team::factory()->create(['club_id' => $club->id]);
            $payerId = User::factory()->create()->id;
            $invite = \App\Models\ClubExternalMember::create(['club_id' => $club->id, 'name' => $member->name,
                'email' => $member->email, 'role' => 'member', 'membership_status' => 'active', 'member_number' => 'T-17',
                'contribution_amount' => 27.50, 'family_group_key' => 'Familie-17',
                'contribution_payer_user_id' => $payerId]);
            $invite->issueInvitation();
            $web = $surface === 'web';
            $url = $web ? route('auth.club-member-invitations.accept', $invite->invitation_token)
                : "/api/v1/club-external-invitations/{$invite->invitation_token}/accept";
            $this->actingAs($member);
            $response = $this->json($web ? 'GET' : 'POST', $url);
            $web ? $response->assertRedirect(route('auth.clubs.show', $club)) : $response->assertOk();
            $key = ['club_id' => $club->id, 'user_id' => $member->id];
            $this->assertDatabaseHas('club_user', $key + ['membership_status' => 'active', 'member_number' => 'T-17',
                'contribution_amount' => 27.50, 'family_group_key' => 'Familie-17', 'contribution_payer_user_id' => $payerId]);
            $this->assertDatabaseMissing('team_user', ['team_id' => $team->id, 'user_id' => $member->id]);
            $this->assertNull($member->fresh()->guardian_consent_at);
            $this->assertFalse(\App\Models\GuardianChildRelationship::where('child_user_id', $member->id)
                ->where('guardian_user_id', $payerId)->exists());
            $this->json($web ? 'GET' : 'POST', $url)->assertNotFound();
            $this->assertSame(1, \Illuminate\Support\Facades\DB::table('club_user')->where($key)->count());
        }
    }

    #[DataProvider('plans')]
    public function test_expired_and_declined_invites_do_not_create_memberships(string $slug): void
    {
        $member = $this->accountForPlan($slug);
        $this->actingAs($member);
        foreach (['expired-web', 'expired-api', 'declined'] as $scenario) {
            $club = Club::factory()->create();
            $invite = \App\Models\ClubExternalMember::create(['club_id' => $club->id, 'email' => $member->email,
                'name' => $member->name, 'role' => 'member', 'membership_status' => 'active']);
            $invite->issueInvitation($scenario === 'declined' ? now()->addDay() : now()->subDay());
            $base = "/api/v1/club-external-invitations/{$invite->invitation_token}";
            $web = route('auth.club-member-invitations.accept', $invite->invitation_token);
            if ($scenario === 'declined') {
                $this->postJson("$base/decline")->assertOk();
                $this->assertSame('declined', $invite->fresh()->invitation_status);
                $this->postJson("$base/accept")->assertNotFound();
                $this->get($web)->assertNotFound();
            } else {
                $response = $scenario === 'expired-web' ? $this->get($web) : $this->postJson("$base/accept");
                $response->assertGone();
                $this->assertSame('expired', $invite->fresh()->invitation_status);
            }
            $this->assertDatabaseMissing('club_user', ['club_id' => $club->id, 'user_id' => $member->id]);
        }
    }

    #[DataProvider('plans')]
    public function test_custom_club_role_lifecycle(string $slug): void
    {
        $actor = $this->accountForPlan($slug);
        $club = $actor->clubs()->first() ?? Club::factory()->create();
        $url = "/api/v1/clubs/{$club->id}/role-definitions";
        $data = ['name' => 'Dateiteam', 'permissions' => ['files.view']];
        foreach ([false, true] as $allowed) {
            $club->users()->syncWithoutDetaching([$actor->id => ['role' => 'member', 'membership_status' => 'active',
                'permission_overrides' => ['members.roles' => $allowed, 'files.view' => true]]]);
            $this->actingAs($actor->fresh());
            $created = $this->postJson($url, $data);
            if (! $allowed) {
                $created->assertForbidden();
                $this->assertDatabaseMissing('club_role_definitions', ['club_id' => $club->id, 'name' => 'Dateiteam']);
                continue;
            }
            $id = $created->assertCreated()->json('data.id');
            $this->getJson($url)->assertOk()->assertJsonFragment(['name' => 'Dateiteam']);
            $this->putJson("$url/$id", array_merge($data, ['name' => 'Dokumententeam']))->assertOk();
            $this->assertDatabaseHas('club_role_definitions', ['id' => $id, 'name' => 'Dokumententeam']);
            $recipient = User::factory()->create();
            $club->users()->attach($recipient, ['role' => 'member', 'membership_status' => 'active']);
            $assignments = "/api/v1/clubs/{$club->id}/members/{$recipient->id}/role-definitions";
            $this->putJson($assignments, ['role_definition_ids' => [$id]])->assertOk()->assertJsonCount(1, 'data.roles');
            $this->deleteJson("$url/$id")->assertUnprocessable()->assertJsonValidationErrors('role');
            $this->assertDatabaseHas('club_role_assignments', ['user_id' => $recipient->id, 'club_role_definition_id' => $id]);
            $this->putJson($assignments, ['role_definition_ids' => []])->assertOk()->assertJsonCount(0, 'data.roles');
            $this->assertDatabaseMissing('club_role_assignments', ['user_id' => $recipient->id, 'club_role_definition_id' => $id]);
            $this->deleteJson("$url/$id")->assertOk();
            $this->assertDatabaseMissing('club_role_definitions', ['id' => $id]);
        }
    }

    #[DataProvider('plans')]
    public function test_recurring_monthly_member_invoice_by_club_plan(string $slug): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $member = $this->accountForPlan($slug);
        $club = $member->clubs()->first() ?? Club::factory()->create();
        $plans = in_array($slug, ['free', 'starter', 'club', 'pro', 'elite'], true) ? [$slug] : ['free', 'pro'];
        foreach ($plans as $plan) {
            $club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => SubscriptionPlan::where('slug', $plan)->sole()->id,
                'status' => 'active', 'billing_interval' => 'monthly']);
            $club->users()->syncWithoutDetaching([$member->id => ['role' => 'member', 'membership_status' => 'active',
                'joined_on' => '2026-09-01', 'contribution_amount' => 12, 'contribution_interval' => 'monthly',
                'contribution_next_invoice_on' => '2026-10-01']]);
            $enabled = in_array($plan, ['pro', 'elite'], true);
            foreach ([1, 2] as $run) {
                $this->artisan('airmius:generate-recurring-contribution-invoices', ['--date' => '2026-10-01'])->assertSuccessful();
                $invoices = \App\Models\Invoice::where('club_id', $club->id)->where('membership_user_id', $member->id);
                $this->assertSame($enabled ? 1 : 0, $invoices->count());
                if ($enabled) {
                    $this->assertSame('12.00', $invoices->sole()->amount);
                }
            }
            $this->artisan('airmius:generate-recurring-contribution-invoices', ['--date' => '2026-11-01'])->assertSuccessful();
            $rows = $invoices->orderBy('billing_period_start')->get();
            $this->assertCount($enabled ? 2 : 0, $rows);
            if ($enabled) {
                foreach ($rows as $index => $invoice) {
                    $start = \Illuminate\Support\Carbon::parse('2026-10-01')->addMonths($index);
                    $this->assertSame($start->toDateString(), $invoice->billing_period_start->toDateString());
                    $this->assertSame($start->endOfMonth()->toDateString(), $invoice->billing_period_end->toDateString());
                    $this->assertSame('12.00', $invoice->amount);
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_family_calendar_visibility_by_relationship(string $slug): void
    {
        $guardian = $this->accountForPlan($slug);
        $club = $guardian->clubs()->first() ?? Club::factory()->create();
        $child = User::factory()->create(['birth_date' => now()->subYears(10), 'guardian_consent_at' => now()]);
        $club->users()->attach($child, ['role' => 'member', 'membership_status' => 'active']);
        $link = \App\Models\GuardianChildRelationship::create(['club_id' => $club->id, 'child_user_id' => $child->id,
            'guardian_user_id' => $guardian->id, 'status' => 'accepted']);
        $this->actingAs($guardian)->get(route('auth.family-calendar.index'))->assertOk();
        $calendar = '/api/v1/family-calendar';
        $start = now()->startOfMonth()->addDays(10)->setHour(12);
        $entry = $this->postJson("$calendar/entries", ['title' => 'Abholung', 'starts_at' => $start->toISOString(),
            'ends_at' => $start->copy()->addHour()->toISOString(), 'child_user_id' => $child->id, 'club_id' => $club->id])
            ->assertCreated()->json('data.id');
        $this->getJson($calendar)->assertOk()->assertJsonCount(1, 'data.children')->assertJsonPath('data.entries.0.id', $entry);
        $this->actingAs(User::factory()->create())->getJson($calendar)->assertOk()
            ->assertJsonCount(0, 'data.children')->assertJsonCount(0, 'data.entries');
        $link->update(['status' => 'revoked', 'revoked_at' => now()]);
        $this->actingAs($guardian)->getJson($calendar)->assertOk()
            ->assertJsonCount(0, 'data.children')->assertJsonCount(0, 'data.entries');
    }

    #[DataProvider('plans')]
    public function test_linked_guardian_can_revoke_and_restore_consent(string $slug): void
    {
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        $guardian = $this->accountForPlan($slug);
        $guardian->assignRole('guardian');
        $this->actingAs($guardian);

        foreach (['web', 'api'] as $surface) {
            $child = User::factory()->create([
                'birth_date' => now()->subYears(13)->toDateString(),
                'guardian_user_id' => $guardian->id,
                'guardian_email' => $guardian->email,
                'guardian_consent_at' => now(),
            ]);
            $child->assignRole('minor_player');
            foreach (['revoke', 'approve'] as $action) {
                if ($surface === 'web') {
                    $this->put(route('guardian-access.children.'.$action, $child))->assertRedirect()->assertSessionHasNoErrors();
                } else {
                    $this->postJson("/api/v1/guardian/children/{$child->id}/$action")->assertOk();
                }
                $child->refresh();
                $this->assertSame($action === 'approve', $child->guardian_consent_at !== null);
                $this->assertSame($action === 'revoke', $child->guardian_consent_revoked_at !== null);
                $this->assertTrue($child->hasRole($action === 'approve' ? 'minor_player' : 'minor_pending_consent'));
            }
        }
    }

    #[DataProvider('plans')]
    public function test_guardian_email_code_opens_web_portal(string $slug): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $parent = $this->accountForPlan($slug);
        $child = User::factory()->create(['birth_date' => now()->subYears(12), 'guardian_email' => $parent->email]);
        $this->post(route('guardian-access.store'), ['email' => $parent->email])
            ->assertRedirect(route('guardian-access.verify'));
        $code = null;
        \Illuminate\Support\Facades\Notification::assertSentOnDemand(
            \App\Notifications\GuardianAccessCodeRequested::class,
            function ($notification, $channels, $recipient) use ($parent, &$code) {
                $code = $notification->code;
                return $recipient->routeNotificationFor('mail') === $parent->email;
            }
        );
        $this->post(route('guardian-access.confirm'), ['code' => $code])
            ->assertRedirect(route('guardian-access.children'));
        $this->get(route('guardian-access.children'))->assertOk()
            ->assertInertia(fn ($page) => $page->has('children', 1)->where('children.0.id', $child->id));
    }

    #[DataProvider('plans')]
    public function test_enterprise_services_listed_in_both_catalogs(string $slug): void
    {
        $this->actingAs($this->accountForPlan($slug));
        $web = $this->get(route('guest.pricing'))->assertOk()->viewData('page')['props']['plans'];
        $mobile = $this->getJson('/api/v1/subscription-plans')->assertOk()->json('data');
        foreach ([$web, $mobile] as $catalog) {
            $plan = collect($catalog)->sole('slug', $slug);
            foreach (['Sportlerportfolio mit Medien (geplant)', 'Profilstatistiken (geplant)'] as $planned) {
                $this->assertSame($slug === 'sportler-pro', in_array($planned, $plan['features'], true), $planned);
            }
            foreach (['Datenmigration', 'Schnittstellen', 'Schulung', 'Mandantenfähigkeit vorbereitet', 'AVV und SLA Paket'] as $service) {
                $this->assertSame($slug === 'enterprise-verband', in_array($service, $plan['features'], true), $service);
            }
        }
    }

    #[DataProvider('plans')]
    public function test_course_creator_manages_participant_access(string $slug): void
    {
        $creator = $this->accountForPlan($slug);
        $other = User::factory()->create();
        $course = \App\Models\LearningCourse::create([
            'user_id' => $creator->id, 'title' => 'Lauftraining', 'slug' => 'lauftraining',
            'category' => 'training', 'level' => 'beginner', 'language' => 'de', 'status' => 'draft',
            'is_public' => false, 'is_free' => true, 'price_cents' => 0,
        ]);
        foreach (['web', 'api'] as $surface) {
            $student = User::factory()->create();
            $base = $surface === 'web' ? route('auth.learning.studio.enrollments.store', $course)
                : "/api/v1/learning-studio/courses/{$course->id}/enrollments";
            $data = ['email' => $student->email];
            $this->actingAs($other)->postJson($base, $data)->assertForbidden();
            $id = $this->actingAs($creator)->postJson($base, $data)->assertCreated()->json('data.id');
            $revoke = "$base/$id/revoke";
            $this->actingAs($other)->putJson($revoke)->assertForbidden();
            $this->assertDatabaseHas('learning_enrollments', ['id' => $id, 'user_id' => $student->id, 'status' => 'active']);
            $this->actingAs($creator)->putJson($revoke)->assertOk();
            $this->assertDatabaseHas('learning_enrollments', ['id' => $id, 'status' => 'cancelled']);
        }
    }

    #[DataProvider('plans')]
    public function test_learning_offer_drafts_by_owner(string $slug): void
    {
        $owner = $this->accountForPlan($slug);
        $other = User::factory()->create();
        foreach ([route('auth.learning.studio.courses.store'), '/api/v1/learning-studio/courses'] as $base) {
            foreach (['course', 'camp'] as $type) {
                $data = ['title' => "Training $type", 'offer_type' => $type, 'category' => 'training',
                    'level' => 'beginner', 'language' => 'de', 'status' => 'draft', 'is_public' => false, 'is_free' => true];
                $this->actingAs($owner)->postJson($base, $data)->assertSuccessful();
                $course = \App\Models\LearningCourse::where('user_id', $owner->id)->where('offer_type', $type)->sole();
                $item = "$base/{$course->getRouteKey()}";
                $data['title'] .= ' aktualisiert';
                $this->actingAs($other)->putJson($item, $data)->assertForbidden();
                $this->deleteJson($item, ['confirmation' => 'DELETE'])->assertForbidden();
                $this->actingAs($owner)->putJson($item, $data)->assertSuccessful();
                $this->assertSame($data['title'], $course->fresh()->title);
                $this->deleteJson($item, ['confirmation' => 'DELETE'])->assertSuccessful();
                $this->assertFalse(\App\Models\LearningCourse::whereKey($course->id)->exists());
            }
        }
    }

    #[DataProvider('plans')]
    public function test_athlete_load_score_and_alert_for_private_session(string $slug): void
    {
        $athlete = $this->accountForPlan($slug);
        $log = \App\Models\TrainingLog::create([
            'user_id' => $athlete->id, 'created_by' => $athlete->id, 'title' => 'Intervalltraining',
            'status' => 'completed', 'performed_at' => now(), 'privacy_scope' => 'private',
            'duration_minutes' => 30, 'metrics' => ['wellness' => ['rpe' => 9]],
        ]);
        $url = '/api/v1/training/analytics?days=7&user_id='.$athlete->id;
        $this->actingAs($athlete)->getJson($url)->assertOk()
            ->assertJsonPath('data.summary.sessions', 1)->assertJsonPath('data.summary.load_score', 270)
            ->assertJsonPath('data.alerts.0.id', $log->id)->assertJsonPath('data.privacy.scope', 'self');
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
    }

    #[DataProvider('plans')]
    public function test_team_coach_can_read_aggregate_overload_signals(string $slug): void
    {
        $trainer = $this->accountForPlan($slug);
        $club = $trainer->clubs()->first() ?? Club::factory()->create();
        $team = \App\Models\Team::factory()->create(['club_id' => $club->id]);
        $athlete = User::factory()->create();
        $team->users()->attach($trainer, ['role' => \App\Support\TeamRoles::COACH]);
        $team->users()->attach($athlete, ['role' => \App\Support\TeamRoles::PLAYER]);
        $url = '/api/v1/training/overload-indicators?user_id='.$athlete->id;
        $this->actingAs($trainer)->getJson($url)->assertOk()->assertJsonPath('data.summary.level', 'insufficient_data');
        \App\Models\TrainingLog::create([
            'user_id' => $athlete->id, 'created_by' => $athlete->id,
            'team_id' => $team->id, 'title' => 'Privates Einzeltraining',
            'status' => 'completed', 'performed_at' => now(), 'duration_minutes' => 60,
            'metrics' => ['privacy_scope' => 'private', 'wellness' => ['rpe' => 10]],
        ]);
        $this->getJson($url)->assertOk()->assertJsonPath('data.summary.shared_logs', 0)
            ->assertJsonPath('data.summary.level', 'insufficient_data')->assertDontSee('Privates Einzeltraining');
        foreach (range(1, 3) as $day) {
            \App\Models\TrainingLog::create(['user_id' => $athlete->id, 'created_by' => $athlete->id,
                'team_id' => $team->id, 'trainer_id' => $trainer->id, 'title' => 'Intervalltraining',
                'status' => 'completed', 'performed_at' => now()->subDays($day), 'duration_minutes' => 30,
                'metrics' => ['privacy_scope' => 'trainer', 'wellness' => ['rpe' => 9, 'pain' => 4]]]);
        }
        $this->getJson($url)->assertOk()->assertJsonPath('data.summary.shared_logs', 3)
            ->assertJsonPath('data.summary.level', 'high')->assertJsonPath('data.indicators.load_score', 810)
            ->assertJsonPath('data.summary.signals', ['repeated_pain', 'repeated_high_rpe'])
            ->assertJsonPath('data.indicators.pain_signal_count', 3)
            ->assertJsonPath('data.indicators.high_rpe_signal_count', 3)
            ->assertJsonPath('data.privacy.content_included', false)->assertDontSee('Intervalltraining');
        $trainer->assignRole(\Spatie\Permission\Models\Role::findOrCreate('coach', 'web'));
        foreach ([6 => 12, 7 => 25, 13 => 35, 14 => 44] as $daysAgo => $minutes) {
            \App\Models\TrainingLog::create(['user_id' => $athlete->id, 'created_by' => $athlete->id,
                'team_id' => $team->id, 'trainer_id' => $trainer->id, 'title' => 'Lockerer Lauf',
                'status' => 'completed', 'performed_at' => today()->subDays($daysAgo)->addHour(),
                'duration_minutes' => $minutes, 'metrics' => ['privacy_scope' => 'trainer', 'wellness' => ['rpe' => 1]]]);
        }
        $this->actingAs($trainer->fresh())->get(route('auth.trainer-cockpit.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('coachWeekly.current_week.session_count', 4)
                ->where('coachWeekly.current_week.duration_minutes', 102)
                ->where('coachWeekly.previous_week.session_count', 2)
                ->where('coachWeekly.previous_week.duration_minutes', 60)
                ->where('coachWeekly.current_week.average_rpe', 7)
                ->where('coachWeekly.risk_athletes.0.id', $athlete->id)
                ->where('coachWeekly.risk_athletes.0.pain', 4)
                ->where('coachWeekly.risk_athletes.0.rpe', 9)
                ->where('coachWeekly.risk_athletes.0.high_load_sessions', 3));
        $this->getJson('/api/v1/trainer-cockpit')->assertOk()
            ->assertJsonPath('data.coachWeekly.current_week.session_count', 4)
            ->assertJsonPath('data.coachWeekly.current_week.duration_minutes', 102)
            ->assertJsonPath('data.coachWeekly.previous_week.session_count', 2)
            ->assertJsonPath('data.coachWeekly.previous_week.duration_minutes', 60)
            ->assertJsonPath('data.coachWeekly.risk_athletes.0.id', $athlete->id)
            ->assertJsonPath('data.coachWeekly.risk_athletes.0.pain', 4)
            ->assertJsonPath('data.coachWeekly.risk_athletes.0.high_load_sessions', 3);
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
    }

    #[DataProvider('plans')]
    public function test_seller_can_book_a_sponsor_section_draft_for_their_club_with_advertising_permission(string $slug): void
    {
        $seller = $this->accountForPlan($slug);
        $club = Club::factory()->create();
        foreach (['web', 'api'] as $surface) {
            $url = $surface === 'web' ? route('auth.commerce.campaigns.store') : '/api/v1/commerce/seller/campaigns';
            $data = ['name' => "Vereinswerbung $surface", 'club_id' => $club->id, 'placement' => 'sponsor_section',
                'objective' => 'awareness', 'creative_format' => 'banner_wide', 'budget_cents' => 2500, 'start_payment' => false];
            foreach ([false, true] as $allowed) {
                $club->users()->syncWithoutDetaching([$seller->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['advertising.edit' => $allowed]]]);
                $response = $this->actingAs($seller->fresh())->postJson($url, $data);
                if ($allowed) {
                    $surface === 'web' ? $response->assertRedirect()->assertSessionHasNoErrors() : $response->assertCreated();
                    $this->assertDatabaseHas('ad_campaigns', ['club_id' => $club->id, 'user_id' => $seller->id,
                        'name' => $data['name'], 'placement' => 'sponsor_section', 'status' => 'draft']);
                } else {
                    $response->assertUnprocessable()->assertJsonValidationErrors('club_id');
                    $this->assertDatabaseMissing('ad_campaigns', ['name' => $data['name']]);
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_seller_campaign_placement_choices(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $this->actingAs($user);
        foreach (['web', 'api'] as $surface) {
            $url = $surface === 'web' ? route('auth.commerce.campaigns.store') : '/api/v1/commerce/seller/campaigns';
            foreach (['marketplace_card', 'feed', 'sidebar', 'sponsor_section', 'premium'] as $placement) {
                $name = "Werbeplatz $surface $placement";
                $result = $this->postJson($url, ['name' => $name, 'placement' => $placement, 'objective' => 'traffic',
                    'creative_format' => 'banner_wide', 'budget_cents' => 2500, 'start_payment' => false,
                    'target_url' => 'https://example.test/angebot']);
                if ($placement === 'premium') {
                    $result->assertUnprocessable()->assertJsonValidationErrors('placement');
                    $this->assertDatabaseMissing('ad_campaigns', ['user_id' => $user->id, 'name' => $name]);
                } else {
                    $surface === 'web' ? $result->assertRedirect()->assertSessionHasNoErrors() : $result->assertCreated();
                    $this->assertDatabaseHas('ad_campaigns', ['user_id' => $user->id, 'name' => $name,
                        'placement' => $placement, 'status' => 'draft', 'force_priority' => false]);
                }
            }
        }
    }

    #[DataProvider('plans')]
    public function test_club_advertising_drafts_by_role(string $slug): void
    {
        $member = $this->accountForPlan($slug);
        $club = $member->clubs()->first() ?? Club::factory()->create();
        foreach (['web', 'api'] as $surface) {
            $api = $surface === 'api';
            $base = $api ? '/api/v1/commerce/seller/campaigns' : route('auth.commerce.campaigns.store');
            $data = ['club_id' => $club->id, 'name' => "Sommeraktion $surface", 'objective' => 'traffic',
                'placement' => 'feed', 'creative_format' => 'feed_square', 'target_url' => 'https://example.test/sport',
                'budget_cents' => 2500, 'start_payment' => false];
            foreach ([false, true] as $allowed) {
                $club->users()->syncWithoutDetaching([$member->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => ['advertising.edit' => $allowed]]]);
                $response = $this->actingAs($member->fresh())->postJson($base, $data);
                if (! $allowed) {
                    $response->assertUnprocessable()->assertJsonValidationErrors('club_id');
                    $this->assertDatabaseMissing('ad_campaigns', ['name' => $data['name']]);
                } elseif ($api) {
                    $response->assertCreated();
                } else {
                    $response->assertRedirect()->assertSessionHasNoErrors();
                }
            }
            $campaign = \App\Models\AdCampaign::where('name', $data['name'])->where('club_id', $club->id)->sole();
            $this->assertSame('draft', $campaign->status);
            $url = "$base/{$campaign->id}";
            $data['name'] .= ' bearbeitet';
            $data['placement'] = 'sponsor_section';
            $data['creative_format'] = 'banner_wide';
            $response = $api ? $this->putJson($url, $data) : $this->postJson($url, $data);
            $response->assertOk();
            $this->assertSame($data['name'], $campaign->fresh()->name);
            $this->assertDatabaseHas('ad_campaigns', ['id' => $campaign->id, 'placement' => 'sponsor_section',
                'creative_format' => 'banner_wide', 'status' => 'draft']);
            $response = $this->deleteJson($url, ['confirmation' => 'delete']);
            $response->assertOk();
            $this->assertDatabaseMissing('ad_campaigns', ['id' => $campaign->id]);
        }
    }

    #[DataProvider('plans')]
    public function test_personal_onboarding_tracks_first_training(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $this->actingAs($user)->get(route('auth.maturity.index'))->assertOk();
        $url = route('api.v1.maturity.onboarding');
        $before = $this->getJson($url)->assertOk()->json('data');
        $this->assertContains('first_training_log', array_column($before['next_actions'], 'key'));
        $this->postJson('/api/v1/training/logs', ['title' => 'Erstes Training', 'sport_type' => 'laufen',
            'status' => 'completed', 'performed_at' => now()->toIso8601String(), 'privacy_scope' => 'private',
            'notify_people' => false])->assertCreated();
        $after = $this->getJson($url)->assertOk()->json('data');
        $this->assertTrue(collect($after['items'])->sole('key', 'first_training_log')['done']);
        $this->assertNotContains('first_training_log', array_column($after['next_actions'], 'key'));
        $this->assertSame($before['completion_percent'] + 10, $after['completion_percent']);
    }

    #[DataProvider('plans')]
    public function test_meal_photo_entitlement_and_consent(string $slug): void
    {
        $this->actingAs($this->accountForPlan($slug));
        $locked = !in_array($slug, ['club', 'pro', 'elite', 'sportler-pro', 'trainer-pro'], true);
        config(['airmius_ai.enabled' => true, 'airmius_ai.features.nutrition_image_analysis.enabled' => true,
            'airmius_ai.features.nutrition_image_analysis.primary_provider' => 'openai',
            'airmius_ai.features.nutrition_image_analysis.fallback_provider' => 'openai',
            'airmius_ai.providers.openai' => ['enabled' => true, 'model' => 'test', 'api_key' => 'test',
                'base_url' => 'https://tarif-test.invalid']]);
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(['https://tarif-test.invalid/responses' =>
            \Illuminate\Support\Facades\Http::response(['output_text' => '{"title":"Testmahlzeit","calories":300}'])]);
        $this->get(route('auth.nutrition.index'))->assertOk()->assertInertia(fn ($page) =>
            $page->where('aiCapabilities.nutrition_image_analysis.requires_premium', $locked));
        $this->getJson('/api/v1/nutrition')->assertOk()
            ->assertJsonPath('data.ai_capabilities.nutrition_image_analysis.requires_premium', $locked);
        foreach ([route('auth.nutrition.ai.meal-image'), '/api/v1/nutrition/ai/meal-image'] as $url) {
            $this->postJson($url, ['image' => \Illuminate\Http\UploadedFile::fake()->image('essen.jpg'),
                'ai_consent' => '0'])->assertUnprocessable()->assertJsonValidationErrors('ai_consent');
            $response = $this->postJson($url, ['image' => \Illuminate\Http\UploadedFile::fake()->image('essen.jpg'),
                'ai_consent' => '1']);
            if ($locked) {
                $response->assertUnprocessable();
            } else {
                $response->assertOk()->assertJsonPath('data.title', 'Testmahlzeit')->assertJsonPath('data.calories', 300);
            }
        }
        \Illuminate\Support\Facades\Http::assertSentCount($locked ? 0 : 2);
    }

    #[DataProvider('plans')]
    public function test_training_plan_quota_for_member_and_coach(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $premium = in_array($slug, ['club', 'pro', 'elite', 'trainer-pro'], true);
        $personal = $premium || $slug === 'sportler-pro';
        foreach ([false, true] as $coach) {
            if ($coach) {
                $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('coach', 'web'));
            }
            [$limit, $weeks] = $coach ? ($premium ? [30, 26] : [0, 0]) : ($personal ? [10, 12] : [3, 4]);
            $this->actingAs($user->fresh())->get(route('auth.nutrition.index'))->assertOk()
                ->assertInertia(fn ($page) => $page->where('aiCapabilities.training_plan_generation.monthly_limit', $limit)
                    ->where('aiCapabilities.training_plan_generation.max_weeks', $weeks));
            $this->getJson('/api/v1/nutrition')->assertOk()
                ->assertJsonPath('data.ai_capabilities.training_plan_generation.monthly_remaining', $limit)
                ->assertJsonPath('data.ai_capabilities.training_plan_generation.max_weeks', $weeks);
        }
    }

    #[DataProvider('plans')]
    public function test_short_plan_preview_with_provider_fixture(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $club = $user->clubs()->first() ?? Club::factory()->create();
        $club->users()->syncWithoutDetaching([$user->id => ['role' => 'member', 'membership_status' => 'active',
            'permission_overrides' => ['training_plans.edit' => true]]]);
        $this->actingAs($user->fresh());
        config(['airmius_ai.enabled' => true, 'airmius_ai.features.training_plan_generation.enabled' => true,
            'airmius_ai.features.training_plan_generation.primary_provider' => 'ionos',
            'airmius_ai.features.training_plan_generation.fallback_provider' => 'ionos',
            'airmius_ai.providers.ionos.enabled' => true, 'airmius_ai.providers.ionos.api_key' => 'test',
            'airmius_ai.providers.ionos.base_url' => 'https://ionos.test/v1']);
        $item = ['title' => 'Lockerer Lauf', 'duration_minutes' => 30, 'adaptation' => '5 Minuten kürzer laufen',
            'steps' => array_map(fn ($phase) => ['phase' => $phase, 'exercise' => 'Lockeres Laufen',
                'prescription' => '10 Minuten', 'minutes' => 10, 'instruction' => 'Entspannt laufen',
                'rest' => 'Keine Pause', 'intensity' => 'Sprechtempo'], ['warmup', 'main', 'cooldown'])];
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Http::fake(['ionos.test/*' => \Illuminate\Support\Facades\Http::response([
            'choices' => [['message' => ['content' => json_encode(['title' => 'Wochenplan', 'items' => [$item]])]]],
        ])]);
        $urls = [route('auth.training.ai.plans.preview'), '/api/v1/training/ai/plans/preview'];
        $request = ['goal' => 'Lauftraining planen', 'sport_type' => 'running', 'level' => 'beginner',
            'phase' => 'base', 'weeks' => 1, 'sessions_per_week' => 1, 'allow_profile_estimate' => true];
        foreach ($urls as $url) {
            $callsBefore = \Illuminate\Support\Facades\Http::recorded()->count();
            $incomplete = $this->postJson($url, array_replace($request, ['allow_profile_estimate' => false]))
                ->assertUnprocessable()->assertJsonPath('profile_estimate_allowed', true);
            $this->assertNotEmpty($incomplete->json('missing_profile_fields'));
            \Illuminate\Support\Facades\Http::assertSentCount($callsBefore);
            $plan = $this->postJson($url, $request)
                ->assertOk()->assertJsonCount(1, 'plan.items')->assertJsonPath('plan.items.0.title', 'Lockerer Lauf')
                ->assertJsonStructure(['plan' => ['safety_gate', 'safety_token', 'quality_check']])->json('plan');
            $checks = collect($plan['quality_check']['checks'])->keyBy('key');
            $this->assertSame('warning', $checks['profile']['status']);
            $this->assertContains($checks['progression']['status'], ['ok', 'warning']);
            $this->assertContains($checks['load_balance']['status'], ['ok', 'warning']);
            $this->assertNotEmpty($plan['quality_check']['suggestions']);
            $storeUrl = str_ends_with($url, '/api/v1/training/ai/plans/preview')
                ? '/api/v1/training/ai/plans' : route('auth.training.ai.plans.store');
            $this->postJson($storeUrl, ['plan' => $plan, 'accepted_ai_safety' => false])
                ->assertUnprocessable()->assertJsonValidationErrors('accepted_ai_safety');
            $id = $this->postJson($storeUrl, ['plan' => $plan, 'accepted_ai_safety' => true, 'status' => 'draft'])
                ->assertCreated()->json('plan.id');
            $this->assertDatabaseHas('training_plans', ['id' => $id, 'created_by' => $user->id, 'status' => 'draft']);
            $this->assertDatabaseHas('training_plan_items', ['training_plan_id' => $id, 'title' => 'Lockerer Lauf']);
        }
        $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('coach', 'web'));
        $this->actingAs($user->fresh());
        foreach ($urls as $url) {
            $before = \Illuminate\Support\Facades\Http::recorded()->count();
            $response = $this->postJson($url, $request);
            if (in_array($slug, ['club', 'pro', 'elite', 'trainer-pro'], true)) {
                $response->assertOk()->assertJsonPath('plan.items.0.title', 'Lockerer Lauf');
                $checks = collect($response->json('plan.quality_check.checks'))->keyBy('key');
                $this->assertSame('warning', $checks['profile']['status']);
            } else {
                $response->assertUnprocessable()->assertJsonPath('code', 'training_ai_unavailable');
                \Illuminate\Support\Facades\Http::assertSentCount($before);
            }
        }
    }

    #[DataProvider('plans')]
    public function test_personal_plan_monthly_usage_boundary(string $slug): void
    {
        $user = $this->accountForPlan($slug);
        $this->actingAs($user);
        $this->travelTo(now()->startOfMonth()->addDays(5));
        $limit = in_array($slug, ['club', 'pro', 'elite', 'trainer-pro', 'sportler-pro'], true) ? 10 : 3;
        $event = ['user_id' => $user->id, 'area' => 'ai', 'provider' => 'test', 'service' => 'text',
            'operation' => 'training_plan_generation', 'occurred_at' => now()];
        \App\Models\ExternalProviderUsageEvent::create($event + ['status' => 'error']);
        for ($i = 1; $i < $limit; $i++) {
            \App\Models\ExternalProviderUsageEvent::create($event + ['status' => 'ok']);
        }
        $remaining = function (int $expected) {
            $this->get(route('auth.nutrition.index'))->assertOk()->assertInertia(fn ($page) =>
                $page->where('aiCapabilities.training_plan_generation.monthly_remaining', $expected));
            $this->getJson('/api/v1/nutrition')->assertOk()
                ->assertJsonPath('data.ai_capabilities.training_plan_generation.monthly_remaining', $expected);
        };
        $remaining(1);
        $request = ['goal' => 'Lauftraining planen', 'sport_type' => 'running', 'level' => 'beginner',
            'phase' => 'base', 'weeks' => 1, 'sessions_per_week' => 2, 'allow_profile_estimate' => true];
        foreach ([route('auth.training.ai.plans.preview'), '/api/v1/training/ai/plans/preview'] as $url) {
            $this->postJson($url, $request)->assertForbidden();
        }
        \App\Models\ExternalProviderUsageEvent::create($event + ['status' => 'ok']);
        $remaining(0);
        $club = $user->clubs()->first() ?? Club::factory()->create();
        $club->users()->syncWithoutDetaching([$user->id => ['role' => 'member', 'membership_status' => 'active',
            'permission_overrides' => ['training_plans.edit' => true]]]);
        $this->actingAs($user->fresh());
        \Illuminate\Support\Facades\Http::fake();
        \Illuminate\Support\Facades\Log::spy();
        foreach ([route('auth.training.ai.plans.preview'), '/api/v1/training/ai/plans/preview'] as $url) {
            // A new HTTP request resolves a fresh controller after the permission change.
            app('router')->getRoutes()->match(\Illuminate\Http\Request::create($url, 'POST'))->flushController();
            $this->postJson($url, array_replace($request, ['allow_profile_estimate' => false]))
                ->assertUnprocessable()->assertJsonPath('profile_estimate_allowed', true)
                ->assertJsonStructure(['missing_profile_fields' => [['label']], 'profile_completion_url']);
            $this->postJson($url, $request)->assertUnprocessable()->assertJsonPath('code', 'training_ai_unavailable');
        }
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->twice()
            ->withArgs(fn ($message, $context) => $message === 'AI training plan generation failed.'
                && str_contains($context['message'], "KI-Trainingsplan-Limit ({$limit}x) ist erreicht"));
        \Illuminate\Support\Facades\Http::assertNothingSent();
        $this->travelTo(now()->addMonth()->startOfMonth());
        $remaining($limit);
    }

    #[DataProvider('plans')]
    public function test_member_can_report_image_rights_on_public_post(string $slug): void
    {
        $reporter = $this->accountForPlan($slug);
        $author = User::factory()->create();
        $this->actingAs($reporter);
        foreach ([route('auth.reports.store'), '/api/v1/reports'] as $url) {
            $post = \App\Models\Post::factory()->create(['user_id' => $author->id, 'visibility' => 'public',
                'image' => 'https://example.test/vereinsfoto.jpg']);
            $id = $this->postJson($url, ['type' => 'post', 'id' => $post->id, 'reason' => 'image_rights'])
                ->assertCreated()->assertJsonPath('data.status', 'open')->json('data.id');
            $this->assertDatabaseHas('content_reports', ['id' => $id, 'reportable_id' => $post->id,
                'reporter_id' => $reporter->id, 'reason' => 'image_rights', 'status' => 'open']);
            $appealUrl = "$url/$id/appeal";
            $appeal = ['reason' => 'Bitte die Bildfreigabe erneut prüfen.'];
            $this->postJson($appealUrl, $appeal)->assertUnprocessable();
            \App\Models\ContentReport::findOrFail($id)->update(['status' => 'dismissed']);
            $this->actingAs($author)->postJson($appealUrl, $appeal)->assertForbidden();
            $this->actingAs($reporter)->postJson($appealUrl, $appeal)->assertCreated()
                ->assertJsonPath('data.appeal_status', 'pending');
            $this->postJson($appealUrl, $appeal)->assertUnprocessable();
            $this->assertDatabaseHas('content_reports', ['id' => $id, 'appeal_status' => 'pending',
                'appeal_reason' => $appeal['reason']]);
        }
    }

    #[DataProvider('plans')]
    public function test_private_device_preview_link_requires_authentication(string $slug): void
    {
        $owner = $this->accountForPlan($slug);
        $disk = \Illuminate\Support\Facades\Storage::fake(\App\Support\UploadStorage::privateDisk());
        $path = 'private-files/test/greeting.txt';
        $disk->put($path, 'Hallo Android');
        $file = \App\Models\File::create(['user_id' => $owner->id, 'display_name' => 'Beispiel.txt',
            'path' => $path, 'type' => 'text/plain', 'size' => 13]);
        $this->actingAs($owner);
        $url = $this->getJson('/api/v1/files?scope=user')->assertOk()->json('data.files.0.url');
        $this->assertSame(route('api.v1.files.preview', $file), $url);
        $response = $this->get($url)->assertOk();
        $this->assertStringStartsWith('inline;', $response->headers->get('Content-Disposition'));
        $this->assertSame('Hallo Android', $response->streamedContent());
        $attachment = $this->get(route('auth.files.download', $file))->assertOk()->assertDownload('Beispiel.txt');
        $this->assertSame('Hallo Android', $attachment->streamedContent());
        auth()->forgetGuards();
        auth()->shouldUse('web');
        $this->assertGuest();
        $guest = $this->get($url, ['Accept' => 'text/html'])->assertStatus(401);
        $guest->assertDontSee('Hallo Android');
    }

    #[DataProvider('plans')]
    public function test_cashbox_rule_lifecycle_by_permission(string $slug): void
    {
        $member = $this->accountForPlan($slug);
        $club = $member->clubs()->first() ?? Club::factory()->create();
        $team = \App\Models\Team::factory()->create(['club_id' => $club->id]);
        $team->users()->attach($member, ['role' => \App\Support\TeamRoles::PLAYER]);
        $data = ['title' => 'Verspätung', 'trigger' => 'late', 'calculation_type' => 'fixed', 'amount' => 5,
            'currency' => 'EUR', 'is_active' => true];
        foreach ([route('auth.teams.penalty-rules.store', $team), "/api/v1/teams/{$team->id}/penalty-rules"] as $url) {
            foreach ([false, true] as $allowed) {
                $club->users()->syncWithoutDetaching([$member->id => ['role' => 'member', 'membership_status' => 'active',
                    'permission_overrides' => [\App\Support\ClubPermissions::TEAM_CASHBOX_MANAGE => $allowed]]]);
                $response = $this->actingAs($member->fresh())->postJson($url, $data);
                $allowed ? $response->assertCreated() : $response->assertForbidden();
            }
            $id = $response->json('data.id');
            $this->putJson("$url/$id", ['title' => 'Spät zum Training'])->assertOk();
            $this->deleteJson("$url/$id")->assertOk()->assertJsonPath('data.is_active', false);
            $this->assertDatabaseHas('team_penalty_rules', ['id' => $id, 'title' => 'Spät zum Training', 'is_active' => false]);
        }
    }

    #[DataProvider('plans')]
    public function test_member_profile_photo_lifecycle(string $slug): void
    {
        config(['filesystems.uploads_disk' => 'public', 'jetstream.profile_photo_disk' => 'public']);
        $disk = \Illuminate\Support\Facades\Storage::fake('public');
        $user = $this->accountForPlan($slug);
        $this->actingAs($user);
        foreach (['web', 'api'] as $surface) {
            $previous = null;
            foreach (['erstes.jpg', 'zweites.jpg'] as $name) {
                $photo = \Illuminate\Http\UploadedFile::fake()->image($name, 120, 120);
                if ($surface === 'web') {
                    $this->putJson(route('user-profile-information.update'), ['first_name' => $user->first_name,
                        'last_name' => $user->last_name, 'email' => $user->email, 'profile_visibility' => 'private',
                        'photo' => $photo])->assertSuccessful();
                } else {
                    $this->postJson('/api/v1/me/profile-photo', ['photo' => $photo])->assertOk();
                }
                $path = $user->refresh()->profile_photo_path;
                $disk->assertExists($path);
                if ($previous !== null) {
                    $this->assertNotSame($previous, $path);
                    $disk->assertMissing($previous);
                }
                $previous = $path;
            }
            $document = \Illuminate\Http\UploadedFile::fake()->createWithContent('Sportprofil.pdf', "%PDF-1.4\nSportprofil\n");
            if ($surface === 'web') {
                $invalid = $this->putJson(route('user-profile-information.update'), ['first_name' => $user->first_name,
                    'last_name' => $user->last_name, 'email' => $user->email, 'profile_visibility' => 'private',
                    'photo' => $document]);
            } else {
                $invalid = $this->postJson('/api/v1/me/profile-photo', ['photo' => $document]);
            }
            $invalid->assertUnprocessable()->assertJsonValidationErrors('photo');
            $this->assertSame($previous, $user->refresh()->profile_photo_path);
            $disk->assertExists($previous);
            $url = $surface === 'web' ? route('current-user-photo.destroy') : '/api/v1/me/profile-photo';
            $response = $this->deleteJson($url);
            $surface === 'web' ? $response->assertRedirect()->assertSessionHasNoErrors() : $response->assertOk();
            $this->assertNull($user->refresh()->profile_photo_path);
            $disk->assertMissing($previous);
        }
    }

    #[DataProvider('plans')]
    public function test_profile_sport_count_respects_private_entry(string $slug): void
    {
        $owner = $this->accountForPlan($slug);
        $owner->forceFill(['profile_visibility' => 'public'])->save();
        $other = User::factory()->create();
        $this->actingAs($owner);
        foreach (['public', 'private'] as $visibility) {
            $sport = \App\Models\Sport::create(['name' => "Sport $visibility", 'slug' => "tarif-$visibility",
                'category' => 'endurance', 'is_active' => true]);
            $this->putJson("/api/v1/sport-profiles/{$sport->id}", ['status' => 'active',
                'experience_level' => 'beginner', 'visibility' => $visibility])->assertOk();
        }
        foreach ([[$owner, 2], [$other, 1]] as [$viewer, $count]) {
            $this->actingAs($viewer)->get(route('auth.users.show', $owner))->assertOk()
                ->assertInertia(fn ($page) => $page->where('profileUser.sport_cv.summary.sports_count', $count));
            $this->getJson("/api/v1/users/{$owner->id}/sport-cv")->assertOk()->assertJsonPath('data.summary.sports_count', $count);
        }
    }

    #[DataProvider('plans')]
    public function test_direct_file_share_across_sender_and_recipient_plans(string $slug): void
    {
        $sender = $this->accountForPlan($slug);
        $disk = \Illuminate\Support\Facades\Storage::fake(\App\Support\UploadStorage::privateDisk());
        $path = 'private-files/tarife/uebergabe.txt';
        $disk->put($path, 'Tarifprobe');
        $source = \App\Models\File::create(['user_id' => $sender->id, 'path' => $path,
            'display_name' => 'uebergabe.txt', 'type' => 'text/plain', 'size' => 10]);
        $copies = [];
        foreach (self::plans() as [$recipientPlan]) {
            $recipient = $this->accountForPlan($recipientPlan);
            $this->actingAs($sender)->postJson(route('auth.files.share', $source),
                ['target_type' => 'user', 'target_id' => $recipient->id])->assertForbidden();
            $this->postJson("/api/v1/uploads/{$source->id}/share", ['target_user_id' => $recipient->id])->assertForbidden();
            $this->assertDatabaseMissing('files', ['user_id' => $recipient->id, 'path' => $path]);
            \App\Models\Friendship::create(['user_id' => $sender->id, 'friend_id' => $recipient->id]);
            $this->actingAs($sender)->postJson("/api/v1/uploads/{$source->id}/share",
                ['target_user_id' => $recipient->id])->assertSuccessful();
            $copy = \App\Models\File::where('user_id', $recipient->id)->where('path', $path)->sole();
            $copies[] = [$recipient, $copy];
            $this->actingAs($recipient);
            $this->getJson('/api/v1/files?scope=user')->assertOk()->assertJsonPath('data.files.0.id', $copy->id);
            $this->assertSame('Tarifprobe', $this->get("/api/v1/files/{$copy->id}/preview")->assertOk()->streamedContent());
            $this->assertSame('Tarifprobe', $this->get(route('auth.files.download', $copy))
                ->assertOk()->assertDownload('uebergabe.txt')->streamedContent());
            $this->travel(2)->minutes();
        }
        $this->actingAs($sender)->deleteJson("/api/v1/uploads/{$source->id}")->assertOk();
        $this->assertNull($source->fresh());
        foreach ($copies as [$recipient, $copy]) {
            $this->actingAs($recipient);
            $this->assertSame('Tarifprobe', $this->get("/api/v1/files/{$copy->id}/preview")->assertOk()->streamedContent());
            $this->assertSame('Tarifprobe', $this->get(route('auth.files.download', $copy))
                ->assertOk()->assertDownload('uebergabe.txt')->streamedContent());
        }
    }

    #[DataProvider('plans')]
    public function test_third_party_token_self_service_is_unavailable(string $slug): void
    {
        $member = $this->accountForPlan($slug);
        $this->actingAs($member);
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('api-tokens.store'));
        $this->get('/user/api-tokens')->assertNotFound();
        $this->postJson('/user/api-tokens', ['name' => 'Berichtsexport', 'permissions' => ['read']])->assertNotFound();
        $this->assertFalse($member->tokens()->where('name', 'Berichtsexport')->exists());
    }

    #[DataProvider('plans')]
    public function test_marketplace_ad_delivery_by_campaign_status_and_budget(string $slug): void
    {
        $this->actingAs($this->accountForPlan($slug));
        foreach (['marketplace_card' => 'feed_square', 'sponsor_section' => 'banner_wide'] as $placement => $format) {
            $campaign = \App\Models\AdCampaign::create(['name' => 'Vereinsanzeige', 'objective' => 'traffic',
                'club_id' => Club::factory()->create()->id, 'placement' => $placement, 'creative_format' => $format,
                'budget_cents' => 10000, 'target_url' => 'https://example.test/sport', 'billing_event' => 'click',
                'status' => 'draft']);
            $url = route('ads.active', ['placement' => $placement]);
            $this->getJson($url)->assertOk()->assertJsonMissingPath('id');
            $campaign->update(['status' => 'active']);
            $this->getJson($url)->assertOk()->assertJsonPath('id', $campaign->id);
            // The mobile public ad screen requests the feed placement.
            $this->getJson(route('ads.active', ['placement' => 'feed']))->assertOk()->assertJsonMissingPath('id');
            $foreignClub = Club::factory()->create();
            $foreignUrl = route('ads.active', ['placement' => $placement, 'club_id' => $foreignClub->id]);
            $this->getJson($foreignUrl)->assertOk()->assertJsonPath('id', $campaign->id);
            $priority = $campaign->replicate();
            $priority->forceFill(['name' => 'Interne Anzeige', 'is_internal' => true, 'force_priority' => true])->save();
            $this->getJson($url)->assertOk()->assertJsonPath('id', $priority->id);
            $priority->update(['status' => 'paused']);
            $campaign->update(['spent_cents' => 10000]);
            $this->getJson($url)->assertOk()->assertJsonMissingPath('id');
        }
    }

    #[DataProvider('plans')]
    public function test_marketplace_campaign_bank_transfer_reserves_single_order(string $slug): void
    {
        $this->assertCampaignReservation($slug, 'marketplace_card', 'feed_square');
    }

    #[DataProvider('plans')]
    public function test_sponsor_section_bank_transfer_reserves_single_order(string $slug): void
    {
        $this->assertCampaignReservation($slug, 'sponsor_section', 'banner_wide');
    }

    private function assertCampaignReservation(string $slug, string $placement, string $format): void
    {
        $admin = User::factory()->create(['two_factor_secret' => 'encrypted-test-secret', 'two_factor_confirmed_at' => now()]);
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('super_admin', 'web'));
        $admin->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('subscriptions.manage', 'web'));
        \App\Models\Setting::setValue('billing_iban', 'DE89370400440532013000');
        \Illuminate\Support\Facades\Notification::fake();
        $owner = $this->accountForPlan($slug);
        $this->actingAs($owner);
        foreach (['web', 'api'] as $surface) {
            $name = "Testwerbung $surface";
            $store = $surface === 'web' ? route('auth.commerce.campaigns.store') : '/api/v1/commerce/seller/campaigns';
            $created = $this->postJson($store, ['name' => $name,
                'objective' => 'traffic', 'placement' => $placement, 'creative_format' => $format,
                'target_url' => 'https://example.test/sport', 'budget_cents' => 2500, 'start_payment' => false]);
            $surface === 'web' ? $created->assertRedirect()->assertSessionHasNoErrors() : $created->assertCreated();
            $campaign = \App\Models\AdCampaign::where('user_id', $owner->id)->where('name', $name)->sole();
            $this->assertFalse($campaign->force_priority);
            $url = $surface === 'web' ? route('auth.commerce.campaigns.payment', $campaign)
                : "/api/v1/commerce/seller/campaigns/{$campaign->id}/payment";
            $data = ['provider' => 'bank_transfer', 'accepted_terms' => true];
            $this->postJson($url, $data)->assertSuccessful();
            $this->postJson($url, $data)->assertSuccessful();
            $order = \App\Models\CommerceOrder::where('orderable_id', $campaign->id)->where('type', 'ads_campaign')->sole();
            $this->assertSame('awaiting_transfer', $order->status);
            $this->assertSame(2500, (int) $order->amount_cents);
            $this->assertSame('pending_payment', $campaign->fresh()->status);
            $this->getJson(route('ads.active', ['placement' => $placement]))->assertOk()->assertJsonMissingPath('id');
            $confirmation = $surface === 'web' ? route('admin.commerce.orders.mark-paid', $order)
                : "/api/v1/admin/commerce/orders/{$order->id}/mark-paid";
            $this->postJson($confirmation)->assertForbidden();
            $this->assertSame('awaiting_transfer', $order->fresh()->status);
            if ($surface === 'web') {
                $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp])
                    ->postJson($confirmation)->assertRedirect()->assertSessionHasNoErrors();
            } else {
                \Laravel\Sanctum\Sanctum::actingAs($admin, [\App\Support\AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);
                $this->postJson($confirmation)->assertOk();
                auth()->forgetGuards();
                auth()->shouldUse('web');
            }
            $this->assertSame('completed', $order->fresh()->status);
            $this->assertSame('pending_review', $campaign->fresh()->status);
            $this->actingAs($owner)->getJson(route('ads.active', ['placement' => $placement]))
                ->assertOk()->assertJsonMissingPath('id');
            $review = $surface === 'web' ? route('admin.commerce.campaigns.status.update', $campaign)
                : "/api/v1/admin/commerce/campaigns/{$campaign->id}/status";
            $method = $surface === 'web' ? 'PUT' : 'PATCH';
            $this->json($method, $review, ['status' => 'active'])->assertForbidden();
            if ($surface === 'web') {
                $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
            } else {
                \Laravel\Sanctum\Sanctum::actingAs($admin, [\App\Support\AdminTwoFactor::STEP_UP_TOKEN_ABILITY]);
            }
            foreach (['active', 'paused'] as $status) {
                $response = $this->json($method, $review, ['status' => $status]);
                $response->assertStatus($surface === 'web' ? 302 : 200);
                $this->assertSame($status, $campaign->fresh()->status);
                $this->assertFalse($campaign->fresh()->force_priority);
                $ad = $this->getJson(route('ads.active', ['placement' => $placement]))->assertOk();
                if ($status === 'active') {
                    $ad->assertJsonPath('id', $campaign->id);
                    $this->getJson(route('ads.active', ['placement' => 'feed']))
                        ->assertOk()->assertJsonMissingPath('id');
                } else {
                    $ad->assertJsonMissingPath('id');
                }
            }
            auth()->forgetGuards();
            auth()->shouldUse('web');
            $this->actingAs($owner);
        }
    }

    private function accountForPlan(string $slug): User
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::where('slug', $slug)->firstOrFail();
        if ($plan->target_actor === 'verein') {
            $club = Club::factory()->create();
            $club->users()->attach($user, ['role' => 'member', 'membership_status' => 'active']);
            $club->currentSubscription()->updateOrCreate([], ['subscription_plan_id' => $plan->id, 'status' => 'active', 'billing_interval' => 'monthly']);
            $this->assertSame($slug, $club->fresh()->subscriptionPlan()->slug);
        } else {
            UserSubscription::create(['user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => 'active', 'current_period_ends_at' => now()->addMonth()]);
        }

        return $user;
    }
}
