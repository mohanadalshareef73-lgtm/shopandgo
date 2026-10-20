<?php

namespace Tests\Feature\Http\Controllers\API;

use App\Models\Product;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\API\RegionController
 */
class RegionControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    /**
     * @test
     */
    public function index_behaves_as_expected()
    {
        $regions = Region::factory()->count(3)->create();

        $response = $this->get(route('region.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    /**
     * @test
     */
    public function show_behaves_as_expected()
    {
        $region = Region::factory()->create();

        $response = $this->get(route('region.show', $region));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }
}
