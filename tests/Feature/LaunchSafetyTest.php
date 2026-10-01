<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustProxies;
use App\Models\Coupon;
use App\Providers\AppServiceProvider;
use App\Support\Day;
use App\Support\Images;
use App\Support\PageCache;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Verificările de lansare: indexare, expirare la limita zilei, cache, „verificat”, securitate. */
class LaunchSafetyTest extends TestCase
{
    use RefreshDatabase;

    /** Simulează producția: APP_ENV=production, APP_URL fix (AppServiceProvider rulează din nou). */
    private function asProduction(): void
    {
        $this->app['env'] = 'production';
        config(['app.url' => 'https://cupoane.example.ro', 'app.debug' => true]);
        (new AppServiceProvider($this->app))->boot();
    }

    public function test_production_is_indexable_and_everything_else_is_noindex(): void
    {
        $this->coupon($this->store());
        $this->assertStringContainsString('noindex', $this->get('/')->getContent(), 'local / preview = noindex');

        $this->asProduction();
        $this->assertFalse(config('app.debug'), 'APP_DEBUG trebuie să fie oprit în producție');
        $robots = $this->get('https://cupoane.example.ro/robots.txt')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/^Disallow: \/\s*$/m', $robots, 'robots.txt nu are voie să blocheze tot site-ul');
        $this->assertStringContainsString('Sitemap: https://cupoane.example.ro/sitemap.xml', $robots);
        $this->assertStringNotContainsString('noindex', $this->get('https://cupoane.example.ro/')->getContent());
    }

    public function test_forwarded_host_cannot_change_canonical_or_robots_in_production(): void
    {
        $store = $this->store();
        $this->coupon($store);
        $this->asProduction();

        $html = $this->get('/magazin/'.$store->slug, ['X-Forwarded-Host' => 'atacator.trycloudflare.com'])->getContent();
        $this->assertStringContainsString('<link rel="canonical" href="https://cupoane.example.ro/magazin/'.$store->slug.'">', $html);
        $this->assertStringNotContainsString('atacator', $html);
        $this->assertStringNotContainsString('noindex', $html);
    }

    public function test_real_visitor_ip_is_trusted_only_from_cloudflare_in_production(): void
    {
        $this->asProduction();
        $ip = function (string $remote) {
            $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_FOR' => '86.120.1.2']);

            return (new TrustProxies)->handle($request, fn ($r) => response($r->ip()))->getContent();
        };

        $this->assertSame('86.120.1.2', $ip('172.64.10.10'), 'prin Cloudflare: IP-ul vizitatorului');
        $this->assertSame('203.0.113.9', $ip('203.0.113.9'), 'direct la server: antetul e ignorat');
    }

    public function test_store_pages_are_indexable_from_one_offer_or_with_own_text(): void
    {
        $this->asProduction();
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

    public function test_stores_without_offers_keep_an_internal_link_and_honest_description(): void
    {
        $store = $this->store(['name' => 'Doar Text', 'description' => '<p>Despre magazin</p>']);
        $this->store(['name' => 'Gol Complet']);

        $this->get('/magazine')->assertSee('Doar Text')->assertSee('fără coduri active acum')->assertDontSee('Gol Complet');
        $html = $this->get($store->url())->getContent();
        $this->assertStringNotContainsString('0 oferte', $html);
        $this->assertStringContainsString('<meta name="description" content="Coduri de reducere Doar Text: cum economisești', $html);
    }

    public function test_go_link_of_deleted_offer_leads_to_store_page(): void
    {
        $store = $this->store();
        $c = $this->coupon($store);
        $c->delete();

        $this->get('/go/'.$c->id, ['User-Agent' => 'Mozilla/5.0'])->assertRedirect($store->url());
    }

    public function test_batch_operations_flush_the_page_cache_once(): void
    {
        $store = $this->store();
        $this->get('/')->assertHeader('X-Page-Cache', 'MISS');
        $this->get('/')->assertHeader('X-Page-Cache', 'HIT');

        PageCache::batch(function () use ($store) {
            $this->coupon($store, ['title' => 'Nouă în lot']);
            $this->assertNotNull(PageCache::store()->get(PageCache::key(Request::create(url('/')))), 'în timpul lotului cache-ul rămâne');
        });

        $this->get('/')->assertHeader('X-Page-Cache', 'MISS')->assertSee('Nouă în lot');
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
