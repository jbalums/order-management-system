<?php

use App\Models\Order;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Orders')] class extends Component {
    /**
     * @var array<int, array{product_id: string, quantity: int}>
     */
    public array $items = [
        ['product_id' => '', 'quantity' => 1],
    ];

    public function addLine(): void
    {
        $this->items[] = ['product_id' => '', 'quantity' => 1];
    }

    public function removeLine(int $index): void
    {
        unset($this->items[$index]);

        $this->items = array_values($this->items);
    }

    public function createOrder(): void
    {
        $validated = $this->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($validated): void {
            $order = Order::create();

            foreach ($validated['items'] as $item) {
                $product = Product::query()->findOrFail($item['product_id']);
                $order->addItem($product, (int) $item['quantity']);
            }
        });

        $this->items = [
            ['product_id' => '', 'quantity' => 1],
        ];

        Flux::toast(variant: 'success', text: __('Order created.'));
    }

    public function cancelOrder(int $orderId): void
    {
        try {
            Order::query()->findOrFail($orderId)->cancel();
        } catch (\DomainException $exception) {
            $this->addError('orders', $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Order cancelled.'));
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        return Product::query()
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Order>
     */
    #[Computed]
    public function orders(): Collection
    {
        return Order::query()
            ->with('items.product')
            ->latest()
            ->get();
    }
};
?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Orders') }}</flux:heading>
        <flux:subheading>{{ __('Create draft orders with product quantities and totals.') }}</flux:subheading>
    </div>

    <form wire:submit="createOrder" class="space-y-4 rounded-xl border border-neutral-200 p-6 dark:border-neutral-700">
        <div class="flex items-center justify-between gap-4">
            <flux:heading level="2">{{ __('New order') }}</flux:heading>
            <flux:button type="button" variant="filled" icon="plus" wire:click="addLine">{{ __('Add item') }}</flux:button>
        </div>

        @error('items')
            <flux:callout variant="danger" icon="x-circle" heading="{{ $message }}" />
        @enderror

        <div class="space-y-3">
            @foreach ($items as $index => $item)
                <div wire:key="order-item-{{ $index }}" class="grid gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 sm:grid-cols-[1fr_8rem_auto] sm:items-end">
                    <flux:select wire:model="items.{{ $index }}.product_id" :label="__('Product')" placeholder="{{ __('Choose a product') }}">
                        @foreach ($this->products as $product)
                            <flux:select.option :value="$product->id" wire:key="order-product-{{ $index }}-{{ $product->id }}">
                                {{ $product->name }} ({{ __('stock: :count', ['count' => $product->stock_quantity]) }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="items.{{ $index }}.quantity" :label="__('Quantity')" type="number" min="1" step="1" required />

                    <flux:button type="button" variant="ghost" icon="trash" wire:click="removeLine({{ $index }})" :disabled="count($items) === 1">
                        {{ __('Remove') }}
                    </flux:button>
                </div>
            @endforeach
        </div>

        <flux:button type="submit" variant="primary">{{ __('Create order') }}</flux:button>
    </form>

    <div class="space-y-3">
        <flux:heading level="2">{{ __('Recent orders') }}</flux:heading>

        @error('orders')
            <flux:callout variant="danger" icon="x-circle" heading="{{ $message }}" />
        @enderror

        @forelse ($this->orders as $order)
            <div wire:key="order-{{ $order->id }}" class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <flux:heading level="3">{{ $order->order_number }}</flux:heading>
                        <flux:text>{{ __('Total: $:amount', ['amount' => $order->total_amount]) }}</flux:text>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <flux:badge>{{ ucfirst($order->status) }}</flux:badge>

                        @if ($order->status !== Order::STATUS_CANCELLED)
                            <flux:button type="button" variant="danger" icon="x-mark" wire:click="cancelOrder({{ $order->id }})">
                                {{ __('Cancel') }}
                            </flux:button>
                        @endif
                    </div>
                </div>

                <div class="mt-4 space-y-1 text-sm text-neutral-600 dark:text-neutral-300">
                    @foreach ($order->items as $item)
                        <div wire:key="order-{{ $order->id }}-item-{{ $item->id }}">
                            {{ $item->product->name }} x {{ $item->quantity }} @ ${{ $item->unit_price }}
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-neutral-300 p-6 text-sm text-neutral-600 dark:border-neutral-700 dark:text-neutral-300">
                {{ __('No orders yet.') }}
            </div>
        @endforelse
    </div>
</section>
