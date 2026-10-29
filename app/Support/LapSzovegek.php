<?php

namespace App\Support;

use App\Models\Media;
use App\Models\Page;
use App\Models\PageText;
use RuntimeException;

/**
 * Egy lap szerkeszthető szövegei a publikus sablonnak, az aktuális nyelven (App\Support\LapSablon írja le).
 *
 * · Románul, ahol nincs fordítás, a MAGYAR szöveg látszik (CLAUDE.md 5.) — a cím-csoportok együtt esnek vissza.
 * · Nincs beépített tartalék: ha a sablon olyan kulcsot kér, ami nincs a leírásban vagy az adatbázisban,
 *   a lap HIBÁVAL áll meg (a telepítés `forgatag:lap-szovegek` lépése hozza létre a kulcsokat).
 */
class LapSzovegek
{
    /** @var array<string, PageText> */
    private array $sorok;

    /** @var array<string, array<string, mixed>> */
    private array $mezok;

    public function __construct(private Page $lap, private string $nyelv)
    {
        $this->mezok = LapSablon::mezok($lap->slug);
        $this->sorok = $lap->texts()->with('media')->get()->keyBy('key')->all();
    }

    public function szoveg(string $kulcs): string
    {
        $sor = $this->sor($kulcs, ['sor', 'szoveg']);
        if ($this->nyelv !== 'ro') {
            return (string) $sor->value_hu;
        }
        $csoport = $this->mezok[$kulcs]['csoport'];
        if ($csoport === null) {
            return filled($sor->value_ro) ? (string) $sor->value_ro : (string) $sor->value_hu;
        }
        // Cím-csoport: ha BÁRMELYIK része le van fordítva, a román változat érvényes (az üres rész üres marad).
        $forditott = collect($this->mezok)->where('csoport', $csoport)
            ->contains(fn ($m) => filled($this->sorok[$m['kulcs']]->value_ro ?? null));

        return (string) ($forditott ? $sor->value_ro : $sor->value_hu);
    }

    public function kep(string $kulcs): ?Media
    {
        return $this->sor($kulcs, ['kep'])->media;
    }

    public function link(string $kulcs): string
    {
        return (string) $this->sor($kulcs, ['link'])->value_hu;
    }

    /** @param list<string> $tipusok */
    private function sor(string $kulcs, array $tipusok): PageText
    {
        $m = $this->mezok[$kulcs] ?? throw new RuntimeException("A(z) „{$this->lap->slug}” sablon nem ismeri a(z) „{$kulcs}” kulcsot (App\\Support\\LapSablon).");
        if (! in_array($m['tipus'], $tipusok, true)) {
            throw new RuntimeException("A(z) „{$kulcs}” kulcs típusa {$m['tipus']}, a sablon " . implode('/', $tipusok) . '-ként kéri.');
        }

        return $this->sorok[$kulcs] ?? throw new RuntimeException("A(z) „{$this->lap->slug}” lap „{$kulcs}” szövege hiányzik az adatbázisból — futtasd: php artisan forgatag:lap-szovegek");
    }
}
