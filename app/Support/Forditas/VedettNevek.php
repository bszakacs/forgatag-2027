<?php

namespace App\Support\Forditas;

use App\Support\Settings;

/**
 * A gépi fordítás nyelvi szabályai — EGY helyen: a promptba kerülő utasítás ÉS a futás utáni ellenőrzés is innen.
 *
 * A listák (védett nevek, kötött román alakok) szervezői adat: a Beállítások „Gépi fordítás” blokkjában élnek
 * (settings.translation), a kezdőértékük a seed/do-not-translate.json (CLAUDE.md 2/b). A ragozás szabálya
 * (Vâltoarea Mureșeană) nyelvtan, nem adat — az itt, a kódban marad (CLAUDE.md 1.).
 *
 * A 2026-os tooling/translate.mjs szabályai, betűre (dntTo, dntSerules, kotottForrasban, kotottRoAlakok, kotottSerules).
 */
final class VedettNevek
{
    /** @param list<string> $nevek @param list<array{hu: string, ro: string}> $kotott */
    public function __construct(public readonly array $nevek, public readonly array $kotott) {}

    public static function beallitasbol(): self
    {
        $b = Settings::has('translation') ? Settings::block('translation') : ['terms' => [], 'bound' => []];
        $nevek = array_values(array_unique(array_filter(array_map(fn ($t) => trim((string) $t), (array) ($b['terms'] ?? [])))));
        // HOSSZ szerint csökkenő: az összetettebb név („Vár, Borkert”) álljon a rövidebb („Borkert”) előtt.
        usort($nevek, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $kotott = array_values(array_filter(array_map(
            fn ($k) => ['hu' => trim((string) ($k['hu'] ?? '')), 'ro' => trim((string) ($k['ro'] ?? ''))],
            (array) ($b['bound'] ?? []),
        ), fn ($k) => $k['hu'] !== '' && $k['ro'] !== ''));
        usort($kotott, fn ($a, $b) => mb_strlen($b['hu']) <=> mb_strlen($a['hu']));

        return new self($nevek, $kotott);
    }

    /** A fordító rendszerpromptja (a 2026-os SYSTEM_PROMPT). Minden csomagnál ugyanaz → gyorsítótárazható. */
    public function forditoPrompt(): string
    {
        $p = <<<'TXT'
        Magyarról románra fordítasz a Vásárhelyi Forgatag számára. Ez egy magyar közösségi fesztivál Marosvásárhelyen (románul: Târgu Mureș), Erdélyben. A szövegek fesztiválprogramok címei, leírásai és jegyinformációi, amelyeket külső programgazdák küldtek be.

        A fordítás olvasói a helyi, román ajkú közönség. A román szöveg legyen természetes, élő nyelv, a helyi nyelvhasználathoz illő — ne szó szerinti tükörfordítás. A hangnem és a hosszúság kövesse a forrást: ami tömör hirdetés, az románul is az legyen.

        SOHA ne találj ki és ne egészíts ki tényadatot: dátumot, időpontot, helyszínt, árat, fellépőt, elérhetőséget, korhatárt. Ami nincs benne a magyar forrásban, az a románban sem lehet benne. Ha a forrás hiányos vagy homályos, fordítsd úgy, ahogy van — ne told ki és ne értelmezd.

        A RÖVIDÍTÉSEKET hagyd változatlanul: NE bontsd ki, és ne told hozzá, mi lehet a jelentésük. Ha a forrás azt írja, hogy „ELTE-HTK", akkor a románban is „ELTE-HTK" álljon. Ez akkor is így van, ha te tudod (vagy sejted) a feloldást: ha a jelentés nincs benne a magyar forrásban, a fordításban sem lehet benne.

        Őrizd meg a forrás sortöréseit, felsorolásait és jelöléseit (Markdown), ahol van. Ne tegyél hozzá bevezetőt, magyarázatot, megjegyzést vagy kérdést — csak a fordítást add vissza.

        A román nyelvben nincs „ő" és „ű" betű: a román szövegben ilyen betű csak a lent felsorolt, változatlanul hagyandó tulajdonnevekben és magyar személynevekben maradhat.
        TXT;

        if ($this->nevek !== []) {
            $p .= "\n\nVÁLTOZATLANUL HAGYANDÓ TULAJDONNEVEK\n"
                . 'Az alábbi kifejezéseket betűre pontosan, fordítás és román ragozás nélkül másold át a román szövegbe. A körülöttük lévő mondatot természetesen fordítsd le, de maga a név maradjon szó szerint ugyanaz (a magyar ékezeteivel együtt):'
                . "\n" . implode("\n", array_map(fn ($t) => "- {$t}", $this->nevek))
                . "\n\nEzen a listán túl is hagyd változatlanul a személyneveket, együttes- és márkaneveket, valamint azokat az intézményneveket, amelyeknek nincs bevett román alakjuk.";
        }

        if ($this->kotott !== []) {
            $p .= "\n\nKÖTÖTT FORDÍTÁSOK\n"
                . 'Az alábbi kifejezéseknek van a szervezők által jóváhagyott román alakja. PONTOSAN ezt a NEVET használd — se szó szerinti fordítást, se saját változatot:'
                . "\n" . implode("\n", array_map(fn ($k) => "- „{$k['hu']}\" → „{$k['ro']}\"", $this->kotott))
                . "\n\n" . <<<'TXT'
                A NÉV RAGOZÁSA — HÁROM ALAK VAN, ÉS CSAK EZ A HÁROM:
                  (1) ALANY, névelővel .......... Vâltoarea Mureșeană   („Vâltoarea Mureșeană este…")
                  (2) ELÖLJÁRÓSZÓ UTÁN .......... Vâltoare              („la Vâltoare", „în Vâltoare")
                  (3) BIRTOKOS / RÉSZES ESET .... Vâltorii Mureșene     („pe teritoriul Vâltorii Mureșene", „programele Vâltorii Mureșene")
                A név TÖVE nem cserélhető, de a névelőt és az esetet NEKED kell a mondathoz igazítani.
                HIBÁS, soha ne írd: „la Vâltoarea", „teritoriul Vâltoarei", „programelor Vâltoarea Mureșeană".
                A „Vâltoarei" alak MINDIG hibás — a birtokos eset „Vâltorii".
                Ugyanez a „Cortul Nasul Roșu"-ra: „la Cortul Nasul Roșu", „programele cortului Nasul Roșu".
                TXT;
            $csapda = $this->kotottReszeiNevben();
            if ($csapda !== []) {
                $p .= "\n\nFIGYELEM, EZ A LEGGYAKORIBB HIBA: a kötött fordítás CSAK az ÖNÁLLÓAN álló kifejezésre vonatkozik. Ha ugyanaz a szó egy HOSSZABB tulajdonnév része, akkor az a fenti „változatlanul hagyandó\" listára tartozik, és betűre pontosan át kell másolni. Példa: „Forgatag\" önmagában → „Vâltoarea\", DE „Ebforgatag\" marad „Ebforgatag\" (nem „Ebvâltoarea\"). Ugyanígy változatlan marad: "
                    . implode(', ', array_map(fn ($t) => "„{$t}\"", $csapda)) . '.';
            }
        }

        return $p;
    }

    /** A védett nevek, amelyek egy kötött kifejezést TARTALMAZNAK (pl. Ebforgatag ⊃ forgatag) — a csapda-lista. */
    private function kotottReszeiNevben(): array
    {
        return array_values(array_filter($this->nevek, function ($t) {
            foreach ($this->kotott as $k) {
                if (mb_stripos($t, $k['hu']) !== false && mb_strtolower($t) !== mb_strtolower($k['hu'])) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * A futás utáni ellenőrzés. Üres lista = rendben; különben emberi nyelvű megjegyzések (a sor check_note-jába).
     *
     * @return list<string>
     */
    public function ellenoriz(string $hu, string $ro): array
    {
        $jegyzet = [];
        foreach ($this->dntSerules($hu, $ro) as $t) {
            $jegyzet[] = "TULAJDONNÉV: „{$t}” kiesett a fordításból.";
        }
        foreach ($this->kotottSerules($hu, $ro) as $k) {
            $jegyzet[] = "KÖTÖTT ALAK: „{$k['hu']}” → „{$k['ro']}” nincs a fordításban.";
        }
        if (preg_match('/V[âa]ltoarei\b/iu', $ro)) {
            $jegyzet[] = 'HIBÁS ALAK: „Vâltoarei” — a birtokos eset „Vâltorii” (CLAUDE.md 1.).';
        }
        $magyar = $this->magyarSzavak($ro);
        if ($magyar !== []) {
            $jegyzet[] = 'MAGYAR BETŰ (ő/ű) a román szövegben: „' . implode('”, „', $magyar)
                . '” — a román nyelvben nincs ő és ű: bent maradt magyar szó? (Magyar személynévnél rendben lehet.)';
        }

        return $jegyzet;
    }

    /**
     * A gyors hibakereső (CLAUDE.md 1.): a román nyelvben nincs ő és ű. A védett nevek (Csicsergő, Szűcsök Bástyája…)
     * szándékosan magyarul maradnak — azokat előbb kivesszük, a maradékban minden ő/ű-s szó gyanús.
     *
     * @return list<string>
     */
    public function magyarSzavak(string $ro): array
    {
        $maradek = $ro;
        foreach ($this->nevek as $t) {
            $maradek = str_replace($t, ' ', $maradek);
        }
        preg_match_all('/[\p{L}\-]*[őűŐŰ][\p{L}\-]*/u', $maradek, $m);

        return array_values(array_unique($m[0]));
    }

    /**
     * A magyar toldalék miatt a védett név nem mindig szerepel SZÓ SZERINT a forrásban („Mátka” → „Mátkával”), ezért a
     * záró magánhangzó nélküli TŐVEL is keresünk (2026: dntTo).
     */
    private static function to(string $t): string
    {
        $to = preg_match('/[aáeéiíoóöőuúüű]$/u', $t) ? mb_substr($t, 0, -1) : '';

        return mb_strlen($to) >= 3 ? $to : '';
    }

    /** @return list<string> a forrásban szereplő, de a fordításból kiesett védett nevek */
    public function dntSerules(string $hu, string $ro): array
    {
        return array_values(array_filter($this->nevek, function ($t) use ($hu, $ro) {
            $to = self::to($t);
            $forrasban = str_contains($hu, $t) || ($to !== '' && str_contains($hu, $to));

            return $forrasban && ! (str_contains($ro, $t) || ($to !== '' && str_contains($ro, $to)));
        }));
    }

    /**
     * A kötött kifejezés ÖNÁLLÓ szóként a magyar forrásban: szóhatár csak ELŐTTE (a magyar toldalékol: „a Forgatagra”).
     * Ez választja el a fesztivál nevét az önálló programnevektől („Ebforgatag” — előtte betű áll, nem találat).
     */
    private static function kotottForrasban(string $hu, string $kif): bool
    {
        return (bool) preg_match('/(?<![\p{L}\p{N}])' . preg_quote($kif, '/') . '/iu', $hu);
    }

    /** @return list<string> a kötött román alak elfogadható változatai (a román nyelvtan megkövetelte végződéssel) */
    private static function kotottRoAlakok(string $ro): array
    {
        $alakok = [$ro];
        $csonk = fn (string $s) => preg_match('/[aăâeéiîoôuü]$/iu', $s) ? mb_substr($s, 0, -1) : null;
        if (($c = $csonk($ro)) !== null) {
            $alakok[] = $c;
        }
        $szavak = preg_split('/\s+/u', $ro);
        if (count($szavak) > 1) {
            $alakok[] = implode(' ', array_slice($szavak, 1)); // „Cortului Nasul Roșu” → „Nasul Roșu”
            if (($c0 = $csonk($szavak[0])) !== null) {
                $alakok[] = $c0;                                // „Vâltoarea Mureșeană” → „Vâltoare”
            }
        }
        // A fesztivál nevének birtokos esete más tövű (CLAUDE.md 1.): „Vâltorii Mureșene” — ez is a kötött alak.
        if (str_contains($ro, 'Vâltoarea')) {
            $alakok[] = 'Vâltorii';
        }

        return array_values(array_unique(array_filter($alakok, fn ($a) => mb_strlen($a) >= 4)));
    }

    /** @return list<array{hu: string, ro: string}> */
    public function kotottSerules(string $hu, string $ro): array
    {
        return array_values(array_filter($this->kotott, function ($k) use ($hu, $ro) {
            if (! self::kotottForrasban($hu, $k['hu'])) {
                return false;
            }
            foreach (self::kotottRoAlakok($k['ro']) as $a) {
                if (str_contains($ro, $a)) {
                    return false;
                }
            }

            return true;
        }));
    }
}
