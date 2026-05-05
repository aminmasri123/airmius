<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]
            ->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | 1. PERMISSIONS (FULL SYSTEM + DESCRIPTION)
        |--------------------------------------------------------------------------
        */

        $globalPermissions = [
            ['name' => 'user.manage', 'description' => 'Benutzer global verwalten'],
            ['name' => 'org.create', 'description' => 'Organisationen erstellen'],
            ['name' => 'org.manage', 'description' => 'Organisationen verwalten'],
        ];

        $scopedPermissions = [
            // TEAM
            ['name' => 'team.create', 'description' => 'Teams erstellen'],
            ['name' => 'team.update', 'description' => 'Teams bearbeiten'],
            ['name' => 'team.delete', 'description' => 'Teams loeschen'],
            ['name' => 'team.invite', 'description' => 'Teammitglieder einladen'],
            ['name' => 'team.kick', 'description' => 'Teammitglieder entfernen'],

            // EVENT
            ['name' => 'event.create', 'description' => 'Events erstellen'],
            ['name' => 'event.update', 'description' => 'Events bearbeiten'],
            ['name' => 'event.delete', 'description' => 'Events loeschen'],
            ['name' => 'event.join', 'description' => 'Events beitreten'],

            // POST
            ['name' => 'post.create', 'description' => 'Beitraege erstellen'],
            ['name' => 'post.update', 'description' => 'Beitraege bearbeiten'],
            ['name' => 'post.delete', 'description' => 'Beitraege loeschen'],

            // FILE
            ['name' => 'file.upload', 'description' => 'Dateien hochladen'],
            ['name' => 'file.delete', 'description' => 'Dateien loeschen'],
            ['name' => 'file.view', 'description' => 'Dateien anzeigen'],

            // SOCIAL
            ['name' => 'follow.user', 'description' => 'Benutzern folgen'],
        ];

        $legacyPermissions = [

            // USERS
            ['name' => 'users.view', 'description' => 'Benutzer anzeigen'],
            ['name' => 'users.create', 'description' => 'Benutzer erstellen'],
            ['name' => 'users.edit', 'description' => 'Benutzer bearbeiten'],
            ['name' => 'users.delete', 'description' => 'Benutzer löschen'],
            ['name' => 'users.assign_roles', 'description' => 'Rollen an Benutzer vergeben'],
            ['name' => 'guardians.children.view', 'description' => 'Verknuepfte Kinderprofile anzeigen'],
            ['name' => 'guardians.children.manage', 'description' => 'Zustimmung und Kinderprofile verwalten'],

            // CLUBS
            ['name' => 'clubs.view', 'description' => 'Clubs anzeigen'],
            ['name' => 'clubs.create', 'description' => 'Clubs erstellen'],
            ['name' => 'clubs.edit', 'description' => 'Clubs bearbeiten'],
            ['name' => 'clubs.delete', 'description' => 'Clubs löschen'],
            ['name' => 'clubs.manage_members', 'description' => 'Club Mitglieder verwalten'],
            ['name' => 'clubs.manage_settings', 'description' => 'Club Einstellungen verwalten'],

            // TEAMS
            ['name' => 'teams.view', 'description' => 'Teams anzeigen'],
            ['name' => 'teams.create', 'description' => 'Teams erstellen'],
            ['name' => 'teams.edit', 'description' => 'Teams bearbeiten'],
            ['name' => 'teams.delete', 'description' => 'Teams löschen'],
            ['name' => 'teams.manage_players', 'description' => 'Spieler in Teams verwalten'],

            // TRAINING
            ['name' => 'training.view', 'description' => 'Trainingseinheiten anzeigen'],
            ['name' => 'training.create', 'description' => 'Trainingseinheiten erstellen'],
            ['name' => 'training.edit', 'description' => 'Trainingseinheiten bearbeiten'],
            ['name' => 'training.delete', 'description' => 'Trainingseinheiten löschen'],

            // MATCHES
            ['name' => 'matches.view', 'description' => 'Spiele anzeigen'],
            ['name' => 'matches.create', 'description' => 'Spiele erstellen'],
            ['name' => 'matches.edit', 'description' => 'Spiele bearbeiten'],
            ['name' => 'matches.delete', 'description' => 'Spiele löschen'],
            ['name' => 'matches.manage_lineup', 'description' => 'Aufstellung verwalten'],
            ['name' => 'matches.report', 'description' => 'Spielberichte erstellen'],

            // PLAYERS
            ['name' => 'players.view', 'description' => 'Spieler anzeigen'],
            ['name' => 'players.edit', 'description' => 'Spieler bearbeiten'],
            ['name' => 'players.stats.view', 'description' => 'Spieler Statistiken ansehen'],
            ['name' => 'players.stats.edit', 'description' => 'Spieler Statistiken bearbeiten'],
            ['name' => 'players.notes.view', 'description' => 'Spieler Notizen ansehen'],
            ['name' => 'players.notes.edit', 'description' => 'Spieler Notizen bearbeiten'],

            // ANALYTICS / PERFORMANCE
            ['name' => 'analytics.view', 'description' => 'Analysen anzeigen'],
            ['name' => 'performance.view', 'description' => 'Leistungsdaten anzeigen'],
            ['name' => 'video.upload', 'description' => 'Videos hochladen'],
            ['name' => 'video.analyze', 'description' => 'Videos analysieren'],
            ['name' => 'gps.data.view', 'description' => 'GPS Daten anzeigen'],
            ['name' => 'gps.data.export', 'description' => 'GPS Daten exportieren'],

            // MEDICAL
            ['name' => 'medical.records.view', 'description' => 'Medizinische Daten anzeigen'],
            ['name' => 'medical.records.edit', 'description' => 'Medizinische Daten bearbeiten'],
            ['name' => 'injuries.create', 'description' => 'Verletzungen erfassen'],
            ['name' => 'injuries.edit', 'description' => 'Verletzungen bearbeiten'],
            ['name' => 'injuries.close', 'description' => 'Verletzungen abschließen'],
            ['name' => 'recovery.plan.create', 'description' => 'Rehaplan erstellen'],
            ['name' => 'recovery.plan.edit', 'description' => 'Rehaplan bearbeiten'],
            ['name' => 'nutrition.plan.create', 'description' => 'Ernährungsplan erstellen'],
            ['name' => 'nutrition.plan.edit', 'description' => 'Ernährungsplan bearbeiten'],
            ['name' => 'mental.health.view', 'description' => 'Mentale Daten anzeigen'],
            ['name' => 'mental.health.edit', 'description' => 'Mentale Daten bearbeiten'],

            // FINANCE
            ['name' => 'finance.view', 'description' => 'Finanzen anzeigen'],
            ['name' => 'finance.edit', 'description' => 'Finanzen bearbeiten'],
            ['name' => 'billing.manage', 'description' => 'Abrechnung verwalten'],
            ['name' => 'subscriptions.manage', 'description' => 'Abonnements verwalten'],
            ['name' => 'outfit-subscriptions.view', 'description' => 'Sportkleidung-Abos verwenden'],
            ['name' => 'outfit-subscriptions.manage', 'description' => 'Sportkleidung-Abo-Modul verwalten'],

            // CONTENT / MEDIA
            ['name' => 'content.create', 'description' => 'Inhalte erstellen'],
            ['name' => 'content.edit', 'description' => 'Inhalte bearbeiten'],
            ['name' => 'content.delete', 'description' => 'Inhalte löschen'],
            ['name' => 'blog.view', 'description' => 'Blogbeitraege anzeigen'],
            ['name' => 'blog.create', 'description' => 'Blogbeitraege erstellen'],
            ['name' => 'blog.update', 'description' => 'Blogbeitraege bearbeiten'],
            ['name' => 'blog.delete', 'description' => 'Blogbeitraege loeschen'],
            ['name' => 'blog.publish', 'description' => 'Blogbeitraege veroeffentlichen'],
            ['name' => 'blog.manage', 'description' => 'Blog-CMS verwalten'],
            ['name' => 'media.upload', 'description' => 'Medien hochladen'],
            ['name' => 'media.delete', 'description' => 'Medien löschen'],
            ['name' => 'seo.manage', 'description' => 'SEO verwalten'],

            // SYSTEM
            ['name' => 'system.manage', 'description' => 'System verwalten'],
            ['name' => 'logs.view', 'description' => 'Logs anzeigen'],
            ['name' => 'api.manage', 'description' => 'API verwalten'],
            ['name' => 'security.manage', 'description' => 'Sicherheit verwalten'],

            // COMMUNITY
            ['name' => 'community.moderate', 'description' => 'Community moderieren'],
            ['name' => 'support.tickets', 'description' => 'Support Tickets bearbeiten'],

            // EVENTS
            ['name' => 'events.manage', 'description' => 'Events verwalten'],
            ['name' => 'tournaments.manage', 'description' => 'Turniere verwalten'],
            ['name' => 'facilities.manage', 'description' => 'Anlagen verwalten'],
            ['name' => 'equipment.manage', 'description' => 'Equipment verwalten'],
        ];

        $permissions = array_merge($globalPermissions, $scopedPermissions, $legacyPermissions);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name']],
                ['description' => $permission['description']]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. ROLES (WITH DESCRIPTION)
        |--------------------------------------------------------------------------
        */

        $roles = [
            //👑 SYSTEM (GLOBAL)
            ['name' => 'super_admin', 'description' => 'Globaler Systemadministrator mit Vollzugriff'],
            ['name' => 'admin', 'description' => 'System Administrator auf Plattform Ebene'],
            ['name' => 'system_admin', 'description' => 'Technischer System Admin'],
            ['name' => 'support', 'description' => 'Support Mitarbeiter für Tickets & Hilfe'],
            ['name' => 'redaktor', 'description' => 'Website Redaktion für Blog und Inhalte'],

            //🏢 CLUB MANAGEMENT
            ['name' => 'club_owner', 'description' => 'Besitzer eines Clubs'],
            ['name' => 'club_admin', 'description' => 'Verwaltet einen Club vollständig'],
            ['name' => 'club_manager', 'description' => 'Operativer Manager eines Clubs'],
            ['name' => 'academy_manager', 'description' => 'Leitet Akademie'],
            ['name' => 'financial_controller', 'description' => 'Finanz Kontrolle'],
            ['name' => 'media_manager', 'description' => 'Medien & Content'],

            //⚽ TEAM & Sport

            ['name' => 'coach', 'description' => 'Trainer'],
            ['name' => 'assistant_coach', 'description' => 'Assistent Trainer'],
            ['name' => 'performance_coach', 'description' => 'Performance Coach'],
            ['name' => 'fitness_coach', 'description' => 'Fitness Trainer'],
            ['name' => 'team_manager', 'description' => 'Team Organisation'],
            ['name' => 'captain', 'description' => 'Team Kapitän'],

            //👤 PLAYER

            ['name' => 'player', 'description' => 'Spieler'],
            ['name' => 'youth_player', 'description' => 'Jugendspieler'],
            ['name' => 'minor_pending_consent', 'description' => 'Minderjaehriger Account wartet auf Zustimmung der Erziehungsberechtigten'],
            ['name' => 'minor_player', 'description' => 'Minderjaehriger Spieler mit Zustimmung der Erziehungsberechtigten'],
            ['name' => 'guest_player', 'description' => 'Gastspieler'],

            //👪 COMMUNITY & Familly & OTHER
            ['name' => 'parent', 'description' => 'Elternteil'],
            ['name' => 'guardian', 'description' => 'Erziehungsberechtigter'],

            //👀 PASSIVE
            ['name' => 'viewer', 'description' => 'Nur Ansicht Zugriff'],
            ['name' => 'guest', 'description' => 'Gast Benutzer'],
            ['name' => 'data_analyst', 'description' => 'Analysiert Leistungsdaten'],
            ['name' => 'physiotherapist', 'description' => 'Physiotherapeut'],


        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. ROLE ASSIGNMENTS (CLEAN + NO WILDCARDS)
        |--------------------------------------------------------------------------
        */

        Role::findByName('super_admin')
            ->givePermissionTo(Permission::all());

        Role::findByName('admin')->givePermissionTo([
            'user.manage','org.create','org.manage',
            'team.create','team.update','team.delete','team.invite','team.kick',
            'event.create','event.update','event.delete','event.join',
            'post.create','post.update','post.delete',
            'file.upload','file.delete','file.view',
            'follow.user',
            'users.view','users.create','users.edit','users.delete','users.assign_roles',
            'clubs.view','clubs.create','clubs.edit','clubs.delete','clubs.manage_members','clubs.manage_settings',
            'teams.view','teams.create','teams.edit','teams.delete','teams.manage_players',
            'training.view','training.create','training.edit','training.delete',
            'matches.view','matches.create','matches.edit','matches.delete','matches.manage_lineup','matches.report',
            'players.view','players.edit','players.stats.view',
            'analytics.view','logs.view','system.manage',
            'blog.view','blog.create','blog.update','blog.delete','blog.publish','blog.manage',
        ]);

        Role::findByName('redaktor')->givePermissionTo([
            'blog.view',
            'blog.create',
            'blog.update',
        ]);

        Role::findByName('support')->givePermissionTo([
            'users.view',
            'support.tickets',
            'community.moderate',
        ]);

        Role::findByName('club_owner')->givePermissionTo([
            'org.create','org.manage',
            'team.create','team.update','team.delete','team.invite','team.kick',
            'event.create','event.update','event.delete','event.join',
            'post.create','post.update','post.delete',
            'file.upload','file.delete','file.view',
            'follow.user',
            'clubs.view','clubs.create','clubs.edit','clubs.delete','clubs.manage_members','clubs.manage_settings',
            'teams.view','teams.manage_players',
            'training.view','training.create','training.edit',
            'matches.view','matches.create','matches.edit',
            'finance.view','billing.manage',
        ]);

        Role::findByName('club_admin')->givePermissionTo([
            'org.manage',
            'team.create','team.update','team.delete','team.invite','team.kick',
            'event.create','event.update','event.delete','event.join',
            'post.create','post.update','post.delete',
            'file.upload','file.delete','file.view',
            'follow.user',
            'clubs.manage_members','clubs.manage_settings',
            'teams.view','teams.edit','teams.manage_players',
            'training.view','training.edit',
            'matches.view','matches.edit',
            'players.view',
        ]);

        Role::findByName('club_manager')->givePermissionTo([
            'org.manage',
            'team.create','team.update','team.invite','team.kick',
            'event.create','event.update',
            'post.create','post.update',
            'file.upload','file.view',
            'follow.user',
            'clubs.view','teams.view',
        ]);

        Role::findByName('academy_manager')->givePermissionTo([
            'org.manage',
            'team.create','team.update','team.delete','team.invite','team.kick',
            'event.create','event.update','event.delete',
            'post.create','post.update',
            'file.upload','file.view',
            'follow.user',
            'clubs.view','teams.view',
        ]);

        Role::findByName('coach')->givePermissionTo([
            'team.create','team.update','team.invite','team.kick',
            'event.create','event.update','event.delete',
            'post.create','post.update',
            'file.upload','file.view',
            'follow.user',
            'teams.view','teams.manage_players',
            'training.view','training.create','training.edit',
            'matches.view','matches.manage_lineup',
            'players.view','players.stats.view',
            'video.analyze','analytics.view',
        ]);

        Role::findByName('assistant_coach')->givePermissionTo([
            'team.update','team.invite',
            'event.create','event.update',
            'post.create','post.update',
            'file.upload','file.view',
            'follow.user',
            'clubs.view','teams.view',
        ]);

        Role::findByName('performance_coach')->givePermissionTo([
            'team.update',
            'event.create','event.update',
            'post.create','post.update',
            'file.upload','file.view',
            'follow.user',
            'clubs.view','teams.view',
        ]);

        Role::findByName('fitness_coach')->givePermissionTo([
            'team.update',
            'event.create','event.update',
            'post.create','post.update',
            'file.upload','file.view',
            'follow.user',
            'clubs.view','teams.view',
        ]);

        Role::findByName('team_manager')->givePermissionTo([
            'team.update','team.invite','team.kick',
            'event.create','event.update',
            'post.create','post.update',
            'file.upload','file.view',
            'follow.user',
            'clubs.view','teams.view',
        ]);

        Role::findByName('captain')->givePermissionTo([
            'team.invite',
            'event.join',
            'post.create','post.update','post.delete',
            'file.view',
            'follow.user',
            'clubs.view','teams.view',
        ]);

        Role::findByName('player')->givePermissionTo([
            'event.join',
            'post.create','post.update','post.delete',
            'file.view',
            'follow.user',
            'clubs.view','teams.view',
            'training.view',
            'matches.view',
            'players.stats.view',
        ]);

        Role::findByName('minor_pending_consent')->givePermissionTo([
            'clubs.view','teams.view',
            'training.view',
            'matches.view',
        ]);

        Role::findByName('minor_player')->givePermissionTo([
            'event.join',
            'file.view',
            'follow.user',
            'clubs.view','teams.view',
            'training.view',
            'matches.view',
            'players.stats.view',
        ]);

        Role::findByName('youth_player')->givePermissionTo([
            'event.join',
            'post.create','post.update',
            'file.view',
            'follow.user',
            'clubs.view','teams.view',
        ]);

        Role::findByName('guest_player')->givePermissionTo([
            'event.join',
            'file.view',
            'follow.user',
            'clubs.view','teams.view',
        ]);

        Role::findByName('parent')->givePermissionTo([
            'file.view',
            'follow.user',
            'clubs.view','teams.view',
            'players.view',
            'training.view',
            'matches.view',
            'guardians.children.view',
            'guardians.children.manage',
        ]);

        Role::findByName('guardian')->givePermissionTo([
            'file.view',
            'follow.user',
            'clubs.view','teams.view',
            'players.view',
            'training.view',
            'matches.view',
            'guardians.children.view',
            'guardians.children.manage',
        ]);

        Role::findByName('viewer')->givePermissionTo([
            'file.view',
            'follow.user',
            'clubs.view','teams.view',
        ]);

        Role::findByName('data_analyst')->givePermissionTo([
            'analytics.view',
            'performance.view',
            'video.analyze',
            'gps.data.view',
        ]);

        Role::findByName('physiotherapist')->givePermissionTo([
            'medical.records.view',
            'injuries.edit',
            'recovery.plan.edit',
        ]);

        Role::findByName('financial_controller')->givePermissionTo([
            'finance.view',
            'billing.manage',
            'subscriptions.manage',
        ]);

        Role::findByName('media_manager')->givePermissionTo([
            'post.create','post.update','post.delete',
            'file.upload','file.delete','file.view',
            'follow.user',
            'content.create','content.edit','content.delete',
            'media.upload','media.delete',
            'seo.manage',
        ]);

        Role::findByName('system_admin')->givePermissionTo([
            'user.manage',
            'org.manage',
            'system.manage',
            'logs.view',
            'api.manage',
            'security.manage',
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]
            ->forgetCachedPermissions();
    }
}
