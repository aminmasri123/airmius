<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CleansClubMetadata
{
    public static function bootCleansClubMetadata(): void
    {
        static::deleting(function (Model $subject): void {
            if (Schema::hasTable('club_custom_field_values')) {
                DB::table('club_custom_field_values')
                    ->where('subject_type', $subject->clubMetadataSubjectType())
                    ->where('subject_id', $subject->getKey())
                    ->delete();
            }
            if (Schema::hasTable('club_category_assignments')) {
                DB::table('club_category_assignments')
                    ->where('subject_type', $subject->clubMetadataSubjectType())
                    ->where('subject_id', $subject->getKey())
                    ->delete();
            }
            if (Schema::hasTable('club_member_timeline_entries')) {
                DB::table('club_member_timeline_entries')
                    ->where('subject_type', $subject->clubMetadataSubjectType())
                    ->where('subject_id', $subject->getKey())
                    ->delete();
            }
        });
    }

    abstract public function clubMetadataSubjectType(): string;
}
