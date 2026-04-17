<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable(['order_number', 'status', 'total_amount'])]
class Order extends Model
{
    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_CONFIRMED = 'confirmed';

    public const string STATUS_PARTIALLY_CANCELLED = 'partially_cancelled';

    public const string STATUS_CANCELLED = 'cancelled';

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'total_amount' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if ($order->order_number === null || $order->order_number === '') {
                $order->order_number = 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<InventoryLog, $this>
     */
    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    /**
     * @return HasMany<OrderActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(OrderActivity::class);
    }

    public function addItem(Product $product, int $quantity): OrderItem
    {
        if ($this->status !== self::STATUS_DRAFT) {
            throw new DomainException('Only draft orders can be changed.');
        }

        $item = $this->items()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $product->price,
        ]);

        $this->updateTotal();

        return $item;
    }

    public function updateTotal(): void
    {
        $total = $this->items()
            ->get()
            ->sum(fn (OrderItem $item): float => $item->quantity * (float) $item->unit_price);

        $this->forceFill(['total_amount' => $total])->save();
    }

    public function confirm(): void
    {
        DB::transaction(function (): void {
            $order = self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            if ($order->status !== self::STATUS_DRAFT) {
                throw new DomainException('Only draft orders can be confirmed.');
            }

            $items = $order->items()->get();

            if ($items->isEmpty()) {
                throw new DomainException('Orders must have at least one item before confirmation.');
            }

            $order->updateTotal();

            foreach ($items as $item) {
                $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();

                if ($product->stock_quantity < $item->quantity) {
                    throw new DomainException("Insufficient stock for {$product->name}.");
                }

                $product->decrement('stock_quantity', $item->quantity);

                $product->inventoryLogs()->create([
                    'order_id' => $order->id,
                    'change_type' => 'sale',
                    'quantity_change' => -$item->quantity,
                    'reason' => "Order {$order->order_number} confirmed",
                ]);
            }

            $order->activities()->create([
                'activity_type' => 'confirmed',
                'description' => "Order {$order->order_number} confirmed",
                'status_from' => $order->status,
                'status_to' => self::STATUS_CONFIRMED,
            ]);

            $order->forceFill(['status' => self::STATUS_CONFIRMED])->save();
        });

        $this->refresh();
    }

    public function cancel(): void
    {
        DB::transaction(function (): void {
            $order = self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            if ($order->status === self::STATUS_CANCELLED) {
                throw new DomainException('This order is already cancelled.');
            }

            if ($order->status === self::STATUS_CONFIRMED) {
                foreach ($order->items()->get() as $item) {
                    $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                    $product->increment('stock_quantity', $item->quantity);

                    $product->inventoryLogs()->create([
                        'order_id' => $order->id,
                        'change_type' => 'return',
                        'quantity_change' => $item->quantity,
                        'reason' => "Order {$order->order_number} cancelled",
                    ]);
                }
            }

            $order->forceFill(['status' => self::STATUS_CANCELLED])->save();
        });

        $this->refresh();
    }
}
