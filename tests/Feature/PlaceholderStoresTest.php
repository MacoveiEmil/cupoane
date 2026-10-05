<?php

namespace Tests\Feature;

use App\Import\Importer;
use App\Models\Coupon;
use App\Models\Program;
use App\Models\Store;
use App\Support\PageCache;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Programele neaplicate devin magazine ascunse; cu o ofertă publicată apar (link direct); la acceptare trec pe afiliere. */
class PlaceholderStoresTest extends TestCase
{
    use RefreshDatabase;

    private function program(int $id, string $name, string $category = 'Fashion', array $extra = []): array
    {
        return ['id' => $id, 'name' => $name, 'main_url' => "https://{$name}", 'unique_code' => "U{$id}", 'status' => 'active',
            'category' => ['name' => $category], 'logo_path' => '', 'advertiser_traffic_sources' => [['traffic_source_id' => 17, 'usage' => $category === 'Fashion' ? 'no' : 'yes', 'status' => 'active', 'name' => 'Coupons/Vouchers']]] + $extra;
    }

    private function fake2p(): void
    {
        Http::fake(function (Request $r) {
            $url = $r->url();
            if (str_contains($url, '/users/sign_in')) {
                return Http::response(['user' => ['unique_code' => 'AFF1']], 200, ['access-token' => 't', 'client' => 'c', 'uid' => 'u']);
            }
            $page = ['metadata' => ['pagination' => ['results' => 1, 'pages' => 1, 'current_page' => 1]]];
            if (str_contains($url, '/affiliate/programs')) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

                return Http::response($page + ['programs' => match ($q['filter']['relation'] ?? '') {
                    'accepted' => [$this->program(1, 'acceptat.ro')],
                    'not_applied' => [$this->program(2, 'neaplicat.ro', 'Home & Garden'), $this->program(3, 'adult.ro', 'Adult')],
                    default => [],
                }]);
            }
            if (str_contains($url, '/affiliate/advertiser_promotions')) {
                return Http::response($page + ['advertiser_promotions' => [
                    ['id' => 50, 'name' => 'Reducere 20% la tot', 'coupon' => '', 'landing_page_link' => 'https://neaplicat.ro/promo', 'customer_description' => '',
                        'promotion_start' => now()->subDay()->toIso8601String(), 'promotion_end' => now()->addDays(10)->toIso8601String(), 'program' => ['id' => 2]],
                ]]);
            }

            return Http::response('', 404);
        });
    }

    public function test_placeholders_stay_hidden_until_an_offer_is_published_then_switch_to_affiliate_on_acceptance(): void
    {
        Settings::set(['tp_email' => 'a@b.ro', 'tp_password' => 'x', 'tp_user_key' => 'k', 'tp_enabled' => true]);
        $this->fake2p();

        $s = Importer::run('2performant');
        $this->assertSame('', $s['error']);
        $this->assertSame(1, $s['stats']['placeholders'], 'adult.ro e sărit');

        $store = Store::where('url', 'https://neaplicat.ro')->firstOrFail();
        $this->assertSame('not_applied', $store->program->relation);
        $this->assertTrue($store->program->allows_coupons);
        $this->assertSame('casa-si-gradina', $store->categories->first()->slug, 'categorie în română');
        $this->assertNull(Store::where('url', 'https://adult.ro')->first());

        // Ascuns: fără ofertă publicată nu apare nicăieri și are noindex.
        $this->assertStringNotContainsString('neaplicat.ro', $this->get('/magazine')->getContent());
        $this->assertStringContainsString('noindex', $this->get($store->url())->getContent());

        // Oferta importată e ciornă, cu link direct; publicată → magazinul apare.
        $coupon = Coupon::where('store_id', $store->id)->firstOrFail();
        $this->assertSame('draft', $coupon->status);
        $this->assertSame('https://neaplicat.ro/promo', $coupon->affiliate_url);
        $coupon->update(['status' => 'published']);
        PageCache::flush();
        $this->assertStringContainsString('Neaplicat.ro', $this->get('/')->getContent());
        $this->get('/go/'.$coupon->id, ['User-Agent' => 'Mozilla/5.0'])->assertRedirect('https://neaplicat.ro/promo');

        // Acceptare: magazinul și oferta trec automat pe link de afiliere.
        Program::where('program_id', '2')->update(['accepted' => true, 'relation' => 'accepted']);
        Importer::syncAffiliateLinks();
        $this->assertStringContainsString('unique=U2', $store->fresh()->affiliate_url);
        $link = $coupon->fresh()->affiliate_url;
        $this->assertStringStartsWith('https://event.2performant.com/events/click?', $link);
        $this->assertStringContainsString('redirect_to=https%3A%2F%2Fneaplicat.ro%2Fpromo', $link);
    }
}
