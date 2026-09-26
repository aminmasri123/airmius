<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_participants')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE event_participants MODIFY status ENUM('yes','no','maybe','late','waitlist') NOT NULL");
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteTable("status in ('yes', 'no', 'maybe', 'late', 'waitlist')");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_participants')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::table('event_participants')->where('status', 'waitlist')->update(['status' => 'maybe']);
            DB::statement("ALTER TABLE event_participants MODIFY status ENUM('yes','no','maybe','late') NOT NULL");
            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::table('event_participants')->where('status', 'waitlist')->update(['status' => 'maybe']);
            $this->rebuildSqliteTable("status in ('yes', 'no', 'maybe', 'late')");
        }
    }

    private function rebuildSqliteTable(string $statusCheck): void
    {
        DB::statement('PRAGMA foreign_keys=OFF');
        DB::statement('ALTER TABLE event_participants RENAME TO event_participants_old');
        DB::statement(<<<SQL
            CREATE TABLE event_participants (
                id integer primary key autoincrement not null,
                event_id integer not null,
                user_id integer not null,
                status varchar check ({$statusCheck}) not null,
                response_reason text null,
                response_mode varchar null,
                responded_at datetime null,
                checked_in_at datetime null,
                check_in_method varchar null,
                created_at datetime null,
                updated_at datetime null,
                foreign key(event_id) references events(id) on delete cascade,
                foreign key(user_id) references users(id) on delete cascade
            )
        SQL);
        DB::statement(<<<SQL
            INSERT INTO event_participants (
                id,
                event_id,
                user_id,
                status,
                response_reason,
                response_mode,
                responded_at,
                checked_in_at,
                check_in_method,
                created_at,
                updated_at
            )
            SELECT
                id,
                event_id,
                user_id,
                status,
                response_reason,
                response_mode,
                responded_at,
                checked_in_at,
                check_in_method,
                created_at,
                updated_at
            FROM event_participants_old
        SQL);
        DB::statement('DROP TABLE event_participants_old');
        DB::statement('CREATE UNIQUE INDEX event_participants_event_id_user_id_unique ON event_participants (event_id, user_id)');
        DB::statement('PRAGMA foreign_keys=ON');
    }
};
