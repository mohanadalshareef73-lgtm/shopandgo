<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Classification;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\VoucherController
 */
class VoucherControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    /**
     * @test
     */
    public function index_displays_view()
    {
        $vouchers = Voucher::factory()->count(3)->create();

        $response = $this->get(route('voucher.index'));

        $response->assertOk();
        $response->assertViewIs('voucher.index');
        $response->assertViewHas('vouchers');
    }


    /**
     * @test
     */
    public function create_displays_view()
    {
        $response = $this->get(route('voucher.create'));

        $response->assertOk();
        $response->assertViewIs('voucher.create');
    }


    /**
     * @test
     */
    public function store_uses_form_request_validation()
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\VoucherController::class,
            'store',
            \App\Http\Requests\VoucherStoreRequest::class
        );
    }

    /**
     * @test
     */
    public function store_saves_and_redirects()
    {
        $classification = Classification::factory()->create();
        $user = User::factory()->create();
        $serial_number = $this->faker->word;

        $response = $this->post(route('voucher.store'), [
            'classification_id' => $classification->id,
            'user_id' => $user->id,
            'serial_number' => $serial_number,
        ]);

        $vouchers = Voucher::query()
            ->where('classification_id', $classification->id)
            ->where('user_id', $user->id)
            ->where('serial_number', $serial_number)
            ->get();
        $this->assertCount(1, $vouchers);
        $voucher = $vouchers->first();

        $response->assertRedirect(route('voucher.index'));
        $response->assertSessionHas('voucher.id', $voucher->id);
    }


    /**
     * @test
     */
    public function show_displays_view()
    {
        $voucher = Voucher::factory()->create();

        $response = $this->get(route('voucher.show', $voucher));

        $response->assertOk();
        $response->assertViewIs('voucher.show');
        $response->assertViewHas('voucher');
    }


    /**
     * @test
     */
    public function edit_displays_view()
    {
        $voucher = Voucher::factory()->create();

        $response = $this->get(route('voucher.edit', $voucher));

        $response->assertOk();
        $response->assertViewIs('voucher.edit');
        $response->assertViewHas('voucher');
    }


    /**
     * @test
     */
    public function update_uses_form_request_validation()
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\VoucherController::class,
            'update',
            \App\Http\Requests\VoucherUpdateRequest::class
        );
    }

    /**
     * @test
     */
    public function update_redirects()
    {
        $voucher = Voucher::factory()->create();
        $classification = Classification::factory()->create();
        $user = User::factory()->create();
        $serial_number = $this->faker->word;

        $response = $this->put(route('voucher.update', $voucher), [
            'classification_id' => $classification->id,
            'user_id' => $user->id,
            'serial_number' => $serial_number,
        ]);

        $voucher->refresh();

        $response->assertRedirect(route('voucher.index'));
        $response->assertSessionHas('voucher.id', $voucher->id);

        $this->assertEquals($classification->id, $voucher->classification_id);
        $this->assertEquals($user->id, $voucher->user_id);
        $this->assertEquals($serial_number, $voucher->serial_number);
    }


    /**
     * @test
     */
    public function destroy_deletes_and_redirects()
    {
        $voucher = Voucher::factory()->create();

        $response = $this->delete(route('voucher.destroy', $voucher));

        $response->assertRedirect(route('voucher.index'));

        $this->assertModelMissing($voucher);
    }
}
