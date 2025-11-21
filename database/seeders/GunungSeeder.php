<?php

namespace Database\Seeders;

use App\Models\Gunung;
use App\Models\KabupatenKota;
use App\Models\Point;
use App\Models\Provinsi;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class GunungSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Negara
        if (!DB::table('negara')->exists()) {
            $sqlPath = database_path('seeders/sql/negara-20250815.sql');
            $sql     = File::get($sqlPath);
            DB::unprepared($sql);
        } else {
            $this->command->info('Negara data already present, skipping negara seeding.');
        }

        // Rute Tingkat Kesulitan
        if (!DB::table('rute_tingkat_kesulitan')->exists()) {
            $sqlPath = database_path('seeders/sql/rute_tingkat_kesulitan-20250815.sql');
            $sql     = File::get($sqlPath);
            DB::unprepared($sql);
        } else {
            $this->command->info('Rute Tingkat Kesulitan data already present, skipping seeding.');
        }

        // Gunung
        if (!DB::table('gunung')->exists()) {
            $sqlPath = database_path('seeders/sql/gunung-20250815.sql');
            $sql     = File::get($sqlPath);
            DB::unprepared($sql);
        } else {
            $this->command->info('Gunung data already present, skipping gunung seeding.');
        }

        // Rute
        if (!DB::table('rute')->exists()) {
            $sqlPath = database_path('seeders/sql/rute-20250815.sql');
            $sql     = File::get($sqlPath);
            DB::unprepared($sql);
        } else {
            $this->command->info('Rute data already present, skipping rute seeding.');
        }

        // Point
        if (!DB::table('point')->exists()) {
            $sqlPath = database_path('seeders/sql/point-20250815.sql');
            $sql     = File::get($sqlPath);
            DB::unprepared($sql);
        } else {
            $this->command->info('Point data already present, skipping point seeding.');
        }
    }
}
