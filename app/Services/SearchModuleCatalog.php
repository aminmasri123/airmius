<?php

namespace App\Services;

use App\Http\Controllers\ClubCockpitController;
use App\Http\Controllers\TrainerCockpitController;
use App\Models\Club;
use App\Models\Team;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class SearchModuleCatalog
{
    public function __construct(private readonly RecruitingPipelineService $recruiting) {}

    /** @return Collection<int, array<string, mixed>> */
    public function search(User $user, string $term, int $limit): Collection
    {
        $needle = mb_strtolower(trim($term));
        $asciiNeedle = Str::lower(Str::ascii($needle));

        return collect($this->definitions($user))
            ->map(function (array $definition, int $index): array {
                $title = __('search.modules.'.$definition['key']);

                return $definition + [
                    'id' => 100_000 + $index,
                    'title' => $title,
                    'subtitle' => __('search.module_hint', ['title' => $title]),
                ];
            })
            ->filter(function (array $definition) use ($needle, $asciiNeedle): bool {
                $haystack = mb_strtolower(implode(' ', [
                    $definition['key'],
                    $definition['title'],
                    $definition['aliases'],
                ]));

                if (str_contains($haystack, $needle)) {
                    return true;
                }

                return $asciiNeedle !== ''
                    && str_contains(Str::lower(Str::ascii($haystack)), $asciiNeedle);
            })
            ->filter(fn (array $definition): bool => ($definition['allowed'])())
            ->take($limit)
            ->map(fn (array $definition): array => [
                'type' => 'module',
                'type_label' => __('search.types.module'),
                'id' => $definition['id'],
                'title' => $definition['title'],
                'subtitle' => $definition['subtitle'],
                'url' => route($definition['route'], $definition['parameters']),
                'module_key' => $definition['key'],
                'icon' => $definition['icon'],
            ])
            ->values();
    }

    /** @return array<int, array<string, mixed>> */
    private function definitions(User $user): array
    {
        $always = static fn (): bool => true;
        $notGuest = fn (): bool => ! $user->hasRole('guest');
        $canUseRides = fn (): bool => $user->hasAnyRole(array_merge(
            Roles::FULL_ACCESS,
            Roles::PLAYER,
            Roles::PARENT,
            ['club_owner', 'club_admin', 'club_manager', 'coach', 'trainer'],
        ));

        return [
            $this->definition('workspaces', 'auth.workspaces.index', 'las la-layer-group', 'workspace arbeitsbereich espaces مساحات', $always),
            $this->definition('training', 'auth.training.index', 'las la-dumbbell', 'training workout plan entraînement تدريب تمارين', $always),
            $this->definition('events', 'auth.events.index', 'las la-calendar-check', 'events termine kalender événements فعاليات تقويم', $always),
            $this->definition('nutrition', 'auth.nutrition.index', 'las la-apple-alt', 'ernährung trinken wasser nutrition hydration alimentation تغذية ماء', $always),
            $this->definition('sport_map', 'auth.sport-map.index', 'las la-route', 'sportkarte route gps map carte خريطة مسار', $always),
            $this->definition('sport_matching', 'auth.sport-matching.index', 'las la-random', 'sport matching partner gegner partenaire مطابقة شريك', $always),
            $this->definition('challenges', 'auth.challenges.index', 'las la-flag-checkered', 'challenge herausforderung schritte streak défi تحدي خطوات', $always),
            $this->definition('friends', 'auth.friends.index', 'las la-user-friends', 'freunde friends amis أصدقاء', $always),
            $this->definition('feed', 'auth.feed.index', 'las la-newspaper', 'feed story community fil actualités مجتمع قصص', $notGuest),
            $this->definition('messages', 'auth.conversations.index', 'las la-comments', 'chat nachrichten messages conversation رسائل محادثة', $always),
            $this->definition('files', 'auth.files.index', 'las la-folder-open', 'dateien file manager fichiers ملفات', $always),
            $this->definition('courses', 'auth.learning.my-courses.index', 'las la-graduation-cap', 'lernen kurse elearning courses apprentissage cours تعلم دورات', $always),
            $this->definition('marketplace', 'guest.marketplace', 'las la-shopping-bag', 'marketplace shop markt boutique متجر سوق', $always),
            $this->definition('blog', 'guest.blog.index', 'las la-pen-nib', 'blog artikel content articles مدونة مقالات', $always),
            $this->definition('outfit', 'auth.outfit-subscriptions.index', 'las la-tshirt', 'outfit abo kleidung vêtements tenue ملابس اشتراك', $always),
            $this->definition('badges', 'auth.badges.index', 'las la-medal', 'badges leveling xp abzeichen niveaux شارات نقاط', $always),
            $this->definition('maturity', 'auth.maturity.index', 'las la-chart-line', 'fortschritt reife analyse maturity progrès تقدم تحليل', $always),
            $this->definition('settings', 'auth.settings', 'las la-cog', 'einstellungen profil datenschutz settings paramètres إعدادات خصوصية', $always),
            $this->definition('support', 'auth.support.index', 'las la-headset', 'support hilfe ticket help aide مساعدة دعم', $always),
            $this->definition('clubs', 'auth.teams.index', 'las la-building', 'verein vereine clubs équipes أندية فرق', fn (): bool => Gate::forUser($user)->allows('viewAny', Club::class)),
            $this->definition('teams', 'auth.teams.index', 'las la-users', 'team teams mannschaft équipe فريق', fn (): bool => Gate::forUser($user)->allows('viewAny', Team::class)),
            $this->definition('club_cockpit', 'auth.club-cockpit.index', 'las la-tachometer-alt', 'verein cockpit club management administration إدارة النادي', fn (): bool => $this->canOpenClubCockpit($user)),
            $this->definition('trainer_cockpit', 'auth.trainer-cockpit.index', 'las la-chalkboard-teacher', 'trainer coach cockpit entraîneur مدرب', fn (): bool => TrainerCockpitController::userCanView($user)),
            $this->definition('sponsor', 'auth.sponsor-workspace.index', 'las la-handshake', 'sponsor partner sponsoring partenaire راعي رعاية', fn (): bool => app(SponsorWorkspaceService::class)->canOpen($user)),
            $this->definition('recruiting', 'auth.recruiting-pipeline.index', 'las la-user-tie', 'recruiting jobs bewerbung recrutement وظائف توظيف', fn (): bool => $this->recruiting->canOpen($user)),
            $this->definition('users', 'members.index', 'las la-users-cog', 'nutzer mitglieder users members utilisateurs مستخدمون أعضاء', fn (): bool => $user->can('users.view')),
            $this->definition('roles', 'roles-permissions.index', 'las la-user-shield', 'rollen rechte permissions roles rôles أدوار صلاحيات', fn (): bool => $user->can('users.assign_roles')),
            $this->definition('commerce', 'admin.commerce.index', 'las la-chart-line', 'commerce bestellungen zahlungen orders payments commandes تجارة طلبات مدفوعات', fn (): bool => $user->can('subscriptions.manage')),
        ];
    }

    /** @return array<string, mixed> */
    private function definition(
        string $key,
        string $route,
        string $icon,
        string $aliases,
        callable $allowed,
        array $parameters = [],
    ): array {
        return compact('key', 'route', 'parameters', 'icon', 'aliases', 'allowed');
    }

    private function canOpenClubCockpit(User $user): bool
    {
        return ClubCockpitController::userCanView($user);
    }
}
