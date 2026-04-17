<?php

use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderActivity;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Logs')] class extends Component {
    /**
     * @return Collection<int, InventoryLog>
     */
    #[Computed]
    public function inventoryLogs(): Collection
    {
        return InventoryLog::query()
            ->with(['product', 'order'])
            ->latest()
            ->take(25)
            ->get();
    }

    /**
     * @return Collection<int, array{key: string, type: string, description: string, status: string, occurred_at: \Illuminate\Support\Carbon|null}>
     */
    #[Computed]
    public function orderEvents(): Collection
    {
        $createdEvents = Order::query()
            ->latest()
            ->take(25)
            ->get()
            ->map(fn (Order $order): array => [
                'key' => "order-created-{$order->id}",
                'type' => __('Created'),
                'description' => __('Order :order created', ['order' => $order->order_number]),
                'status' => Str::of($order->status)->replace('_', ' ')->title()->toString(),
                'occurred_at' => $order->created_at,
            ]);

        $activityEvents = OrderActivity::query()
            ->with('order')
            ->latest()
            ->take(25)
            ->get()
            ->map(fn (OrderActivity $activity): array => [
                'key' => "order-activity-{$activity->id}",
                'type' => Str::of($activity->activity_type)->replace('_', ' ')->title()->toString(),
                'description' => $activity->description,
                'status' => Str::of($activity->status_to)->replace('_', ' ')->title()->toString(),
                'occurred_at' => $activity->created_at,
            ]);

        return $createdEvents
            ->concat($activityEvents)
            ->sortByDesc('occurred_at')
            ->take(25)
            ->values();
    }

    public function inventoryActivityLabel(InventoryLog $log): string
    {
        if ($log->change_type === 'return') {
            return __('Restore');
        }

        if ($log->quantity_change < 0) {
            return __('Deduction');
        }

        return __('Addition');
    }
};
?>

<section class="w-full space-y-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Logs') }}</flux:heading>
        <flux:subheading>{{ __('Recent inventory and order activity history.') }}</flux:subheading>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="space-y-3 rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:heading level="2">{{ __('Inventory Activity') }}</flux:heading>

            @forelse ($this->inventoryLogs as $log)
                <div wire:key="inventory-log-{{ $log->id }}" class="border-t border-neutral-200 pt-3 text-sm dark:border-neutral-700">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="font-medium">{{ $log->product->name }}</div>
                            <div class="text-neutral-600 dark:text-neutral-300">
                                {{ $this->inventoryActivityLabel($log) }}:
                                {{ $log->quantity_change > 0 ? '+' : '' }}{{ $log->quantity_change }}
                                @if ($log->order)
                                    - {{ $log->order->order_number }}
                                @endif
                            </div>
                            <div class="text-neutral-500 dark:text-neutral-400">{{ $log->reason }}</div>
                        </div>

                        <div class="text-neutral-500 dark:text-neutral-400">
                            {{ $log->created_at?->diffForHumans() }}
                        </div>
                    </div>
                </div>
            @empty
                <flux:text>{{ __('No inventory activity yet.') }}</flux:text>
            @endforelse
        </div>

        <div class="space-y-3 rounded-xl border border-neutral-200 p-5 dark:border-neutral-700">
            <flux:heading level="2">{{ __('Order Activity') }}</flux:heading>

            @forelse ($this->orderEvents as $event)
                <div wire:key="{{ $event['key'] }}" class="border-t border-neutral-200 pt-3 text-sm dark:border-neutral-700">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="font-medium">{{ $event['type'] }}</div>
                            <div class="text-neutral-600 dark:text-neutral-300">{{ $event['description'] }}</div>
                            <div class="text-neutral-500 dark:text-neutral-400">{{ __('Status: :status', ['status' => $event['status']]) }}</div>
                        </div>

                        <div class="text-neutral-500 dark:text-neutral-400">
                            {{ $event['occurred_at']?->diffForHumans() }}
                        </div>
                    </div>
                </div>
            @empty
                <flux:text>{{ __('No order activity yet.') }}</flux:text>
            @endforelse
        </div>
    </div>
</section>
