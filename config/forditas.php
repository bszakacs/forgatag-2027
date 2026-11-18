<?php

/*
 * A GÉPI FORDÍTÁS (HU → RO, Claude API) beállításai — CLAUDE.md 5., PROMPT-19.
 *
 * Csak a BEKÜLDÖTT PROGRAMOK címe, leírása és jegyinformációja (App\Enums\TranslatableField). A saját tartalom
 * (hírek, lapok, kategória- és helyszínnevek, menü) KÉZI fordítás — a gépi futás nem nyúl hozzá.
 *
 * Ez technikai beállítás (.env). A szervezői adat — a védett nevek és a kötött román alakok — a Beállítások
 * lap „Gépi fordítás” blokkjában él (CLAUDE.md 2/b).
 */
return [

    // A kulcsot a tulajdonos írja be a szerveri .env-be. Üresen a gépi fordítás CSENDBEN kimarad (nem törik el semmi),
    // és ezt az admin nyitólapja kiírja.
    'kulcs' => env('ANTHROPIC_API_KEY'),

    // Fordításhoz a Sonnet elég (2026-os tulajdonosi döntés). Felülírható.
    'modell' => env('FORDITAS_MODELL', 'claude-sonnet-5'),

    // A tényadat-ellenőrzés (második hívás: állít-e a román olyat, ami a magyarban nincs — vagy kiesett-e valami)
    // MÁS modellel fut, mint a fordítás (2026: a saját munkáját ellenőrző modell vakfoltjai korrelálnak).
    'ellenorzes' => (bool) env('FORDITAS_ELLENORZES', true),
    'ellenorzo_modell' => env('FORDITAS_ELLENORZO_MODELL', 'claude-opus-5'),

    'effort' => env('FORDITAS_EFFORT', 'medium'),
    'max_tokens' => (int) env('FORDITAS_MAX_TOKENS', 16000),

    // Egy hívásban legfeljebb ennyi mező / ennyi karakter megy fel.
    'csomag_tetel' => (int) env('FORDITAS_CSOMAG_TETEL', 20),
    'csomag_karakter' => (int) env('FORDITAS_CSOMAG_KARAKTER', 8000),

    // Becsült ár (USD / millió token) a futási jelentéshez — csak tájékoztató.
    'ar_be' => (float) env('FORDITAS_AR_BE', 2),
    'ar_ki' => (float) env('FORDITAS_AR_KI', 10),
];
