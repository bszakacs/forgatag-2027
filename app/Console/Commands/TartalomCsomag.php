<?php

namespace App\Console\Commands;

use App\Support\Mentes2026;
use App\Support\TartalomImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * A 2026-os TARTALOM csomagolása — a fejlesztőgépen fut, a mentésből (MENTES-LELTAR.md), NEM a szerveren.
 * Kimenet: database/import/tartalom-2026.json (a tárolóba kerül; személyes adat NINCS benne: menü, lapok
 * nyilvános szövegei, videók, a tesztelésre kiválasztott hírek). A szerveren a forgatag:tartalom-import tölti be.
 *
 * Mi jön (PROMPT-08, tulajdonosi döntések):
 *  · a menü EGY AZ EGYBEN: 2 menü, 21 menüpont (a fa, a nyelvenkénti láthatóság is);
 *  · a lapok egy az egyben: 13 lap, a szerkeszthető szövegeikkel és képeikkel (`page.home_sections`);
 *  · a videóarchívum: 3 videócsoport, 26 videó → a „videoarchivum” lap videós blokkjai;
 *  · a hírekből CSAK néhány, TESZTNEK (self::TESZT_HIREK) — `is_test` jelöléssel, egy lépésben törölhetők.
 * Ami szándékosan NEM jön, azt a csomag számszerűen, okkal együtt rögzíti (`kihagyva`) — néma kimaradás nincs.
 *
 * Futtatás:
 *   docker compose run --rm -v "E:/Work/mysites/forgatag.ro/MENTES-2026:/mentes" app \
 *     php artisan forgatag:tartalom-csomag /mentes/2026-10-21
 */
#[Signature('forgatag:tartalom-csomag {mentes : a mentés mappája (benne adatbazis/)}')]
#[Description('A 2026-os menu, lapok, videok es teszthirek csomagolasa a mentesbol (fejlesztogepen fut)')]
class TartalomCsomag extends Command
{
    /**
     * A tesztelésre áthozott hírek (2026-os azonosító). Négy, különböző hosszúságú és szerkezetű, mind borítóval:
     * rövid felvezető, fellépő-bemutató, partnerprogram-lista, hosszú hagyomány-cikk.
     */
    private const TESZT_HIREK = [
        '927e50af-227d-45ef-98b8-70b16bf7e011', // Ott találkozunk!
        '1e9f67f7-43cd-42ff-b45c-01240297091e', // Parno Graszt
        '461affd8-a3bf-4dc4-a30b-e79f0651ea5d', // A Tékaforgatag programjai
        'a5b2d50f-085c-4284-833d-2c297f1e70a5', // Hol terem a hagyomány?
    ];

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public function handle(): int
    {
        try {
            $t = Mentes2026::tablak((string) $this->argument('mentes'),
                ['directus_files', 'menu', 'menu_item', 'page', 'page_block', 'news_item', 'video', 'video_group']);
            $csomag = $this->csomag($t);
        } catch (RuntimeException $e) {
            $this->error('CSOMAG: ' . $e->getMessage());

            return self::FAILURE;
        }

        file_put_contents(base_path(TartalomImport::LEIRAS), json_encode($csomag, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        foreach ($csomag['szamok'] as $mi => $db) {
            $this->line("  {$mi}: {$db}");
        }
        foreach ($csomag['kihagyva'] as $mi => $k) {
            $this->warn("  KIHAGYVA — {$mi}: {$k['db']} ({$k['ok']})");
        }
        $this->info('CSOMAG: kész → ' . TartalomImport::LEIRAS);

        return self::SUCCESS;
    }

    /** @param array<string, list<array<string, ?string>>> $t */
    private function csomag(array $t): array
    {
        $fajlok = collect($t['directus_files'])->keyBy('id');
        $kep = function (?string $id) use ($fajlok): ?string {
            if ($id === null) {
                return null;
            }

            return $fajlok->has($id) ? $id : throw new RuntimeException("a(z) {$id} kép nincs a mentés fájltárában");
        };

        $menuk = collect($t['menu'])->map(fn ($m) => [
            'id' => $m['id'], 'slug' => $m['slug'], 'title_hu' => $m['title_hu'], 'title_ro' => $m['title_ro'], 'sort' => (int) $m['sort'],
        ])->values()->all();

        $menupontok = collect($t['menu_item'])->map(function ($p) {
            $lathato = $p['visible'] === 't';
            $nyelv = $p['locale_visibility'] ?? 'both';
            $tipus = match (true) {
                $p['type'] === 'page' => 'page',
                blank($p['url']) => 'group', // 2026: „link” cím nélkül = almenü-fejléc (Programok, Médiatár)
                default => 'link',
            };

            return [
                'id' => $p['id'], 'menu' => $p['menu'], 'parent' => $p['parent'], 'sort' => (int) $p['sort'],
                'label_hu' => $p['label_hu'], 'label_ro' => $p['label_ro'], 'type' => $tipus,
                'page' => $tipus === 'page' ? $p['page'] : null, 'url' => $tipus === 'link' ? $p['url'] : null,
                'new_tab' => $p['open_new_tab'] === 't',
                'show_hu' => $lathato && in_array($nyelv, ['both', 'hu'], true),
                'show_ro' => $lathato && in_array($nyelv, ['both', 'ro'], true),
            ];
        })->sortBy(fn ($p) => [$p['parent'] !== null, $p['sort']])->values()->all();

        $lapIdk = collect($t['page'])->pluck('id')->flip();
        $videok = collect($t['video'])->groupBy('video_group');
        $csoportok = collect($t['video_group']);

        $lapok = collect($t['page'])->map(function ($l) use ($kep, $fajlok, $videok, $csoportok) {
            $szovegek = [];
            foreach (json_decode((string) $l['home_sections'], true) ?: [] as $kulcs => $ertek) {
                $hu = $ertek['hu'] ?? null;
                $ro = $ertek['ro'] ?? null;
                $kepId = collect([$hu, $ro])->first(fn ($v) => is_string($v) && preg_match(self::UUID, $v) && $fajlok->has($v));
                if ($kepId !== null && $hu !== null && $ro !== null && $hu !== $ro) {
                    throw new RuntimeException("a(z) {$l['slug']} lap {$kulcs} kulcsán a két nyelv más képet mutat — kézzel kell dönteni");
                }
                $szovegek[] = $kepId !== null
                    ? ['key' => $kulcs, 'hu' => null, 'ro' => null, 'kep' => $kepId]
                    : ['key' => $kulcs, 'hu' => $hu, 'ro' => $ro, 'kep' => null];
            }

            $blokkok = [];
            if ($l['slug'] === 'videoarchivum') {
                foreach ($csoportok as $i => $cs) {
                    $nyelv = $cs['locale_visibility'] ?? 'both';
                    $lathato = $cs['visible'] === 't';
                    $blokkok[] = [
                        'id' => $cs['id'], 'type' => 'videos', 'sort' => $i + 1,
                        'title_hu' => $cs['title_hu'], 'title_ro' => $cs['title_ro'],
                        'show_hu' => $lathato && in_array($nyelv, ['both', 'hu'], true),
                        'show_ro' => $lathato && in_array($nyelv, ['both', 'ro'], true),
                        'videos' => ($videok[$cs['id']] ?? collect())->where('visible', 't')->sortBy(fn ($v) => (int) $v['sort'])
                            ->map(fn ($v) => ['title_hu' => trim($v['title_hu']), 'title_ro' => $v['title_ro'] !== null ? trim($v['title_ro']) : null, 'youtube_id' => $v['youtube_id']])
                            ->values()->all(),
                    ];
                }
            }

            return [
                'id' => $l['id'], 'slug' => $l['slug'], 'title_hu' => $l['title_hu'], 'title_ro' => $l['title_ro'],
                'lead_hu' => $l['lead_hu'], 'lead_ro' => $l['lead_ro'],
                'seo_description_hu' => $l['seo_description_hu'], 'seo_description_ro' => $l['seo_description_ro'],
                'hero' => $kep($l['hero_image']), 'status' => $l['status'] === 'published' ? 'published' : 'draft',
                'is_system' => $l['is_system'] === 't', 'sort' => (int) $l['sort'],
                'szovegek' => $szovegek, 'blokkok' => $blokkok,
            ];
        })->sortBy('sort')->values()->all();

        // A 2026-os lapblokkok: ha valamelyik LÉTEZŐ laphoz tartozna, azt nem hagyhatjuk ki némán.
        $kotott = collect($t['page_block'])->filter(fn ($b) => $b['page'] !== null && $lapIdk->has($b['page']));
        if ($kotott->isNotEmpty()) {
            throw new RuntimeException($kotott->count() . ' lapblokk létező laphoz tartozik — a csomagoló még nem viszi át őket');
        }

        $hirek = collect($t['news_item'])->whereIn('id', self::TESZT_HIREK)->map(fn ($h) => [
            'id' => $h['id'], 'slug' => $h['slug'], 'title_hu' => $h['title_hu'], 'title_ro' => $h['title_ro'],
            'excerpt_hu' => $h['excerpt_hu'], 'excerpt_ro' => $h['excerpt_ro'], 'body_hu' => $h['body_hu'], 'body_ro' => $h['body_ro'],
            'date' => $h['date'] !== null ? substr($h['date'], 0, 10) : null, 'cover' => $kep($h['cover']),
            'status' => $h['status'] === 'published' ? 'published' : 'draft',
        ])->values()->all();
        if (count($hirek) !== count(self::TESZT_HIREK)) {
            throw new RuntimeException('a kiválasztott teszthírek közül ' . (count(self::TESZT_HIREK) - count($hirek)) . ' nincs a mentésben');
        }

        $videoDb = collect($lapok)->flatMap(fn ($l) => $l['blokkok'])->sum(fn ($b) => count($b['videos']));

        return [
            'forras' => 'MENTES-2026/2026-10-21 (Directus PostgreSQL-mentés)',
            'szamok' => [
                'menü' => count($menuk),
                'menüpont' => count($menupontok),
                'lap' => count($lapok),
                'szerkeszthető szöveg/kép' => collect($lapok)->sum(fn ($l) => count($l['szovegek'])),
                'videócsoport → videós blokk' => collect($lapok)->sum(fn ($l) => count($l['blokkok'])),
                'videó' => $videoDb,
                'teszthír' => count($hirek),
            ],
            'kihagyva' => [
                'hír' => ['db' => count($t['news_item']) - count($hirek), 'ok' => 'tulajdonosi döntés: időhöz kötött 2026-os bejelentések, a 2026-os archívum őrzi őket'],
                'lapblokk' => ['db' => count($t['page_block']), 'ok' => 'egyik sem tartozik létező laphoz (a laphoz nem kötött blokkok próbatartalmak: „dsadsa”, „Teszte csere”)'],
                'rejtett videó' => ['db' => collect($t['video'])->where('visible', '!=', 't')->count(), 'ok' => '2026-ban sem látszott'],
            ],
            'menuk' => $menuk,
            'menupontok' => $menupontok,
            'lapok' => $lapok,
            'hirek' => $hirek,
        ];
    }
}
