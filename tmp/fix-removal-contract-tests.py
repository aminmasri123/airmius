from pathlib import Path

def confirm(club,member,var):
 return f'''        ${var} = \\App\\Models\\ClubMemberRemoval::query()->where('club_id', ${club}->id)->where('user_id', ${member}->id)->sole();
        $this->assertSame('pending', ${var}->status);
        app(\\App\\Services\\ClubMemberRemovalService::class)->respond(${var}, ${member}, 'confirm', null);

'''
p=Path('tests/Feature/ClubAuditLogTest.php'); s=p.read_text(encoding='utf-8')
for member,var in [('webMember','webRemoval'),('apiMember','apiRemoval')]:
 anchor="        $this->assertDatabaseMissing('club_user', [\n            'club_id' => $club->id,\n            'user_id' => $"+member+"->id,\n        ]);"
 assert s.count(anchor)==1;s=s.replace(anchor,confirm('club',member,var)+anchor)
 anchor="'user_id' => $owner->id,\n            'type' => 'club.member.removed',\n            'subject_type' => User::class,\n            'subject_id' => $"+member+"->id,"
 assert anchor in s;s=s.replace(anchor,"'user_id' => $"+member+"->id,\n            'type' => 'club.member.removed',\n            'subject_type' => User::class,\n            'subject_id' => $"+member+"->id,")
s=s.replace("$activity->data['reason']","$webRemoval->reason")
p.write_text(s,encoding='utf-8')
p=Path('tests/Feature/ClubPermissionsTest.php');s=p.read_text(encoding='utf-8'); anchor="        $this->assertDatabaseMissing('club_user', ['club_id' => $club->id, 'user_id' => $victim->id]);"; assert s.count(anchor)==1;s=s.replace(anchor,confirm('club','victim','removal')+anchor);p.write_text(s,encoding='utf-8')
p=Path('tests/Feature/ClubMetadataValuesTest.php');s=p.read_text(encoding='utf-8');anchor="        $this->assertDatabaseMissing('club_custom_field_values', [\n            'club_id' => $club->id, 'subject_type' => 'member', 'subject_id' => $member->id,\n        ]);";assert s.count(anchor)==1;s=s.replace(anchor,confirm('club','member','removal')+anchor);p.write_text(s,encoding='utf-8')
p=Path('tests/Feature/ClubMembershipApplicationFlowTest.php');s=p.read_text(encoding='utf-8');s=s.replace('"/api/v1/clubs/{$foreignClub->id}/membership-requests/{$application->id}/{$decision}")\n                ->assertForbidden()', '"/api/v1/clubs/{$foreignClub->id}/membership-requests/{$application->id}/{$decision}")\n                ->assertNotFound()');p.write_text(s,encoding='utf-8')
p=Path('tests/Feature/ClubWorkspaceIsolationTest.php');s=p.read_text(encoding='utf-8');s=s.replace('"/api/v1/clubs/{$club->id}/finance-entries"','"/api/v1/clubs/{$club->id}/finance-entries"');s=s.replace("'/api/v1/clubs/'.$this->foreign->id.'/finance-entries', [])->assertNotFound()", "'/api/v1/clubs/'.$this->foreign->id.'/finance-entries', [])->assertForbidden()");p.write_text(s,encoding='utf-8')
p=Path('tests/Feature/MobileFullQaRoleBoundaryTest.php');s=p.read_text(encoding='utf-8');s=s.replace('"/api/v1/clubs/{$club->id}/members")->assertForbidden()','"/api/v1/clubs/{$club->id}/members")->assertNotFound()').replace('"/api/v1/clubs/{$club->id}/members/{$member->id}/role", [\'role\' => \'admin\'])\n            ->assertForbidden()','"/api/v1/clubs/{$club->id}/members/{$member->id}/role", [\'role\' => \'admin\'])\n            ->assertNotFound()').replace('"/api/v1/clubs/{$club->id}/members/{$member->id}")->assertForbidden()','"/api/v1/clubs/{$club->id}/members/{$member->id}")->assertNotFound()');p.write_text(s,encoding='utf-8')
print('Updated lifecycle tests to confirm removals before checking cleanup; retained foreign-record denial checks')
