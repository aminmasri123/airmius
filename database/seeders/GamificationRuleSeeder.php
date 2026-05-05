<?php

namespace Database\Seeders;

use App\Models\GamificationRule;
use Illuminate\Database\Seeder;

class GamificationRuleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rules() as $rule) {
            GamificationRule::updateOrCreate(
                [
                    'key' => $rule['key'],
                    'actor_type' => $rule['actor_type'],
                ],
                $rule,
            );
        }
    }

    private function rules(): array
    {
        return [
            ['key' => 'sport_profile_added', 'actor_type' => 'sportler', 'category' => 'Profil & Skills', 'label' => 'Sportart hinzugefügt', 'description' => 'Ein Nutzer ergänzt eine Sportart im sportlichen Profil.', 'xp_amount' => 15, 'daily_limit' => null, 'trust_delta' => 1],
            ['key' => 'skill_profile_refined', 'actor_type' => 'sportler', 'category' => 'Profil & Skills', 'label' => 'Skill bearbeitet', 'description' => 'Beschreibung, Sichtbarkeit oder Einschätzung eines Skills wurde gepflegt.', 'xp_amount' => 3, 'daily_limit' => 3, 'trust_delta' => 1],
            ['key' => 'skill_level_improved', 'actor_type' => 'sportler', 'category' => 'Profil & Skills', 'label' => 'Skill-Level verbessert', 'description' => 'Der eigene Skill wurde auf eine höhere Entwicklungsstufe gesetzt.', 'xp_amount' => 8, 'daily_limit' => 3, 'trust_delta' => 1],
            ['key' => 'skill_endorsed', 'actor_type' => 'sportler', 'category' => 'Soziale Interaktionen', 'label' => 'Skill bestätigt', 'description' => 'Eine andere Person bestätigt einen Skill.', 'xp_amount' => 10, 'daily_limit' => 3, 'trust_delta' => 1],
            ['key' => 'recommendation_approved', 'actor_type' => 'sportler', 'category' => 'Soziale Interaktionen', 'label' => 'Empfehlung freigegeben', 'description' => 'Eine erhaltene Empfehlung wird auf dem Profil veröffentlicht.', 'xp_amount' => 10, 'daily_limit' => 2, 'trust_delta' => 1],
            ['key' => 'training_accepted', 'actor_type' => 'sportler', 'category' => 'Training', 'label' => 'Training zugesagt', 'description' => 'Teilnahme an einem Training wurde zugesagt.', 'xp_amount' => 5, 'daily_limit' => 3, 'trust_delta' => 0],
            ['key' => 'training_check_in', 'actor_type' => 'sportler', 'category' => 'Training', 'label' => 'Training Check-in', 'description' => 'Teilnahme wurde durch Check-in dokumentiert.', 'xp_amount' => 2, 'daily_limit' => 3, 'trust_delta' => 1],
            ['key' => 'content_created', 'actor_type' => 'sportler', 'category' => 'Inhalte & Beiträge', 'label' => 'Informativer Beitrag erstellt', 'description' => 'Wissens-, Trainings-, Taktik-, Analyse- oder Erfahrungsbeitrag wurde erstellt.', 'xp_amount' => 5, 'daily_limit' => 3, 'trust_delta' => 1],
            ['key' => 'knowledge_marked_helpful', 'actor_type' => 'sportler', 'category' => 'Inhalte & Beiträge', 'label' => 'Beitrag als hilfreich markiert', 'description' => 'Ein sportbezogener Wissensbeitrag wurde von einer anderen Person als hilfreich bewertet.', 'xp_amount' => 5, 'daily_limit' => 3, 'trust_delta' => 1],
            ['key' => 'daily_meaningful_activity', 'actor_type' => 'sportler', 'category' => 'Aktivität', 'label' => 'Tägliche sinnvolle Aktivität', 'description' => 'Mindestens eine sinnvolle Aktion am Tag, nicht nur Login.', 'xp_amount' => 2, 'daily_limit' => 1, 'trust_delta' => 0],

            ['key' => 'training_created', 'actor_type' => 'verein', 'category' => 'Organisation & Trainingsbetrieb', 'label' => 'Training erstellt', 'description' => 'Ein Verein erstellt eine Trainingseinheit.', 'xp_amount' => 5, 'daily_limit' => null, 'trust_delta' => 0],
            ['key' => 'training_verified', 'actor_type' => 'verein', 'category' => 'Organisation & Trainingsbetrieb', 'label' => 'Training durchgeführt', 'description' => 'Training wurde verifiziert durchgeführt.', 'xp_amount' => 10, 'daily_limit' => null, 'trust_delta' => 1],
            ['key' => 'event_created', 'actor_type' => 'verein', 'category' => 'Events & Veranstaltungen', 'label' => 'Event erstellt', 'description' => 'Verein erstellt ein Event.', 'xp_amount' => 10, 'daily_limit' => null, 'trust_delta' => 0],
            ['key' => 'event_completed', 'actor_type' => 'verein', 'category' => 'Events & Veranstaltungen', 'label' => 'Event durchgeführt', 'description' => 'Event wurde durchgeführt.', 'xp_amount' => 25, 'daily_limit' => null, 'trust_delta' => 1],
            ['key' => 'club_profile_completed', 'actor_type' => 'verein', 'category' => 'Struktur & Verwaltung', 'label' => 'Vereinsprofil vollständig', 'description' => 'Vereinsprofil ist strukturiert und vollständig gepflegt.', 'xp_amount' => 15, 'daily_limit' => null, 'trust_delta' => 1],
            ['key' => 'club_informative_post', 'actor_type' => 'verein', 'category' => 'Inhalte & Kommunikation', 'label' => 'Vereinsbeitrag informativ', 'description' => 'Vereinsnews oder Trainingsbericht mit Mehrwert.', 'xp_amount' => 8, 'daily_limit' => 3, 'trust_delta' => 1],

            ['key' => 'training_created', 'actor_type' => 'team', 'category' => 'Team & Training', 'label' => 'Training erstellt', 'description' => 'Ein Team erstellt eine Trainingseinheit.', 'xp_amount' => 5, 'daily_limit' => null, 'trust_delta' => 0],
            ['key' => 'event_created', 'actor_type' => 'team', 'category' => 'Team & Training', 'label' => 'Team-Event erstellt', 'description' => 'Ein Team plant ein Event oder Training.', 'xp_amount' => 5, 'daily_limit' => null, 'trust_delta' => 0],
            ['key' => 'team_member_joined', 'actor_type' => 'team', 'category' => 'Teamaufbau', 'label' => 'Teammitglied hinzugefuegt', 'description' => 'Ein neues Mitglied wurde ins Team aufgenommen.', 'xp_amount' => 3, 'daily_limit' => 5, 'trust_delta' => 0],

            ['key' => 'coach_feedback_created', 'actor_type' => 'trainer', 'category' => 'Bewertung & Feedback', 'label' => 'Konstruktives Feedback', 'description' => 'Trainer dokumentiert hilfreiches Feedback.', 'xp_amount' => 8, 'daily_limit' => 3, 'trust_delta' => 1],
            ['key' => 'training_plan_created', 'actor_type' => 'trainer', 'category' => 'Trainingsmanagement', 'label' => 'Trainingsplan erstellt', 'description' => 'Trainer erstellt einen strukturierten Trainingsplan.', 'xp_amount' => 10, 'daily_limit' => 3, 'trust_delta' => 1],
            ['key' => 'coach_knowledge_shared', 'actor_type' => 'trainer', 'category' => 'Inhalte & Wissenstransfer', 'label' => 'Wissen geteilt', 'description' => 'Trainer teilt Methode, Analyse oder Übungsmaterial.', 'xp_amount' => 10, 'daily_limit' => 3, 'trust_delta' => 1],

            ['key' => 'training_no_show', 'actor_type' => 'sportler', 'category' => 'Strafen', 'label' => 'No-Show', 'description' => 'Unentschuldigtes Fernbleiben trotz Anmeldung.', 'xp_amount' => -5, 'daily_limit' => null, 'trust_delta' => -3, 'is_penalty' => true],
            ['key' => 'false_confirmation', 'actor_type' => 'sportler', 'category' => 'Strafen', 'label' => 'Falsche Bestätigung', 'description' => 'Manipulierte oder falsche Bestätigung.', 'xp_amount' => -50, 'daily_limit' => null, 'trust_delta' => -12, 'is_penalty' => true],
            ['key' => 'spam_or_abuse', 'actor_type' => 'sportler', 'category' => 'Strafen', 'label' => 'Spam oder Missbrauch', 'description' => 'Missbräuchliche Nutzung oder Spam.', 'xp_amount' => -30, 'daily_limit' => null, 'trust_delta' => -8, 'is_penalty' => true],
            ['key' => 'recommendation_rejected', 'actor_type' => 'sportler', 'category' => 'Strafen', 'label' => 'Empfehlung abgelehnt', 'description' => 'Eine Empfehlung wurde abgelehnt.', 'xp_amount' => -5, 'daily_limit' => null, 'trust_delta' => -2, 'is_penalty' => true],
            ['key' => 'excessive_usage', 'actor_type' => 'sportler', 'category' => 'Strafen', 'label' => 'Übermäßige Nutzung', 'description' => 'XP wird wegen exzessiver Nutzung reduziert oder ausgesetzt.', 'xp_amount' => -5, 'daily_limit' => null, 'trust_delta' => -1, 'is_penalty' => true],
        ];
    }
}
