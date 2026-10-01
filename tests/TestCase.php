<?php

namespace Tests;

use App\Models\Coupon;
use App\Models\Store;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Cache-ul de pagini în memorie, ca testele să nu se influențeze între ele.
        config(['cache.stores.pages' => ['driver' => 'array']]);
    }

    protected function store(array $attrs = []): Store
    {
        return Store::create($attrs + ['name' => 'Magazin Test', 'url' => 'https://magazin.example.com', 'affiliate_url' => 'https://aff.example.com/magazin']);
    }

    protected function coupon(Store $store, array $attrs = []): Coupon
    {
        return Coupon::create($attrs + ['store_id' => $store->id, 'title' => '20% reducere la tot', 'code' => 'TEST20', 'value' => 20, 'value_type' => 'procent', 'status' => 'published']);
    }
}
