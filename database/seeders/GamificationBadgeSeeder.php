<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class GamificationBadgeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->badges() as $badge) {
            Badge::updateOrCreate(
                ['key' => $badge['key']],
                $badge,
            );
        }
    }

    private function badges(): array
    {
        return [
            ['key' => 'player_first_steps', 'name' => 'Erste Schritte', 'description' => 'Die ersten 50 XP gesammelt.', 'icon' => 'las la-seedling', 'actor_type' => 'sportler', 'trigger' => 'xp', 'threshold' => 50],
            ['key' => 'player_level_3', 'name' => 'Amateur-Level', 'description' => 'Level 3 erreicht.', 'icon' => 'las la-medal', 'actor_type' => 'sportler', 'trigger' => 'level', 'threshold' => 3],
            ['key' => 'player_streak_7', 'name' => '7 Tage aktiv', 'description' => 'Sieben Tage sinnvolle Aktivität am Stück.', 'icon' => 'las la-fire', 'actor_type' => 'sportler', 'trigger' => 'streak', 'threshold' => 7],
            ['key' => 'player_helpful_author', 'name' => 'Hilfreicher Inhalt', 'description' => 'Ein Beitrag wurde als hilfreich markiert.', 'icon' => 'las la-hands-helping', 'actor_type' => 'sportler', 'trigger' => 'reason', 'threshold' => 1, 'meta' => ['reason' => 'knowledge_marked_helpful']],
            ['key' => 'player_course_completed', 'name' => 'Kurs erfolgreich abgeschlossen', 'description' => 'Einen vollständigen Airmius-Kurs mit allen Pflichtbestandteilen abgeschlossen.', 'icon' => 'las la-graduation-cap', 'actor_type' => 'sportler', 'trigger' => 'reason', 'threshold' => 1, 'meta' => ['reason' => 'course_completed']],
            ['key' => 'coach_knowledge_builder', 'name' => 'Wissens-Coach', 'description' => 'Trainer teilt hilfreiches Wissen.', 'icon' => 'las la-chalkboard-teacher', 'actor_type' => 'trainer', 'trigger' => 'reason', 'threshold' => 1, 'meta' => ['reason' => 'coach_knowledge_shared']],
            ['key' => 'club_first_event', 'name' => 'Aktiver Verein', 'description' => 'Der Verein hat sein erstes Event erstellt.', 'icon' => 'las la-calendar-check', 'actor_type' => 'verein', 'trigger' => 'reason', 'threshold' => 1, 'meta' => ['reason' => 'event_created']],
            ['key' => 'club_growth_250', 'name' => 'Wachsender Verein', 'description' => 'Der Verein hat 250 XP erreicht.', 'icon' => 'las la-trophy', 'actor_type' => 'verein', 'trigger' => 'xp', 'threshold' => 250],
            ['key' => 'team_first_training', 'name' => 'Team in Bewegung', 'description' => 'Das Team hat seine erste Trainingseinheit erstellt.', 'icon' => 'las la-running', 'actor_type' => 'team', 'trigger' => 'reason', 'threshold' => 1, 'meta' => ['reason' => 'training_created']],
        ];
    }
}
