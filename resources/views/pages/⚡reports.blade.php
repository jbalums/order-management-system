<?php

use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Reports')] class extends Component {
    #[Computed]
    public function totalOrders(): int
    {
        return Order::query()->count();
    }

    #[Computed]
    public function confirmedOrders(): int
    {
        return Order::query()
            ->where('status', Order::STATUS_CONFIRMED)
            ->count();
    }

    #[Computed]
    public function cancelledOrders(): int
    {
        return Order::query()
            ->where('status', Order::STATUS_CANCELLED)
            ->count();
    }

    #[Computed]
    public function activeRevenue(): string
    {
        $revenue = Order::query()
            ->whereIn('status', [Order::STATUS_CONFIRMED, Order::STATUS_PARTIALLY_CANCELLED])
            ->sum('total_amount');

        return number_format((float) $revenue, 2);
    }

    #[Computed]
    public function totalProducts(): int
    {
        return Product::query()->count();
    }

    #[Computed]
    public function totalStock(): int
    {
        return (int) Product::query()->sum('stock_quantity');
    }

    #[Computed]
    public function lowStockProducts(): int
    {
        return Product::query()
            ->where('stock_quantity', '<=', 5)
            ->count();
    }

    #[Computed]
    public function outOfStockProducts(): int
    {
        return Product::query()
            ->where('stock_quantity', 0)
            ->count();
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function inventoryOverview(): Collection
    {
        return Product::query()
            ->orderBy('stock_quantity')
            ->orderBy('name')
            ->take(10)
            ->get();
    }
};
?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Reports') }}</flux:heading>
        <flux:subheading>{{ __('Simple order, revenue, and inventory reporting.') }}</flux:subheading>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>{{ __('Total orders') }}</flux:text>
            <div class="mt-2 text-3xl font-semibold">{{ $this->totalOrders }}</div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>{{ __('Confirmed orders') }}</flux:text>
            <div class="mt-2 text-3xl font-semibold">{{ $this->confirmedOrders }}</div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>{{ __('Cancelled orders') }}</flux:text>
            <div class="mt-2 text-3xl font-semibold">{{ $this->cancelledOrders }}</div>
        </div>

        <div class="rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:text>{{ __('Active revenue') }}</flux:text>
            <div class="mt-2 text-3xl font-semibold">₱{{ $this->activeRevenue }}</div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-4 rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:heading level="2">{{ __('Inventory Status') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <flux:text>{{ __('Total products') }}</flux:text>
                    <div class="mt-1 text-2xl font-semibold">{{ $this->totalProducts }}</div>
                </div>

                <div>
                    <flux:text>{{ __('Current stock units') }}</flux:text>
                    <div class="mt-1 text-2xl font-semibold">{{ $this->totalStock }}</div>
                </div>

                <div>
                    <flux:text>{{ __('Low stock products') }}</flux:text>
                    <div class="mt-1 text-2xl font-semibold">{{ $this->lowStockProducts }}</div>
                </div>

                <div>
                    <flux:text>{{ __('Out of stock products') }}</flux:text>
                    <div class="mt-1 text-2xl font-semibold">{{ $this->outOfStockProducts }}</div>
                </div>
            </div>
        </div>

        <div class="space-y-3 rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:heading level="2">{{ __('Current Stock Overview') }}</flux:heading>

            @forelse ($this->inventoryOverview as $product)
            <div wire:key="report-product-{{ $product->id }}" class="flex items-center justify-between gap-4 border-t border-neutral-200 pt-3 text-sm dark:border-neutral-700">
                <div>
                    <div class="font-medium">{{ $product->name }}</div>
                    <div class="text-neutral-600 dark:text-neutral-300">₱{{ $product->price }}</div>
                </div>

                <flux:badge>{{ __('Stock: :count', ['count' => $product->stock_quantity]) }}</flux:badge>
            </div>
            @empty
            <flux:text>{{ __('No products yet.') }}</flux:text>
            @endforelse
        </div>
    </div>
</section>