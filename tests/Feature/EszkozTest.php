<?php

namespace Tests\Feature;

use App\Support\Eszkoz;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * A saját CSS/JS-fájlok gyorsítótár-törő címe EGY helyen (App\Support\Eszkoz, Blade: @eszkoz): a cím a fájl TARTALMÁNAK
 * lenyomatát viseli, tehát minden módosítás új címet ad — a szerver 7 napos gyorsítótára nem mutathat régi stílust.
 */
class EszkozTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_cim_a_tartalom_lenyomatat_viseli_es_hianyzo_fajl_hiba(): void
    {
        $this->assertSame(asset('css/publikus.css') . '?v=' . substr(md5_file(public_path('css/publikus.css')), 0, 12), Eszkoz::url('css/publikus.css'));
        $this->expectException(RuntimeException::class);
        Eszkoz::lenyomat('css/nincs-ilyen-fajl.css');
    }

    public function test_sablonban_minden_sajat_css_es_js_az_eszkoz_iranyelven_at_megy(): void
    {
        $hibak = [];
        $fajlok = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($fajlok as $f) {
            if (! str_ends_with($f->getFilename(), '.blade.php')) {
                continue;
            }
            $tartalom = (string) file_get_contents($f->getPathname());
            // asset('….css|.js') közvetlenül, vagy a régi fájlonkénti filemtime-jelölés: mindkettő a gyorsítótár-csapda.
            if (preg_match_all("~asset\\(\\s*'[^']+\\.(css|js)'\\s*\\)|filemtime\\(~", $tartalom, $m)) {
                $hibak[] = str_replace(resource_path('views') . DIRECTORY_SEPARATOR, '', $f->getPathname()) . ': ' . implode(', ', $m[0]);
            }
        }
        $this->assertSame([], $hibak, 'Saját CSS/JS-t csak @eszkoz(...)-szal szabad hivatkozni.');
    }

    public function test_a_publikus_lap_minden_sajat_css_es_js_cime_lenyomatos(): void
    {
        $seed = json_decode((string) file_get_contents(base_path('seed/settings.json')), true);
        Settings::save(array_intersect_key($seed, array_flip(['festival', 'submission', 'public', 'notifications'])));
        $html = $this->get('/hamarosan')->getContent();
        preg_match_all('~(?:href|src)="([^"]+\.(?:css|js)(?:\?[^"]*)?)"~', $html, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $cim) {
            $this->assertMatchesRegularExpression('~\?v=[0-9a-f]{12}$|/arculat/tema-[0-9a-f]+\.css$~', $cim, "lenyomat nélküli cím: {$cim}");
        }
    }
}
