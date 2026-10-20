<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Dept;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DeptListingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('types')->updateOrInsert(['id' => 1], ['type' => 'مدير النظام', 'created_at' => now(), 'updated_at' => now()]);
        $this->admin = User::factory()->create(['type_id' => 1, 'status' => true]);
        $this->admin->givePermissionTo(Permission::firstOrCreate(
            ['name' => 'dept-list', 'guard_name' => 'web'], ['ar_name' => 'عرض الديون']
        ));
        $this->actingAs($this->admin);
    }

    public function test_empty_debt_listings_render_successfully(): void
    {
        foreach (['/dept', '/dept-unpaid', '/dept-paid', '/dept-search?status=dept'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_filtered_pages_display_matching_debts_and_actual_payments(): void
    {
        $customer = Customer::create(['name' => 'Debt customer', 'phone_1' => '0910000001', 'code' => 'DEBT-1']);
        $unpaid = $this->debt($customer, 'dept', 'OUTSTANDING-ROW');
        $paid = $this->debt($customer, 'paid', 'PAID-ROW');
        DB::table('dept_histories')->insert([
            ['dept_id' => $unpaid->id, 'paid_price' => 25, 'created_id' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()],
            ['dept_id' => $paid->id, 'paid_price' => 100, 'created_id' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->get('/dept-unpaid')->assertOk()->assertSee('OUTSTANDING-ROW')->assertDontSee('PAID-ROW')
            ->assertSee('75.000 DLY')->assertSee('25.000 DLY');
        $this->get('/dept-paid')->assertOk()->assertSee('PAID-ROW')->assertDontSee('OUTSTANDING-ROW')
            ->assertSee('100.000 DLY');
        $this->get('/dept-search?name=Debt&status=dept')->assertOk()
            ->assertSee('OUTSTANDING-ROW')->assertDontSee('PAID-ROW');
        $this->get('/dept-search?name=Missing')->assertOk()
            ->assertDontSee('OUTSTANDING-ROW')->assertDontSee('PAID-ROW');
    }

    public function test_debt_pagination_and_search_filters_are_preserved(): void
    {
        $customer = Customer::create(['name' => 'Paged customer', 'phone_1' => '0910000002', 'code' => 'DEBT-2']);
        for ($i = 1; $i <= 26; $i++) {
            $this->debt($customer, 'dept', sprintf('PAGED-ROW-%02d', $i));
        }

        $this->get('/dept-unpaid')->assertOk()->assertSee('PAGED-ROW-01')
            ->assertDontSee('PAGED-ROW-26')->assertSee('dept-unpaid?page=2', false);
        $this->get('/dept-unpaid?page=2')->assertOk()->assertSee('PAGED-ROW-26')->assertDontSee('PAGED-ROW-01');
        $this->get('/dept-search?name=Paged&status=dept')->assertOk()
            ->assertSee('name=Paged&amp;status=dept&amp;page=2', false);
    }

    private function debt(Customer $customer, string $status, string $note): Dept
    {
        $dept = new Dept;
        $dept->customer_id = $customer->id;
        $dept->created_id = $this->admin->id;
        $dept->total = 100;
        $dept->status = $status;
        $dept->date = now()->toDateString();
        $dept->note = $note;
        $dept->save();

        return $dept;
    }
}
