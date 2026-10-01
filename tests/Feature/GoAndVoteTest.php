<?php

namespace Tests\Feature;

use App\Support\Day;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GoAndVoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_go_redirects_to_affiliate_link_and_counts_humans_only(): void
    {
        $store = $this->store();
        $c = $this->coupon($store, ['affiliate_url' => 'https://aff.example.com/oferta']);

        $this->get('/go/'.$c->id, ['User-Agent' => 'Mozilla/5.0'])->assertRedirect('https://aff.example.com/oferta')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/go/'.$c->id, ['User-Agent' => 'Mozilla/5.0']);
        $this->get('/go/'.$c->id, ['User-Agent' => 'Googlebot/2.1']);
        $this->assertSame(2, (int) DB::table('clicks')->where('object_id', $c->id)->value('count'));

        $this->get('/go/'.$store->slug, ['User-Agent' => 'Mozilla/5.0'])->assertRedirect('https://aff.example.com/magazin');
    }

    public function test_expired_offer_redirects_to_store_page(): void
    {
        $store = $this->store();
        $c = $this->coupon($store, ['expires_on' => Day::today()->subDay()]);
        $this->get('/go/'.$c->id, ['User-Agent' => 'Mozilla/5.0'])->assertRedirect($store->url());
    }

    public function test_vote_requires_same_origin_and_counts_once(): void
    {
        $c = $this->coupon($this->store());

        $this->postJson('/api/vote', ['id' => $c->id, 'v' => 1])->assertForbidden();

        $origin = ['Origin' => url('/')];
        $this->postJson('/api/vote', ['id' => $c->id, 'v' => 1], $origin)->assertOk()->assertJson(['ok' => true, 'yes' => 1])->assertCookie('cv');
        // Același IP, aceeași zi → refuzat.
        $this->postJson('/api/vote', ['id' => $c->id, 'v' => 0], $origin)->assertStatus(429);
        $this->assertSame(1, (int) DB::table('votes')->where('coupon_id', $c->id)->sum('yes'));
    }
}
