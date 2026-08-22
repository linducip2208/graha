<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\StockBalance;
use App\Models\User;
use App\Notifications\OperationalNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class NotifyLowStock extends Command
{
    protected $signature = 'inventory:notify-low-stock';

    protected $description = 'Mengirim notifikasi harian untuk stok di bawah minimum (sekali per item/gudang/hari)';

    public function handle(): int
    {
        $notified = 0;
        Company::query()->select('id')->chunkById(100, function ($companies) use (&$notified) {
            foreach ($companies as $company) {
                $recipients = User::query()->select('users.*')->distinct()
                    ->join('company_user', 'company_user.user_id', '=', 'users.id')
                    ->join('company_user_role', 'company_user_role.company_user_id', '=', 'company_user.id')
                    ->join('roles', 'roles.id', '=', 'company_user_role.role_id')
                    ->join('permission_role as pr', 'pr.role_id', '=', 'roles.id')
                    ->join('permissions', 'permissions.id', '=', 'pr.permission_id')
                    ->where('company_user.company_id', $company->id)
                    ->where('company_user.is_active', true)
                    ->where('users.is_active', true)
                    ->where('permissions.code', 'inventory.view')
                    ->get();
                if ($recipients->isEmpty()) {
                    continue;
                }
                StockBalance::where('stock_balances.company_id', $company->id)
                    ->join('items', 'items.id', '=', 'stock_balances.item_id')
                    ->whereColumn('stock_balances.quantity', '<=', 'items.minimum_stock')
                    ->with(['item', 'warehouse'])
                    ->get()
                    ->each(function (StockBalance $balance) use ($recipients, &$notified) {
                        foreach ($recipients as $recipient) {
                            $already = DatabaseNotification::query()
                                ->where('type', OperationalNotification::class)
                                ->where('data->event', 'stock_low')
                                ->where('data->item_id', $balance->item_id)
                                ->where('data->warehouse_id', $balance->warehouse_id)
                                ->whereDate('created_at', today())
                                ->where('notifiable_id', $recipient->id)
                                ->exists();
                            if ($already) {
                                continue;
                            }
                            $recipient->notify(new OperationalNotification('stock_low', [
                                'item_id' => $balance->item_id,
                                'item' => $balance->item?->sku ?? ('#'.$balance->item_id),
                                'warehouse_id' => $balance->warehouse_id,
                                'warehouse' => $balance->warehouse?->code ?? '-',
                                'quantity' => (string) $balance->quantity,
                                'minimum' => (string) ($balance->item?->minimum_stock ?? 0),
                                'url' => '/admin/inventory#stok-kritis',
                            ]));
                            $notified++;
                        }
                    });
            }
        });
        $this->info("Notifikasi stok kritis terkirim: {$notified}.");

        return self::SUCCESS;
    }
}
