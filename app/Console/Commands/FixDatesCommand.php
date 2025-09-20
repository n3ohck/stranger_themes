<?php

namespace App\Console\Commands;

use App\Models\Venta;
use App\Scopes\SucursalFilterScope;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixDatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stranger:set-time-zone-sales';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {
        Venta::withOutGlobalScope(new SucursalFilterScope)
            ->get()
            ->each(function (Venta $venta) {
                $toZone = Carbon::parse($venta->created_at)
                    ->setTimezone(config('app.display_timezone', 'America/Chihuahua'))
                    ->format('Y-m-d H:i:s');
                if ($venta->created_at->format('Y-m-d H:i:s') !== $toZone) {
                    dd($venta->created_at, $toZone);
                }
            });
    }
}
