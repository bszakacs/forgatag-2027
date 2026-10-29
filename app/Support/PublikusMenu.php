<?php

namespace App\Support;

use App\Enums\ContentStatus;
use App\Enums\MenuItemType;
use App\Models\Menu;
use App\Models\MenuItem;

/**
 * A menü (adminban: Tartalom → Menü) a publikus fejléchez/lábléchez: a fa, nyelvenként szűrve.
 * · `show_hu`/`show_ro`: a rejtett pont (és az alpontjai) az adott nyelven nem jelenik meg;
 * · lapra mutató pont csak akkor, ha a lap közzétett (vázlatra nem viszünk látogatót);
 * · csak-fejléc (csoport) pont alpontok nélkül kimarad;
 * · belső cím a nyelv előtagját kapja (/ro/...), a felirat románul a magyarra esik vissza, ha üres.
 */
class PublikusMenu
{
    /** @return list<array{felirat: string, cim: ?string, uj_lap: bool, gyerekek: list<array{felirat: string, cim: ?string, uj_lap: bool}>}> */
    public static function fa(string $menu, ?string $nyelv = null): array
    {
        $nyelv ??= Nyelv::aktualis();
        $m = Menu::query()->where('slug', $menu)->first();
        if ($m === null) {
            return [];
        }
        $pontok = $m->items()->with('page')->get();
        $gyerekek = $pontok->whereNotNull('parent_id')->groupBy('parent_id');

        $fa = [];
        foreach ($pontok->whereNull('parent_id') as $p) {
            if (($csomo = self::csomo($p, $nyelv)) === null) {
                continue;
            }
            $csomo['gyerekek'] = collect($gyerekek->get($p->id, []))
                ->map(fn (MenuItem $g) => self::csomo($g, $nyelv))->filter()->values()->all();
            if ($p->type === MenuItemType::Group && $csomo['gyerekek'] === []) {
                continue;
            }
            $fa[] = $csomo;
        }

        return $fa;
    }

    /** @return array{felirat: string, cim: ?string, uj_lap: bool, gyerekek: array}|null */
    private static function csomo(MenuItem $p, string $nyelv): ?array
    {
        if (! $p->{"show_{$nyelv}"}) {
            return null;
        }
        if ($p->type === MenuItemType::Page && $p->page?->status !== ContentStatus::Published) {
            return null;
        }
        $cim = $p->target();

        return [
            'felirat' => Nyelv::valaszt($p->label_hu, $p->label_ro, $nyelv),
            'cim' => $cim === null ? null : Nyelv::ut($cim, $nyelv),
            'uj_lap' => (bool) $p->new_tab,
            'gyerekek' => [],
        ];
    }
}
