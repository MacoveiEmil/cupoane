<?php

namespace Tests\Feature;

use App\Support\Day;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** FAQ homepage, conținut unic per magazin, pagina de încredere, schema, contorul de folosiri. */
class ContentAndFaqTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_faq_is_server_rendered_and_matches_schema(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('id="faq"', $html);
        $this->assertSame(7, substr_count($html, '<summary><h3>'), '7 întrebări, fiecare H3, în HTML-ul inițial');
        $this->assertStringContainsString('href="/cum-verificam-codurile"', $html);
        $this->assertSame(1, substr_count($html, '"@type":"FAQPage"'), 'un singur FAQPage');
        $this->assertSame(7, substr_count($html, '"@type":"Question"'));
        $this->assertStringNotContainsString('SearchAction', $html);
    }

    public function test_homepage_order_is_fresh_then_top_then_stores(): void
    {
        $store = $this->store();
        $this->coupon($store, ['title' => 'Cea nouă', 'value' => 10]);
        $this->coupon($store, ['title' => 'Cea mare', 'value' => 60]);

        $html = $this->get('/')->getContent();
        $fresh = strpos($html, 'Cele mai noi vouchere');
        $top = strpos($html, 'Top coduri și oferte');
        $stores = strpos($html, 'Magazine populare</h2>');
        $this->assertTrue($fresh < $top && $top < $stores, 'ordinea: noi → top → magazine');
        $this->assertStringContainsString('class="intro__text"', $html);
    }

    public function test_store_page_shows_only_its_own_content(): void
    {
        $plain = $this->store(['name' => 'Fara Continut']);
        $this->coupon($plain);
        $html = $this->get($plain->url())->getContent();
        $this->assertStringNotContainsString('Întrebări frecvente', $html, 'fără FAQ completat: fără secțiune generică');
        $this->assertStringNotContainsString('FAQPage', $html);
        $this->assertStringNotContainsString('Cum folosești codurile', $html);
        $this->assertStringContainsString('href="'.url('/cum-verificam-codurile').'"', $html);

        $rich = $this->store([
            'name' => 'Cu Continut',
            'offers_text' => '<p>Text unic sub oferte.</p>',
            'faq' => [['q' => 'Are Cu Continut cod pentru prima comandă?', 'a' => 'Da, vezi [condițiile](/cum-verificam-codurile).']],
            'howto' => [['t' => 'Pasul unic', 'p' => 'Explicație unică.']],
        ]);
        $this->coupon($rich);
        $html = $this->get($rich->url())->getContent();
        $this->assertStringContainsString('Text unic sub oferte.', $html);
        $this->assertStringContainsString('<summary><h3>Are Cu Continut cod pentru prima comandă?</h3></summary>', $html);
        $this->assertStringContainsString('<a href="/cum-verificam-codurile">condițiile</a>', $html);
        $this->assertSame(1, substr_count($html, '"@type":"FAQPage"'));
        $this->assertStringContainsString('Pasul unic', $html);
    }

    public function test_listing_pages_have_no_duplicated_faq(): void
    {
        foreach (['/magazine', '/categorii', '/top-oferte'] as $url) {
            $this->assertStringNotContainsString('FAQPage', $this->get($url)->getContent(), $url);
        }
    }

    public function test_verification_page_exists_and_old_urls_redirect(): void
    {
        $this->get('/cum-verificam-codurile')->assertOk()->assertSee('Cum câștigăm bani')->assertSee('Verificat pe');
        $this->get('/metodologie')->assertRedirect('/cum-verificam-codurile')->assertStatus(301);
        $this->get('/intrebari-frecvente')->assertRedirect('/#faq')->assertStatus(301);
        $this->assertStringContainsString('/cum-verificam-codurile', $this->get('/')->getContent(), 'link din footer / FAQ');
    }

    public function test_usage_counter_appears_from_three_uses(): void
    {
        $store = $this->store();
        $c = $this->coupon($store);
        DB::table('clicks')->insert(['object_type' => 1, 'object_id' => $c->id, 'date' => Day::today()->toDateString(), 'count' => 2]);
        $this->assertStringNotContainsString('Folosit de', $this->get($store->url())->getContent());

        DB::table('clicks')->where('object_id', $c->id)->update(['count' => 23]);
        \App\Support\PageCache::flush();
        $this->get($store->url())->assertSee('Folosit de 23 de ori');
    }

    public function test_content_import_updates_and_sanitizes(): void
    {
        $store = $this->store(['name' => 'Import Test']);
        $file = storage_path('app/test-continut.json');
        file_put_contents($file, json_encode([$store->slug => [
            'offers_text' => '<p onclick="x()">Bun <script>alert(1)</script><a href="javascript:alert(1)">rău</a></p>',
            'faq' => [['q' => 'Întrebare?', 'a' => 'Răspuns.']],
        ]]));

        $this->artisan('cupons:content', ['action' => 'import', 'file' => $file])->assertSuccessful();
        @unlink($file);

        $store->refresh();
        $this->assertSame('<p>Bun alert(1)<a>rău</a></p>', $store->offers_text);
        $this->assertSame([['q' => 'Întrebare?', 'a' => 'Răspuns.']], $store->faq);
    }

    public function test_logo_urls_are_relative(): void
    {
        $store = $this->store(['logo_path' => 'logos/test.webp']);
        $this->assertSame('/storage/logos/test.webp', $store->logoUrl());
    }
}
