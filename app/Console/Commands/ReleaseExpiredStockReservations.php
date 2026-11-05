<?php

namespace App\Console\Commands;

use App\Models\StockReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseExpiredStockReservations extends Command
{
    protected $signature = 'stock-reservations:release-expired';

    protected $description = 'Release expired stock reservations for unpaid orders.';

    public function handle(): int
    {
        DB::transaction(function (): void {
            StockReservation::query()
                ->where('status', StockReservation::STATUS_RESERVED)
                ->where('expires_at', '<=', now())
                ->whereHas('order', fn ($query) => $query->whereNull('paid_at'))
                ->orderBy('id')
                ->lockForUpdate()
                ->chunkById(500, function ($reservations): void {
                    foreach ($reservations as $reservation) {
                        $reservation->status = StockReservation::STATUS_RELEASED;
                        $reservation->save();
                    }
                });
        }, 3);

        return self::SUCCESS;
    }
}
