<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Support\Day;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_render_and_only_active_offers_are_listed(): void
    {
        $store = $this->store();
        $this->coupon($store, ['title' => 'Oferta activă']);
        $this->coupon($store, ['title' => 'Oferta expirată', 'expires_on' => Day::today()->subDay()]);
        $this->coupon($store, ['title' => 'Oferta ciornă', 'status' => 'draft']);
        $this->coupon($store, ['title' => 'Oferta viitoare', 'starts_on' => Day::today()->addDay()]);
        Page::create(['slug' => 'despre-noi', 'title' => 'Despre noi', 'body' => '<p>Text</p>']);

        $this->get('/')->assertOk()->assertSee('Oferta activă')->assertDontSee('Oferta expirată')->assertDontSee('Oferta ciornă')->assertDontSee('Oferta viitoare');
        $this->get('/magazin/'.$store->slug)->assertOk()->assertSee('Cod reducere Magazin Test')->assertSee('Oferta activă')->assertDontSee('Oferta expirată');
        foreach (['/magazine', '/categorii', '/deal-uri', '/top-oferte', '/cauta?q=mag', '/despre-noi', '/magazine.json'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/nu-exista')->assertNotFound();
    }

    public function test_seo_basics(): void
    {
        $store = $this->store();
        $this->coupon($store);

        // Un singur H1, canonical, schema (regulile de noindex: LaunchSafetyTest).
        $html = $this->get('/magazin/'.$store->slug)->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/magazin/'.$store->slug).'">', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);

        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /go/');
        $this->get('/sitemap.xml')->assertOk()->assertSee('<urlset', false);
        $this->get('/cauta?q=x')->assertHeader('X-Robots-Tag', 'noindex, follow');
    }

    public function test_public_pages_set_no_cookies(): void
    {
        $this->get('/')->assertCookieMissing('laravel_session')->assertCookieMissing('XSRF-TOKEN');
    }
}
