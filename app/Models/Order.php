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
            ->sum(fn (OrderItem $item): float => $item->remainingQuantity() * (float) $item->unit_price);

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

            $this->ensureCancellable($order);

            $itemQuantities = $order->items()
                ->lockForUpdate()
                ->get()
                ->mapWithKeys(fn (OrderItem $item): array => [$item->id => $item->remainingQuantity()])
                ->filter(fn (int $quantity): bool => $quantity > 0)
                ->all();

            if ($itemQuantities === []) {
                throw new DomainException('This order is already cancelled.');
            }

            $this->applyCancellations($order, $itemQuantities);
        });

        $this->refresh();
    }

    /**
     * @param  array<int, int>  $itemQuantities
     */
    public function cancelItems(array $itemQuantities): void
    {
        DB::transaction(function () use ($itemQuantities): void {
            $order = self::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            $this->ensureCancellable($order);
            $this->applyCancellations($order, $this->normalizeCancellations($itemQuantities));
        });

        $this->refresh();
    }

    private function ensureCancellable(Order $order): void
    {
        if ($order->status === self::STATUS_CANCELLED) {
            throw new DomainException('This order is already cancelled.');
        }

        if (! in_array($order->status, [self::STATUS_CONFIRMED, self::STATUS_PARTIALLY_CANCELLED], true)) {
            throw new DomainException('Only confirmed orders can be cancelled.');
        }
    }

    /**
     * @param  array<int, int>  $itemQuantities
     * @return array<int, int>
     */
    private function normalizeCancellations(array $itemQuantities): array
    {
        if ($itemQuantities === []) {
            throw new DomainException('At least one order item must be cancelled.');
        }

        $normalized = [];

        foreach ($itemQuantities as $itemId => $quantity) {
            $itemId = (int) $itemId;
            $quantity = (int) $quantity;

            if ($itemId <= 0 || $quantity <= 0) {
                throw new DomainException('Cancellation quantities must be positive.');
            }

            $normalized[$itemId] = $quantity;
        }

        return $normalized;
    }

    /**
     * @param  array<int, int>  $itemQuantities
     */
    private function applyCancellations(Order $order, array $itemQuantities): void
    {
        $items = $order->items()
            ->whereKey(array_keys($itemQuantities))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($items->count() !== count($itemQuantities)) {
            throw new DomainException('One or more order items do not belong to this order.');
        }

        $statusFrom = $order->status;

        foreach ($itemQuantities as $itemId => $quantity) {
            /** @var OrderItem $item */
            $item = $items->get($itemId);
            $remainingQuantity = $item->remainingQuantity();

            if ($quantity > $remainingQuantity) {
                throw new DomainException('Cannot cancel more than the remaining ordered quantity.');
            }

            $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
            $product->increment('stock_quantity', $quantity);

            $item->forceFill([
                'cancelled_quantity' => $item->cancelled_quantity + $quantity,
            ])->save();

            $product->inventoryLogs()->create([
                'order_id' => $order->id,
                'change_type' => 'return',
                'quantity_change' => $quantity,
                'reason' => "Order {$order->order_number} cancelled",
            ]);
        }

        $order->updateTotal();

        $remainingQuantity = $order->items()
            ->get()
            ->sum(fn (OrderItem $item): int => $item->remainingQuantity());
        $statusTo = $remainingQuantity === 0 ? self::STATUS_CANCELLED : self::STATUS_PARTIALLY_CANCELLED;
        $description = $statusTo === self::STATUS_CANCELLED
            ? "Order {$order->order_number} cancelled"
            : "Order {$order->order_number} partially cancelled";

        $order->activities()->create([
            'activity_type' => $statusTo,
            'description' => $description,
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
        ]);

        $order->forceFill(['status' => $statusTo])->save();
    }
}
