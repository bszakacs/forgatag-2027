<?php

namespace App\Support;

use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A 2026-os tartalom behozatala a csomagból (database/import/tartalom-2026.json — forgatag:tartalom-csomag készíti).
 * A telepítés futtatja (forgatag:tartalom-import), a médiatár-behozatal UTÁN — a képek a médiatár `legacy_id`-jén
 * át kötődnek, NEM töltődnek fel újra.
 *
 * Három lépés, mindegyik egy tranzakció:
 *  · aloldalak — csak ha még nincs egyetlen lap sem;
 *  · menük — csak ha még nincs egyetlen menü sem (a menüpontok a lapokra mutathatnak, ezért utánuk);
 *  · TESZThírek (`is_test`) — csak EGYSZER: a jelölés fájlban van (storage/app/import-2026/tartalom.json), NEM az
 *    adatbázisban, hogy az élesítés előtti adatbázis-ürítés (+ telepítés) után ne jöjjenek vissza.
 * Így az adminban törölt vagy átírt sor soha nem jön vissza (mint a forgatag:torzsadatok-nál).
 *
 * Ha egy hivatkozott kép hiányzik a médiatárból, a lépés NEM ír semmit, és hibával áll meg (néma hiba tilos).
 */
class TartalomImport
{
    public const LEIRAS = 'database/import/tartalom-2026.json';

    public const JELOLES = 'import-2026/tartalom.json';

    /** @var array<string, mixed> */
    private array $csomag;

    /** @var array<string, int> legacy_id → médiatár-id */
    private array $kepek = [];

    public function __construct(?string $leiras = null)
    {
        $ut = $leiras ?? base_path(self::LEIRAS);
        $this->csomag = json_decode((string) @file_get_contents($ut), true)
            ?? throw new RuntimeException("a csomag nem olvasható: {$ut}");
    }

    /**
     * @param  callable(string): void  $naplo
     * @return array<string, int> mit hozott be (0 = kihagyta, mert már bent volt)
     */
    public function run(callable $naplo): array
    {
        $this->kepek = $this->kepTerkep();
        $eredmeny = ['lap' => 0, 'menüpont' => 0, 'teszthír' => 0];

        if (Page::query()->exists()) {
            $naplo('aloldalak — már van ' . Page::count() . ' lap, nem nyúltam hozzájuk.');
        } else {
            $eredmeny['lap'] = DB::transaction(fn () => $this->lapok());
            $naplo("aloldalak — {$eredmeny['lap']} lap behozva.");
        }

        if (Menu::query()->exists()) {
            $naplo('menük — már van ' . Menu::count() . ' menü, nem nyúltam hozzájuk.');
        } else {
            $eredmeny['menüpont'] = DB::transaction(fn () => $this->menuk());
            $naplo('menük — ' . count($this->csomag['menuk']) . " menü, {$eredmeny['menüpont']} menüpont behozva.");
        }

        if (Storage::disk('local')->exists(self::JELOLES)) {
            $naplo('teszthírek — egyszer már behozva (' . self::JELOLES . '), nem hozom újra.');
        } else {
            $eredmeny['teszthír'] = DB::transaction(fn () => $this->hirek());
            Storage::disk('local')->put(self::JELOLES, json_encode(['teszthirek' => $eredmeny['teszthír'], 'ekkor' => now()->toIso8601String()]));
            $naplo("teszthírek — {$eredmeny['teszthír']} hír behozva, TESZT jelöléssel (Beállítások → Tesztadatok).");
        }

        foreach ($this->csomag['kihagyva'] as $mi => $k) {
            $naplo("szándékosan kihagyva — {$mi}: {$k['db']} ({$k['ok']}).");
        }

        return $eredmeny;
    }

    /** @return array<string, int> */
    private function kepTerkep(): array
    {
        $kell = collect($this->csomag['lapok'])
            ->flatMap(fn ($l) => [$l['hero'], ...array_column($l['szovegek'], 'kep')])
            ->merge(array_column($this->csomag['hirek'], 'cover'))
            ->filter()->unique()->values();
        $van = Media::whereIn('legacy_id', $kell)->pluck('id', 'legacy_id')->all();
        $hiany = $kell->reject(fn ($id) => isset($van[$id]));
        if ($hiany->isNotEmpty()) {
            throw new RuntimeException($hiany->count() . ' hivatkozott 2026-os kép nincs a médiatárban (előbb a médiatár-behozatalnak kell lefutnia): '
                . $hiany->take(5)->implode(', '));
        }

        return $van;
    }

    private function kep(?string $legacy): ?int
    {
        return $legacy === null ? null : $this->kepek[$legacy];
    }

    private function lapok(): int
    {
        foreach ($this->csomag['lapok'] as $l) {
            $lap = new Page([
                'slug' => $l['slug'], 'title_hu' => $l['title_hu'], 'title_ro' => $l['title_ro'],
                'lead_hu' => $l['lead_hu'], 'lead_ro' => $l['lead_ro'],
                'seo_description_hu' => $l['seo_description_hu'], 'seo_description_ro' => $l['seo_description_ro'],
                'hero_media_id' => $this->kep($l['hero']), 'status' => $l['status'], 'sort' => $l['sort'],
            ]);
            $lap->is_system = $l['is_system'];
            $lap->legacy_id = $l['id'];
            $lap->save();

            foreach ($l['szovegek'] as $i => $sz) {
                PageText::create(['page_id' => $lap->id, 'key' => $sz['key'], 'value_hu' => $sz['hu'], 'value_ro' => $sz['ro'],
                    'media_id' => $this->kep($sz['kep']), 'sort' => $i + 1]);
            }
            foreach ($l['blokkok'] as $b) {
                $blokk = new PageBlock(['page_id' => $lap->id, 'sort' => $b['sort'], 'type' => $b['type'],
                    'title_hu' => $b['title_hu'], 'title_ro' => $b['title_ro'], 'videos' => $b['videos'],
                    'show_hu' => $b['show_hu'], 'show_ro' => $b['show_ro']]);
                $blokk->legacy_id = $b['id'];
                $blokk->save();
            }
        }

        return count($this->csomag['lapok']);
    }

    private function menuk(): int
    {
        $menu = [];
        foreach ($this->csomag['menuk'] as $m) {
            $uj = new Menu(['slug' => $m['slug'], 'title_hu' => $m['title_hu'], 'title_ro' => $m['title_ro'], 'sort' => $m['sort']]);
            $uj->legacy_id = $m['id'];
            $uj->save();
            $menu[$m['id']] = $uj->id;
        }
        $lap = Page::whereNotNull('legacy_id')->pluck('id', 'legacy_id')->all();

        // A csomag a szülőket a gyerekek ELÉ rendezi (TartalomCsomag) — így a szülő mindig létezik már.
        $pont = [];
        foreach ($this->csomag['menupontok'] as $p) {
            if ($p['page'] !== null && ! isset($lap[$p['page']])) {
                throw new RuntimeException("a(z) „{$p['label_hu']}” menüpont lapja nincs meg (a lapokat a menük előtt kell behozni)");
            }
            $uj = new MenuItem([
                'menu_id' => $menu[$p['menu']], 'parent_id' => $p['parent'] !== null ? $pont[$p['parent']] : null,
                'sort' => $p['sort'], 'label_hu' => $p['label_hu'], 'label_ro' => $p['label_ro'], 'type' => $p['type'],
                'page_id' => $p['page'] !== null ? $lap[$p['page']] : null, 'url' => $p['url'], 'new_tab' => $p['new_tab'],
                'show_hu' => $p['show_hu'], 'show_ro' => $p['show_ro'],
            ]);
            $uj->legacy_id = $p['id'];
            $uj->save();
            $pont[$p['id']] = $uj->id;
        }

        return count($pont);
    }

    private function hirek(): int
    {
        $db = 0;
        foreach ($this->csomag['hirek'] as $h) {
            if (NewsItem::where('legacy_id', $h['id'])->exists()) {
                continue;
            }
            $hir = new NewsItem([
                'slug' => $h['slug'], 'title_hu' => $h['title_hu'], 'title_ro' => $h['title_ro'],
                'excerpt_hu' => $h['excerpt_hu'], 'excerpt_ro' => $h['excerpt_ro'], 'body_hu' => $h['body_hu'], 'body_ro' => $h['body_ro'],
                'cover_media_id' => $this->kep($h['cover']), 'status' => $h['status'], 'published_on' => $h['date'],
            ]);
            $hir->is_test = true;
            $hir->legacy_id = $h['id'];
            $hir->save();
            $db++;
        }

        return $db;
    }
}
