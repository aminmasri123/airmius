<?php

namespace Database\Seeders;

use App\Models\Sport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SportsSeeder extends Seeder
{
    public function run(): void
    {
        $sports = [
            ['Fußball', 'Olympisch / weltweit'],
            ['Futsal', 'Weltweit'],
            ['Beach Soccer', 'Weltweit'],
            ['American Football', 'IOC-anerkannt'],
            ['Flag Football', 'Olympisch / weltweit'],
            ['Australian Football', 'Weltweit'],
            ['Rugby Union', 'Olympisch / weltweit'],
            ['Rugby League', 'Weltweit'],
            ['Basketball', 'Olympisch'],
            ['3x3 Basketball', 'Olympisch'],
            ['Volleyball', 'Olympisch'],
            ['Beachvolleyball', 'Olympisch'],
            ['Handball', 'Olympisch'],
            ['Hockey', 'Olympisch'],
            ['Eishockey', 'Olympisch'],
            ['Bandy', 'IOC-anerkannt'],
            ['Floorball', 'IOC-anerkannt'],
            ['Netball', 'IOC-anerkannt'],
            ['Korfball', 'IOC-anerkannt'],
            ['Cricket', 'IOC-anerkannt'],
            ['Baseball', 'IOC-anerkannt'],
            ['Softball', 'IOC-anerkannt'],
            ['Lacrosse', 'IOC-anerkannt'],
            ['Polo', 'IOC-anerkannt'],
            ['Tennis', 'Olympisch'],
            ['Tischtennis', 'Olympisch'],
            ['Badminton', 'Olympisch'],
            ['Squash', 'IOC-anerkannt'],
            ['Racquetball', 'IOC-anerkannt'],
            ['Padel', 'Weltweit'],
            ['Pickleball', 'Weltweit'],
            ['Beach Tennis', 'Weltweit'],
            ['Golf', 'Olympisch'],
            ['Minigolf', 'Weltweit'],
            ['Leichtathletik', 'Olympisch'],
            ['Straßenlauf', 'Weltweit'],
            ['Crosslauf', 'Weltweit'],
            ['Trailrunning', 'Weltweit'],
            ['Triathlon', 'Olympisch'],
            ['Moderner Fünfkampf', 'Olympisch'],
            ['Radsport', 'Olympisch'],
            ['BMX Racing', 'Olympisch'],
            ['BMX Freestyle', 'Olympisch'],
            ['Mountainbike', 'Olympisch'],
            ['Bahnradsport', 'Olympisch'],
            ['Straßenradsport', 'Olympisch'],
            ['Schwimmen', 'Olympisch'],
            ['Freiwasserschwimmen', 'Olympisch'],
            ['Wasserspringen', 'Olympisch'],
            ['Wasserball', 'Olympisch'],
            ['Synchronschwimmen', 'Olympisch'],
            ['Rudern', 'Olympisch'],
            ['Kanu-Rennsport', 'Olympisch'],
            ['Kanu-Slalom', 'Olympisch'],
            ['Segeln', 'Olympisch'],
            ['Surfen', 'Olympisch'],
            ['Rettungssport', 'IOC-anerkannt'],
            ['Unterwassersport', 'IOC-anerkannt'],
            ['Wasserski und Wakeboard', 'IOC-anerkannt'],
            ['Powerboating', 'IOC-anerkannt'],
            ['Turnen', 'Olympisch'],
            ['Kunstturnen', 'Olympisch'],
            ['Rhythmische Sportgymnastik', 'Olympisch'],
            ['Trampolinturnen', 'Olympisch'],
            ['Parkour', 'Weltweit'],
            ['Gewichtheben', 'Olympisch'],
            ['Powerlifting', 'Weltweit'],
            ['Bodybuilding und Fitness', 'Weltweit'],
            ['Boxen', 'Olympisch'],
            ['Judo', 'Olympisch'],
            ['Ringen', 'Olympisch'],
            ['Taekwondo', 'Olympisch'],
            ['Karate', 'IOC-anerkannt'],
            ['Kickboxen', 'IOC-anerkannt'],
            ['Muay Thai', 'IOC-anerkannt'],
            ['Sambo', 'IOC-anerkannt'],
            ['Sumo', 'IOC-anerkannt'],
            ['Wushu', 'IOC-anerkannt'],
            ['Ju-Jitsu', 'Weltweit'],
            ['Aikido', 'Weltweit'],
            ['Fechten', 'Olympisch'],
            ['Bogenschießen', 'Olympisch'],
            ['Sportschießen', 'Olympisch'],
            ['Biathlon', 'Olympisch'],
            ['Ski Alpin', 'Olympisch'],
            ['Skilanglauf', 'Olympisch'],
            ['Skispringen', 'Olympisch'],
            ['Nordische Kombination', 'Olympisch'],
            ['Freestyle-Skiing', 'Olympisch'],
            ['Snowboard', 'Olympisch'],
            ['Ski-Bergsteigen', 'IOC-anerkannt'],
            ['Eiskunstlauf', 'Olympisch'],
            ['Eisschnelllauf', 'Olympisch'],
            ['Shorttrack', 'Olympisch'],
            ['Curling', 'Olympisch'],
            ['Bob', 'Olympisch'],
            ['Skeleton', 'Olympisch'],
            ['Rennrodeln', 'Olympisch'],
            ['Klettern', 'Olympisch'],
            ['Bergsteigen', 'IOC-anerkannt'],
            ['Orienteering', 'IOC-anerkannt'],
            ['Skateboarding', 'Olympisch'],
            ['Roller Sports', 'Weltweit'],
            ['Inline-Speedskating', 'Weltweit'],
            ['Reiten', 'Olympisch'],
            ['Dressurreiten', 'Olympisch'],
            ['Springreiten', 'Olympisch'],
            ['Vielseitigkeitsreiten', 'Olympisch'],
            ['Motorsport', 'IOC-anerkannt'],
            ['Motorradsport', 'IOC-anerkannt'],
            ['Air Sports', 'IOC-anerkannt'],
            ['Billiard Sports', 'IOC-anerkannt'],
            ['Bowling', 'IOC-anerkannt'],
            ['Boules', 'IOC-anerkannt'],
            ['Darts', 'Weltweit'],
            ['Schach', 'IOC-anerkannt'],
            ['Bridge', 'IOC-anerkannt'],
            ['Damespiel', 'Weltweit'],
            ['Go', 'Weltweit'],
            ['Dance Sport', 'IOC-anerkannt'],
            ['Breaking', 'Olympisch'],
            ['Cheerleading', 'IOC-anerkannt'],
            ['Flying Disc', 'IOC-anerkannt'],
            ['Ultimate Frisbee', 'Weltweit'],
            ['Tug of War', 'IOC-anerkannt'],
            ['Basque Pelota', 'IOC-anerkannt'],
            ['Ice Stock Sport', 'IOC-anerkannt'],
            ['Casting Sport', 'Weltweit'],
            ['Drachenboot', 'Weltweit'],
            ['Fistball', 'Weltweit'],
            ['Faustball', 'Weltweit'],
            ['Sepak Takraw', 'Weltweit'],
            ['Kabaddi', 'Weltweit'],
            ['Armwrestling', 'Weltweit'],
            ['Savate', 'Weltweit'],
            ['Teqball', 'Weltweit'],
        ];

        foreach ($sports as $index => [$name, $category]) {
            $sport = Sport::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'category' => $this->categorySlug($category),
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );

            DB::table('clubs')->where('sport_type', $name)->update(['sport_type' => $sport->slug]);
            DB::table('teams')->where('sport_type', $name)->update(['sport_type' => $sport->slug]);
        }

        $legacy = [
            'football' => 'fussball',
            'soccer' => 'fussball',
            'basketball' => 'basketball',
            'tennis' => 'tennis',
            'running' => 'strassenlauf',
            'cycling' => 'radsport',
        ];

        foreach ($legacy as $old => $new) {
            DB::table('clubs')->where('sport_type', $old)->update(['sport_type' => $new]);
            DB::table('teams')->where('sport_type', $old)->update(['sport_type' => $new]);
        }
    }

    private function categorySlug(string $category): string
    {
        return match ($category) {
            'Olympisch' => 'olympic',
            'Olympisch / weltweit' => 'olympic_worldwide',
            'IOC-anerkannt' => 'ioc_recognized',
            default => 'worldwide',
        };
    }
}
