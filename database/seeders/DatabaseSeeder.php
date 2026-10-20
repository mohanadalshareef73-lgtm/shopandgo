<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\whatsappMessage;
use Illuminate\Database\Seeder;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RegionSeeder;
use App\Models\PurchaseDescription;
use Database\Seeders\ProductSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ExchangeSeeder;
use Database\Seeders\ProviderSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(TypeSeeder::class);
        $this->call(TreasurySeeder::class);
        $this->call(PermissionTableSeeder::class);
        $this->call(CartPermissionsSeeder::class);
        $this->call(AdminSeeder::class);
        $this->call(DescriptionSeeder::class);
        $this->call(InvoiceSeeder::class);
        $this->call(StatusSeeder::class);
        $this->call(whatsappMessagesSeeder::class);
    }
}
