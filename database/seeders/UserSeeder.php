<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\User::create([
            'name' => 'admin',
            'user' => 'admin',
            'email' => 'admin@st.com',
            'sucursal_id' => 1,
            'password' => bcrypt('admin'),
        ]);
    }
}
