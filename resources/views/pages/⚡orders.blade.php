<?php

use App\Models\Order;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    public ?int $cancelling_order_id = null;

    public string $cancelling_order_number = '';

    /**
     * @var array<int, array{order_item_id: int, product_name: string, ordered_quantity: int, cancelled_quantity: int, remaining_quantity: int, cancel_quantity: int}>
     */
    public array $cancellation_items = [];

    public function openOrderForm(): void
    {
        $this->resetOrderForm();

        Flux::modal('order-form')->show();
    }

    public function closeOrderForm(): void
    {
        $this->resetOrderForm();

        Flux::modal('order-form')->close();
    }

    public function dismissOrderForm(): void
    {
        $this->resetOrderForm();
    }

    public function openCancellationForm(int $orderId): void
    {
        $this->resetCancellationForm();

        $order = Order::query()
            ->with('items.product')
            ->findOrFail($orderId);

        if (! in_array($order->status, [Order::STATUS_CONFIRMED, Order::STATUS_PARTIALLY_CANCELLED], true)) {
            $this->addError('orders', __('Only confirmed orders can be cancelled.'));

            return;
        }

        $this->cancelling_order_id = $order->id;
        $this->cancelling_order_number = $order->order_number;
        $this->cancellation_items = $order->items
            ->filter(fn($item): bool => $item->remainingQuantity() > 0)
            ->map(fn($item): array => [
                'order_item_id' => $item->id,
                'product_name' => $item->product->name,
                'ordered_quantity' => $item->quantity,
                'cancelled_quantity' => $item->cancelled_quantity,
                'remaining_quantity' => $item->remainingQuantity(),
                'cancel_quantity' => 0,
            ])
            ->values()
            ->all();

        Flux::modal('cancel-order-form')->show();
    }

    public function closeCancellationForm(): void
    {
        $this->resetCancellationForm();

        Flux::modal('cancel-order-form')->close();
    }

    public function dismissCancellationForm(): void
    {
        $this->resetCancellationForm();
    }

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

        $this->resetOrderForm();

        Flux::modal('order-form')->close();
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

    public function cancelSelectedItems(): void
    {
        $validated = $this->validate([
            'cancelling_order_id' => ['required', 'integer', 'exists:orders,id'],
            'cancellation_items' => ['required', 'array'],
            'cancellation_items.*.order_item_id' => ['required', 'integer', 'exists:order_items,id'],
            'cancellation_items.*.cancel_quantity' => ['required', 'integer', 'min:0'],
        ]);

        $itemQuantities = collect($validated['cancellation_items'])
            ->mapWithKeys(fn(array $item): array => [(int) $item['order_item_id'] => (int) $item['cancel_quantity']])
            ->filter(fn(int $quantity): bool => $quantity > 0)
            ->all();

        if ($itemQuantities === []) {
            throw ValidationException::withMessages([
                'cancellation_items' => __('Choose at least one quantity to cancel.'),
            ]);
        }

        try {
            Order::query()
                ->findOrFail($validated['cancelling_order_id'])
                ->cancelItems($itemQuantities);
        } catch (ModelNotFoundException) {
            $this->addError('cancellation_items', __('Order could not be found.'));

            return;
        } catch (\DomainException $exception) {
            $this->addError('cancellation_items', $exception->getMessage());

            return;
        }

        $this->resetCancellationForm();

        Flux::modal('cancel-order-form')->close();
        Flux::toast(variant: 'success', text: __('Order partially cancelled.'));
    }

    public function confirmOrder(int $orderId): void
    {
        try {
            Order::query()->findOrFail($orderId)->confirm();
        } catch (\DomainException $exception) {
            $this->addError('orders', $exception->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Order confirmed.'));
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
            ->with(['activities', 'items.product'])
            ->latest()
            ->get();
    }

    private function resetOrderForm(): void
    {
        $this->items = [
            ['product_id' => '', 'quantity' => 1],
        ];

        $this->resetValidation();
    }

    private function resetCancellationForm(): void
    {
        $this->reset('cancelling_order_id', 'cancelling_order_number', 'cancellation_items');
        $this->resetValidation(['cancelling_order_id', 'cancellation_items']);
    }
};
?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Orders') }}</flux:heading>
            <flux:subheading>{{ __('Create draft orders with product quantities and totals.') }}</flux:subheading>
        </div>

        <flux:button type="button" variant="primary" icon="plus" wire:click="openOrderForm">
            {{ __('New order') }}
        </flux:button>
    </div>

    <flux:modal name="order-form" class="w-full max-w-3xl" @close="dismissOrderForm">
        <form wire:submit="createOrder" class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-2">
                    <flux:heading size="lg" level="2">{{ __('New order') }}</flux:heading>
                    <flux:subheading>{{ __('Build a draft order from one or more product line items.') }}</flux:subheading>
                </div>

                <flux:button type="button" variant="filled" icon="plus" wire:click="addLine">
                    {{ __('Add item') }}
                </flux:button>
            </div>

            @error('items')
            <flux:callout variant="danger" icon="x-circle" heading="{{ $message }}" />
            @enderror

            <div class="max-h-[65vh] space-y-3 overflow-y-auto pr-1">
                @foreach ($items as $index => $item)
                <div wire:key="order-item-{{ $index }}" class="grid gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700 sm:grid-cols-[1fr_8rem_auto] sm:items-end">
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

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:button type="button" variant="filled" wire:click="closeOrderForm">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ __('Create order') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="cancel-order-form" class="w-full max-w-2xl" @close="dismissCancellationForm">
        <form wire:submit="cancelSelectedItems" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg" level="2">{{ __('Partial cancellation') }}</flux:heading>
                <flux:subheading>
                    {{ __('Choose quantities to cancel for :order.', ['order' => $cancelling_order_number]) }}
                </flux:subheading>
            </div>

            @error('cancellation_items')
            <flux:callout variant="danger" icon="x-circle" heading="{{ $message }}" />
            @enderror

            <div class="space-y-3">
                @foreach ($cancellation_items as $index => $item)
                <div wire:key="cancellation-item-{{ $item['order_item_id'] }}" class="grid gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700 sm:grid-cols-[1fr_10rem] sm:items-end">
                    <div>
                        <div class="font-medium">{{ $item['product_name'] }}</div>
                        <div class="text-sm text-neutral-600 dark:text-neutral-300">
                            {{ __('Ordered: :ordered | Already cancelled: :cancelled | Remaining: :remaining', [
                                    'ordered' => $item['ordered_quantity'],
                                    'cancelled' => $item['cancelled_quantity'],
                                    'remaining' => $item['remaining_quantity'],
                                ]) }}
                        </div>
                    </div>

                    <flux:input
                        wire:model="cancellation_items.{{ $index }}.cancel_quantity"
                        :label="__('Cancel quantity')"
                        type="number"
                        min="0"
                        max="{{ $item['remaining_quantity'] }}"
                        step="1"
                        required />
                </div>
                @endforeach
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:button type="button" variant="filled" wire:click="closeCancellationForm">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button type="submit" variant="danger">
                    {{ __('Cancel selected quantities') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

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

                    @if ($order->status === Order::STATUS_DRAFT)
                    <flux:button type="button" variant="primary" icon="check" wire:click="confirmOrder({{ $order->id }})">
                        {{ __('Confirm') }}
                    </flux:button>
                    @endif

                    @if (in_array($order->status, [Order::STATUS_CONFIRMED, Order::STATUS_PARTIALLY_CANCELLED], true))
                    <flux:button type="button" variant="filled" icon="minus-circle" wire:click="openCancellationForm({{ $order->id }})">
                        {{ __('Partial') }}
                    </flux:button>

                    <flux:button type="button" variant="danger" icon="x-mark" wire:click="cancelOrder({{ $order->id }})">
                        {{ __('Cancel') }}
                    </flux:button>
                    @endif
                </div>
            </div>

            <div class="mt-4 space-y-1 text-sm text-neutral-600 dark:text-neutral-300">
                @foreach ($order->items as $item)
                <div wire:key="order-{{ $order->id }}-item-{{ $item->id }}">
                    {{ $item->product->name }} x {{ $item->quantity }} @ ₱{{ $item->unit_price }}
                </div>
                @endforeach

                @foreach ($order->activities as $activity)
                <div wire:key="order-{{ $order->id }}-activity-{{ $activity->id }}" class="text-neutral-500 dark:text-neutral-400">
                    {{ $activity->description }}
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