<?php

use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component {
    #[Computed]
    public function totalProducts(): int
    {
        return Product::query()->count();
    }

    #[Computed]
    public function lowStockProducts(): int
    {
        return Product::query()
            ->where('stock_quantity', '<=', 5)
            ->count();
    }

    #[Computed]
    public function confirmedOrders(): int
    {
        return Order::query()
            ->where('status', Order::STATUS_CONFIRMED)
            ->count();
    }

    #[Computed]
    public function revenue(): string
    {
        $revenue = Order::query()
            ->whereIn('status', [Order::STATUS_CONFIRMED, Order::STATUS_PARTIALLY_CANCELLED])
            ->sum('total_amount');

        return number_format((float) $revenue, 2);
    }

    /**
     * @return Collection<int, InventoryLog>
     */
    #[Computed]
    public function inventoryLogs(): Collection
    {
        return InventoryLog::query()
            ->with('product')
            ->latest()
            ->take(5)
            ->get();
    }

    /**
     * @return Collection<int, Order>
     */
    #[Computed]
    public function recentOrders(): Collection
    {
        return Order::query()
            ->latest()
            ->take(5)
            ->get();
    }
};
?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
        <flux:subheading>{{ __('Inventory, order, and activity overview.') }}</flux:subheading>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>{{ __('Total products') }}</flux:text>
            <div class="mt-2 text-3xl font-semibold">{{ $this->totalProducts }}</div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>{{ __('Low stock') }}</flux:text>
            <div class="mt-2 text-3xl font-semibold">{{ $this->lowStockProducts }}</div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>{{ __('Confirmed orders') }}</flux:text>
            <div class="mt-2 text-3xl font-semibold">{{ $this->confirmedOrders }}</div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>{{ __('Revenue') }}</flux:text>
            <div class="mt-2 text-3xl font-semibold">₱{{ $this->revenue }}</div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-3 rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:heading level="2">{{ __('Recent inventory activity') }}</flux:heading>

            @forelse ($this->inventoryLogs as $log)
            <div wire:key="inventory-log-{{ $log->id }}" class="border-t border-neutral-200 pt-3 text-sm dark:border-neutral-700">
                <div class="font-medium">{{ $log->product->name }}</div>
                <div class="text-neutral-600 dark:text-neutral-300">
                    {{ ucfirst($log->change_type) }}: {{ $log->quantity_change }} - {{ $log->reason }}
                </div>
            </div>
            @empty
            <flux:text>{{ __('No inventory activity yet.') }}</flux:text>
            @endforelse
        </div>

        <div class="space-y-3 rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:heading level="2">{{ __('Recent order activity') }}</flux:heading>

            @forelse ($this->recentOrders as $order)
            <div wire:key="recent-order-{{ $order->id }}" class="border-t border-neutral-200 pt-3 text-sm dark:border-neutral-700">
                <div class="font-medium">{{ $order->order_number }}</div>
                <div class="text-neutral-600 dark:text-neutral-300">
                    {{ ucfirst(str_replace('_', ' ', $order->status)) }} - ₱{{ $order->total_amount }}
                </div>
            </div>
            @empty
            <flux:text>{{ __('No order activity yet.') }}</flux:text>
            @endforelse
        </div>
    </div>
</section>