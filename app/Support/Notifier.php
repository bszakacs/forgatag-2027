<?php

namespace App\Support;

use App\Enums\NotificationTopic;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * A rendszer levelei — a címzettek és a feladó a Beállításokból jönnek (CLAUDE.md 2/b), nem .env-ből.
 * Egy levél hibája soha nem dönti el a felhasználó műveletét (a program már elmentve), de NEM is néma:
 * minden kimaradt vagy elhasalt levél hibaszinten a naplóba kerül.
 *
 * Ez az osztály SOHA nem dob kivételt: a mentés utáni levél hibája nem fordulhat át „sikertelen
 * beküldésbe" (a program már bent van — a beküldő hibát látna, és újra beküldené; mérve 2026-10-12).
 */
class Notifier
{
    /** Levél a témához beállított koordinátori címekre. */
    public static function topic(NotificationTopic $topic, Mailable $mail): bool
    {
        try {
            $cimek = self::cimek($topic);
        } catch (Throwable $e) {
            self::naplo('ÉRTESÍTÉS KIMARADT: a beállítások nem olvashatók. ' . $e->getMessage(), ['targy' => $mail->subject]);

            return false;
        }
        if ($cimek === []) {
            self::naplo("ÉRTESÍTÉS KIMARADT: a(z) „{$topic->label()}” témához nincs koordinátori cím a Beállítások / Értesítések blokkban.", [
                'targy' => $mail->subject,
            ]);

            return false;
        }

        return self::send($cimek, $mail);
    }

    /** @return list<string> a témához beállított koordinátori címek */
    public static function cimek(NotificationTopic $topic): array
    {
        try {
            return array_values(array_filter((array) Settings::get("notifications.topics.{$topic->value}", [])));
        } catch (Throwable) {
            return []; // hiányzó beállítás-blokk: a topic() ezt „kimaradt értesítésként” naplózza
        }
    }

    /** @param list<string>|string $to */
    public static function send(array|string $to, Mailable $mail): bool
    {
        // A feladó a szerver levelezési beállítása (.env: MAIL_FROM_ADDRESS = az SMTP-fiók) — nem a Beállítások.
        try {
            Mail::to($to)->send($mail);

            return true;
        } catch (Throwable $e) {
            self::naplo('LEVÉL NEM MENT KI: ' . $e->getMessage(), ['cimzett' => $to, 'targy' => $mail->subject]);

            return false;
        }
    }

    /** @param array<string, mixed> $ctx */
    private static function naplo(string $uzenet, array $ctx): void
    {
        try {
            Log::error($uzenet, $ctx);
        } catch (Throwable) {
            // Ha a napló sem írható, a PHP saját hibanaplója marad (a tárhelyen: error_log).
            error_log($uzenet);
        }
    }
}
