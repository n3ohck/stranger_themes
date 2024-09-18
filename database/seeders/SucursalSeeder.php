<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SucursalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\Sucursal::create([
            'razon_social' => 'Stranger Themes',
            'rfc' => 'X001001001111',
            'email' => 'demo@demo.com'
        ]);
    }
}
