<?php

namespace Database\Seeders;

use App\Services\SiteContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SiteContent::defaults() as $key => $value) {
            DB::table('site_settings')->insertOrIgnore(['key' => $key, 'value' => json_encode($value), 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
