<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Support\Day;
use App\Support\Images;
use App\Support\PageCache;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Verificările de lansare: indexare, expirare la limita zilei, cache, „verificat”, securitate. */
class LaunchSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_domain_is_indexable_and_preview_link_is_not(): void
    {
        $this->coupon($this->store());

        $robots = $this->get('https://cupoane.example.ro/robots.txt')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/^Disallow: \/\s*$/m', $robots, 'robots.txt nu are voie să blocheze tot site-ul');
        $this->assertStringContainsString('Sitemap: https://cupoane.example.ro/sitemap.xml', $robots);
        $this->assertStringNotContainsString('noindex', $this->get('https://cupoane.example.ro/')->getContent());

        $this->assertStringContainsString('noindex', $this->get('https://abc-def.trycloudflare.com/')->getContent());
    }

    public function test_store_pages_are_indexable_from_one_offer_or_with_own_text(): void
    {
        $withOffer = $this->store(['name' => 'Cu oferta']);
        $this->coupon($withOffer);
        $empty = $this->store(['name' => 'Fara oferte']);
        $described = $this->store(['name' => 'Cu text', 'description' => '<p>Despre magazin</p>']);
        $forced = $this->store(['name' => 'Fortat', 'force_index' => true]);

        $this->assertStringNotContainsString('noindex', $this->get($withOffer->url())->getContent());
        $this->assertStringContainsString('noindex', $this->get($empty->url())->getContent());
        $this->assertStringNotContainsString('noindex', $this->get($described->url())->getContent());
        $this->assertStringNotContainsString('noindex', $this->get($forced->url())->getContent());

        $sitemap = $this->get('/sitemap.xml')->getContent();
        $this->assertStringContainsString($withOffer->url(), $sitemap);
        $this->assertStringNotContainsString($empty->url(), $sitemap);
    }

    public function test_offer_expiring_today_is_active_until_midnight_romanian_time(): void
    {
        $store = $this->store();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 23:59:30', 'Europe/Bucharest'));
        $c = $this->coupon($store, ['expires_on' => '2026-10-05']);
        $this->assertTrue(Coupon::active()->whereKey($c->id)->exists());

        $this->travelTo(CarbonImmutable::parse('2026-10-06 00:00:30', 'Europe/Bucharest'));
        $this->assertFalse(Coupon::active()->whereKey($c->id)->exists());
    }

    public function test_cached_pages_expire_at_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-05 23:00:00', 'Europe/Bucharest'));
        $this->assertEqualsWithDelta(3630, PageCache::ttl(), 5);

        $res = $this->get('/')->assertOk();
        $this->assertStringContainsString('s-maxage=300', $res->headers->get('Cache-Control'));
        $this->assertMatchesRegularExpression('/max-age=36\d\d/', $res->headers->get('X-LiteSpeed-Cache-Control'));
    }

    public function test_imported_offers_are_not_shown_as_verified(): void
    {
        $store = $this->store();
        $imported = $this->coupon($store, ['title' => 'Importata', 'ext_id' => '2p-1']);
        $manual = $this->coupon($store, ['title' => 'Manuala']);

        $this->assertNull($imported->verified_on);
        $this->assertNotNull($manual->verified_on);
        $this->get($store->url())->assertSee('Adăugat pe')->assertSee('Verificat pe');
    }

    public function test_security_headers_and_prefetch_clicks(): void
    {
        $c = $this->coupon($this->store());

        $res = $this->get('/')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("frame-ancestors 'none'", $res->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('SearchAction', $res->getContent());

        $this->get('/go/'.$c->id, ['User-Agent' => 'Mozilla/5.0', 'Sec-Purpose' => 'prefetch']);
        $this->assertNull(DB::table('clicks')->where('object_id', $c->id)->value('count'));
    }

    public function test_image_upload_rejects_svg_and_converts_to_webp(): void
    {
        Storage::fake('public');

        $path = Images::fromUpload(UploadedFile::fake()->image('logo.png', 600, 200), 'logos', 'logo_path', 320, 160);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        $this->expectException(ValidationException::class);
        Images::fromUpload(UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'), 'logos', 'logo_path', 320, 160);
    }
}
