<?php

namespace App\Support;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A menü fájának EGYETLEN író-útja (saját kód — fa-bővítményre szándékosan nem építünk, PROMPT-08).
 *
 * A szerkesztő (App\Filament\Pages\Menuk) a teljes fát küldi vissza egy fogd-és-vidd után; a nyíl-gombok
 * (fel/le/beljebb/kijjebb — mobilon ez a biztos út) ugyanígy egy új fát állítanak elő. Ez az osztály
 * ellenőrzi és egy tranzakcióban írja ki: minden menüpont pontosan egyszer szerepel, csak ebből a menüből,
 * és a mélység legfeljebb MAX_DEPTH. Hibás fát NEM ír ki félig — kivétellel áll meg, a felület kiírja.
 */
class MenuTree
{
    /** Főmenüpont + egy szint almenü (a 2026-os fejléc ennyit tud megjeleníteni). */
    public const MAX_DEPTH = 2;

    /**
     * A menü fája beágyazott tömbként: [['id' => 1, 'children' => [...]], ...].
     *
     * @return list<array{id: int, children: list<mixed>}>
     */
    public static function tree(Menu $menu): array
    {
        $elemek = MenuItem::where('menu_id', $menu->id)->orderBy('sort')->orderBy('id')->get(['id', 'parent_id']);
        $gyerekek = $elemek->groupBy(fn ($e) => $e->parent_id ?? 0);
        $epit = function (int $szulo) use (&$epit, $gyerekek): array {
            return ($gyerekek[$szulo] ?? collect())->map(fn ($e) => ['id' => $e->id, 'children' => $epit($e->id)])->values()->all();
        };

        return $epit(0);
    }

    /**
     * Kiírja a fát (sorrend + szülő).
     *
     * @param  list<array{id: int|string, children?: list<mixed>}>  $fa
     *
     * @throws InvalidArgumentException emberi nyelvű okkal
     */
    public static function save(Menu $menu, array $fa): void
    {
        $sorok = [];
        $bejar = function (array $agak, ?int $szulo, int $melyseg) use (&$bejar, &$sorok): void {
            foreach (array_values($agak) as $i => $ag) {
                if (! is_array($ag) || ! isset($ag['id'])) {
                    throw new InvalidArgumentException('Hibás menüfa érkezett.');
                }
                if ($melyseg > self::MAX_DEPTH) {
                    throw new InvalidArgumentException('A menü legfeljebb ' . self::MAX_DEPTH . ' szintű lehet (főmenüpont és almenü). Almenüpont alá nem tehető újabb menüpont.');
                }
                $id = (int) $ag['id'];
                if (isset($sorok[$id])) {
                    throw new InvalidArgumentException('Egy menüpont kétszer szerepelt a fában.');
                }
                $sorok[$id] = ['parent_id' => $szulo, 'sort' => $i + 1];
                $bejar($ag['children'] ?? [], $id, $melyseg + 1);
            }
        };
        $bejar($fa, null, 1);

        $letezo = MenuItem::where('menu_id', $menu->id)->pluck('id')->all();
        sort($letezo);
        $kapott = array_keys($sorok);
        sort($kapott);
        if ($letezo !== $kapott) {
            throw new InvalidArgumentException('A menü közben megváltozott (más is szerkeszti?). Töltsd újra a lapot.');
        }

        DB::transaction(function () use ($sorok): void {
            foreach ($sorok as $id => $ertek) {
                MenuItem::whereKey($id)->update($ertek);
            }
        });
    }

    /**
     * Egy menüpont mozgatása gombbal: fel, le, beljebb (az előző testvér alá), kijjebb (a szülője után).
     *
     * @throws InvalidArgumentException ha a lépés nem lehetséges
     */
    public static function move(Menu $menu, int $id, string $irany): void
    {
        $fa = self::tree($menu);
        [$lista, $i] = self::find($fa, $id) ?? throw new InvalidArgumentException('Nincs ilyen menüpont.');

        // Referenciával dolgozunk a fán: a megtalált testvérlista helyben módosul.
        $testverek = &self::listRef($fa, $lista);
        $elem = $testverek[$i];

        switch ($irany) {
            case 'fel':
                if ($i === 0) {
                    throw new InvalidArgumentException('Ez már az első a saját szintjén.');
                }
                [$testverek[$i - 1], $testverek[$i]] = [$testverek[$i], $testverek[$i - 1]];
                break;
            case 'le':
                if ($i === count($testverek) - 1) {
                    throw new InvalidArgumentException('Ez már az utolsó a saját szintjén.');
                }
                [$testverek[$i + 1], $testverek[$i]] = [$testverek[$i], $testverek[$i + 1]];
                break;
            case 'beljebb':
                if ($i === 0) {
                    throw new InvalidArgumentException('Beljebb csak akkor tehető, ha van fölötte menüpont ugyanazon a szinten (az alá kerül).');
                }
                array_splice($testverek, $i, 1);
                $testverek[$i - 1]['children'][] = $elem;
                break;
            case 'kijjebb':
                if ($lista === []) {
                    throw new InvalidArgumentException('Ez már főmenüpont.');
                }
                array_splice($testverek, $i, 1);
                unset($testverek);
                $szuloLista = array_slice($lista, 0, -1);
                $szuloIndex = end($lista);
                $nagyszulo = &self::listRef($fa, $szuloLista);
                array_splice($nagyszulo, $szuloIndex + 1, 0, [$elem]);
                break;
            default:
                throw new InvalidArgumentException('Ismeretlen irány.');
        }
        unset($testverek, $nagyszulo);

        self::save($menu, $fa);
    }

    /**
     * Hol van a menüpont: [az őt tartalmazó lista útja (szülő-indexek), index a listán belül].
     *
     * @return array{0: list<int>, 1: int}|null
     */
    private static function find(array $fa, int $id, array $ut = []): ?array
    {
        foreach ($fa as $i => $ag) {
            if ($ag['id'] === $id) {
                return [$ut, $i];
            }
            if ($talalat = self::find($ag['children'], $id, [...$ut, $i])) {
                return $talalat;
            }
        }

        return null;
    }

    private static function &listRef(array &$fa, array $ut): array
    {
        $lista = &$fa;
        foreach ($ut as $i) {
            $lista = &$lista[$i]['children'];
        }

        return $lista;
    }
}
