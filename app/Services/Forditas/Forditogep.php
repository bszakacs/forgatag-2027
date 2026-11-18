<?php

namespace App\Services\Forditas;

/**
 * A fordító „gép” — a futtató (GepiForditas) csak ezt ismeri. Élesben ClaudeForditogep (Claude API), a tesztekben
 * hamis gép (nem hív API-t, és megszámolja, hányszor hívták: „üres futásnál nincs API-hívás”).
 */
interface Forditogep
{
    /**
     * @param  array<string, string>  $tetelek  kulcs => magyar szöveg
     * @return array<string, string> kulcs => román szöveg (MINDEN kulcsra; ha egy hiányzik, kivételt dob — nem csúsztat)
     */
    public function fordit(array $tetelek, string $rendszerPrompt): array;

    /**
     * Tényadat-ellenőrzés: állít-e a román olyat, ami a magyarban nincs, vagy kiesett-e belőle tényadat.
     *
     * @param  array<string, array{hu: string, ro: string}>  $parok
     * @return array<string, list<string>> kulcs => talált gondok (üres lista = rendben)
     */
    public function ellenoriz(array $parok): array;

    /** @return array{hivas: int, be: int, ki: int, gyorsitotarbol: int} az eddigi hívások összesítve */
    public function hasznalat(): array;
}
