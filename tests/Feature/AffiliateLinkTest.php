<?php

namespace Tests\Feature;

use App\Import\Importer;
use App\Models\Program;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Magazinele cu program acceptat trimit prin linkul de afiliere (comision), nu direct la magazin. */
class AffiliateLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_accepted_stores_get_affiliate_link_and_manual_links_are_kept(): void
    {
        Settings::set(['tp_email' => 'a@b.ro', 'tp_password' => 'x', 'tp_user_key' => 'k']);
        Http::fake(['api.2performant.com/users/sign_in' => Http::response(['user' => ['unique_code' => 'AFF123']], 200, ['access-token' => 't', 'client' => 'c', 'uid' => 'u'])]);

        $store = $this->store(['name' => 'Acceptat', 'url' => 'https://acceptat.ro', 'affiliate_url' => null]);
        Program::create(['network' => '2performant', 'program_id' => '77', 'name' => 'acceptat.ro', 'accepted' => true, 'unique_code' => 'UNQ77', 'store_id' => $store->id]);
        $manual = $this->store(['name' => 'Manual', 'url' => 'https://manual.ro', 'affiliate_url' => 'https://link-manual.example/x']);
        Program::create(['network' => '2performant', 'program_id' => '78', 'name' => 'manual.ro', 'accepted' => true, 'unique_code' => 'UNQ78', 'store_id' => $manual->id]);
        $pending = $this->store(['name' => 'Asteptare', 'url' => 'https://asteptare.ro', 'affiliate_url' => null]);
        Program::create(['network' => '2performant', 'program_id' => '79', 'name' => 'asteptare.ro', 'accepted' => false, 'unique_code' => 'UNQ79', 'store_id' => $pending->id]);

        $this->assertSame(1, Importer::syncAffiliateLinks());

        $link = $store->fresh()->affiliate_url;
        $this->assertStringStartsWith('https://event.2performant.com/events/click?', $link);
        $this->assertStringContainsString('aff_code=AFF123', $link);
        $this->assertStringContainsString('unique=UNQ77', $link);
        $this->assertStringContainsString('redirect_to=https%3A%2F%2Facceptat.ro', $link);
        $this->get('/go/'.$store->slug, ['User-Agent' => 'Mozilla/5.0'])->assertRedirect($link);

        $this->assertSame('https://link-manual.example/x', $manual->fresh()->affiliate_url, 'linkul pus manual rămâne');
        $this->assertNull($pending->fresh()->affiliate_url, 'neacceptat: rămâne direct');
    }
}
