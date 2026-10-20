<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\Classification;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\API\VoucherController
 */
class VoucherControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    /**
     * @test
     */
    public function index_behaves_as_expected()
    {
        $vouchers = Voucher::factory()->count(3)->create();

        $response = $this->get(route('voucher.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }
}
