<?php

$root = dirname(__DIR__);
$object = '7742fb7ceb59797c34a8ce382fbd5c1e8e0e365d';
$sportsObject = 'd628b5322d22d8f29bbf6ab48d0c9f446be3486a';
$sourcePath = $root.'/resources/js/lang/de.json';
$targetPath = $root.'/resources/js/lang/ar.json';

$contents = shell_exec('git -C '.escapeshellarg($root).' cat-file blob '.escapeshellarg($object));
if (! is_string($contents) || $contents === '') {
    throw new RuntimeException("Arabic recovery object {$object} is unavailable.");
}

$arabic = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
$german = json_decode(file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR);

// Texts added after the last intact Arabic catalog snapshot.
$arabic['Mitglieder & Finanzen'] = 'الأعضاء والشؤون المالية';

// Preserve count placeholders even when Arabic singular grammar could omit the number.
$arabic['guest']['jobs']['results']['professional_one'] = 'تم العثور على {total} دور مهني';
$arabic['guest']['jobs']['results']['volunteer_one'] = 'تم العثور على {total} فرصة تطوعية';
$arabic['guest']['jobs']['results']['open_one'] = 'تم العثور على {total} دور مفتوح';

// These nine strings were already damaged in the otherwise intact snapshot.
$arabic['auto']['Aus deinem Gewicht berechnet.'] = 'محسوب من وزنك.';
$arabic['auto']['Automatisch'] = 'تلقائي';
$arabic['auto']['Lebensmittel'] = 'الأطعمة';
$arabic['auto']['Manuell'] = 'يدوي';
$arabic['auto']['Modus'] = 'الوضع';
$arabic['auto']['Standard, bis Gewicht gepflegt ist.'] = 'القيمة الافتراضية حتى يتم إدخال الوزن.';
$arabic['auto']['Verlauf'] = 'السجل';
$arabic['auto']['heute'] = 'اليوم';
$arabic['auto']['min'] = 'دقيقة';

$arabic['auto_patterns'][0]['target'] = 'استخدم "$1"';
$arabic['auto_patterns'][1]['target'] = '$1 اليوم';
$arabic['auto_patterns'][2]['target'] = '$1 إدخالات';
$arabic['auto_patterns'][3]['target'] = '$1 سعرة حرارية متبقية للوصول إلى هدفك اليومي.';
$arabic['auto_patterns'][4]['target'] = 'تم تناول $1 سعرة حرارية';

// German is the only source of truth: remove stale targets and retain source order.
$ordered = [];
foreach ($german as $key => $_) {
    if (! array_key_exists($key, $arabic)) {
        throw new RuntimeException("Arabic translation is missing German source key: {$key}");
    }
    $ordered[$key] = $arabic[$key];
}

$encoded = json_encode($ordered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
$encoded = preg_replace_callback(
    '/^( +)/m',
    fn (array $match): string => str_repeat(' ', intdiv(strlen($match[1]), 2)),
    $encoded,
);
file_put_contents($targetPath, $encoded);

$sports = shell_exec('git -C '.escapeshellarg($root).' cat-file blob '.escapeshellarg($sportsObject));
if (! is_string($sports) || $sports === '' || str_contains($sports, '�')) {
    throw new RuntimeException("Intact sports localization object {$sportsObject} is unavailable.");
}
file_put_contents($root.'/resources/js/lang/sports.js', $sports);

echo 'Restored '.count($ordered)." Arabic messages and the sports catalog from intact local Git objects.\n";
