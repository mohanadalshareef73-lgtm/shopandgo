<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\ProviderController
 */
class ProviderControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    /**
     * @test
     */
    public function index_displays_view()
    {
        $providers = Provider::factory()->count(3)->create();

        $response = $this->get(route('provider.index'));

        $response->assertOk();
        $response->assertViewIs('provider.index');
        $response->assertViewHas('providers');
    }


    /**
     * @test
     */
    public function create_displays_view()
    {
        $response = $this->get(route('provider.create'));

        $response->assertOk();
        $response->assertViewIs('provider.create');
    }


    /**
     * @test
     */
    public function store_uses_form_request_validation()
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ProviderController::class,
            'store',
            \App\Http\Requests\ProviderStoreRequest::class
        );
    }

    /**
     * @test
     */
    public function store_saves_and_redirects()
    {
        $name = $this->faker->name;
        $api = $this->faker->word;
        $token = $this->faker->text;

        $response = $this->post(route('provider.store'), [
            'name' => $name,
            'api' => $api,
            'token' => $token,
        ]);

        $providers = Provider::query()
            ->where('name', $name)
            ->where('api', $api)
            ->where('token', $token)
            ->get();
        $this->assertCount(1, $providers);
        $provider = $providers->first();

        $response->assertRedirect(route('provider.index'));
        $response->assertSessionHas('provider.id', $provider->id);
    }


    /**
     * @test
     */
    public function show_displays_view()
    {
        $provider = Provider::factory()->create();

        $response = $this->get(route('provider.show', $provider));

        $response->assertOk();
        $response->assertViewIs('provider.show');
        $response->assertViewHas('provider');
    }


    /**
     * @test
     */
    public function edit_displays_view()
    {
        $provider = Provider::factory()->create();

        $response = $this->get(route('provider.edit', $provider));

        $response->assertOk();
        $response->assertViewIs('provider.edit');
        $response->assertViewHas('provider');
    }


    /**
     * @test
     */
    public function update_uses_form_request_validation()
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ProviderController::class,
            'update',
            \App\Http\Requests\ProviderUpdateRequest::class
        );
    }

    /**
     * @test
     */
    public function update_redirects()
    {
        $provider = Provider::factory()->create();
        $name = $this->faker->name;
        $api = $this->faker->word;
        $token = $this->faker->text;

        $response = $this->put(route('provider.update', $provider), [
            'name' => $name,
            'api' => $api,
            'token' => $token,
        ]);

        $provider->refresh();

        $response->assertRedirect(route('provider.index'));
        $response->assertSessionHas('provider.id', $provider->id);

        $this->assertEquals($name, $provider->name);
        $this->assertEquals($api, $provider->api);
        $this->assertEquals($token, $provider->token);
    }


    /**
     * @test
     */
    public function destroy_deletes_and_redirects()
    {
        $provider = Provider::factory()->create();

        $response = $this->delete(route('provider.destroy', $provider));

        $response->assertRedirect(route('provider.index'));

        $this->assertModelMissing($provider);
    }
}
