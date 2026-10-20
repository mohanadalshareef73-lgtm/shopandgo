<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Regen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\RegenController
 */
class RegenControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function index_displays_view()
    {
        $regens = Regen::factory()->count(3)->create();

        $response = $this->get(route('regen.index'));

        $response->assertOk();
        $response->assertViewIs('regen.index');
        $response->assertViewHas('regens');
    }


    /**
     * @test
     */
    public function show_displays_view()
    {
        $regen = Regen::factory()->create();

        $response = $this->get(route('regen.show', $regen));

        $response->assertOk();
        $response->assertViewIs('regen.show');
        $response->assertViewHas('regen');
    }
}
