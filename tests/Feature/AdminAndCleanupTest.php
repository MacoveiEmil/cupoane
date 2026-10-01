<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use App\Support\Day;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAndCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_screen_loads(): void
    {
        $store = $this->store();
        $coupon = $this->coupon($store);
        $this->actingAs(User::factory()->create());

        foreach ([
            '/admin', '/admin/coupons', '/admin/coupons/create', '/admin/coupons/'.$coupon->id.'/edit',
            '/admin/stores', '/admin/stores/create', '/admin/stores/'.$store->getRouteKey().'/edit',
            '/admin/deals', '/admin/deals/create', '/admin/categories', '/admin/categories/create',
            '/admin/tags', '/admin/pages', '/admin/pages/create', '/admin/setari', '/admin/import',
        ] as $url) {
            $this->assertSame(200, $this->get($url)->status(), "Admin: {$url}");
        }
        auth()->logout();
        $this->get('/admin/login')->assertOk();
    }

    public function test_admin_requires_login(): void
    {
        $this->get('/admin/coupons')->assertRedirect('/admin/login');
    }

    public function test_daily_cleanup(): void
    {
        $store = $this->store();
        $expired = $this->coupon($store, ['expires_on' => Day::today()->subDay()]);
        $stale = $this->coupon($store, ['expires_on' => null, 'verified_on' => Day::today()->subDays(45)]);
        $fresh = $this->coupon($store, ['expires_on' => Day::today()]); // expiră azi: încă valabilă

        $this->artisan('cupons:cleanup --dry-run')->assertSuccessful();
        $this->assertNull(Coupon::withTrashed()->find($expired->id)->deleted_at);

        $this->artisan('cupons:cleanup')->assertSuccessful();
        $this->assertSoftDeleted($expired);
        $this->assertTrue(Coupon::find($stale->id)->needs_recheck);
        $this->assertSame('draft', Coupon::find($stale->id)->status);
        $this->assertTrue(Coupon::find($fresh->id)->isActive());
    }
}
