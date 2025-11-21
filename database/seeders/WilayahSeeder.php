<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class WilayahSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Skip seeding if data already exists to avoid duplicate primary keys
        $hasWilayahData = DB::table('desa')->exists()
            || DB::table('kecamatan')->exists()
            || DB::table('kabupaten_kota')->exists()
            || DB::table('provinsi')->exists();

        if ($hasWilayahData) {
            $this->command->info('Wilayah data already present, skipping WilayahSeeder.');
            return;
        }

        $sqlPath = database_path('seeders/sql/wilayah-20241112.sql');

        if (File::exists($sqlPath)) {
            $sql = File::get($sqlPath);
            DB::unprepared($sql);
        } else {
            $this->command->error("SQL file not found: {$sqlPath}");
            return;
        }
    }
}
