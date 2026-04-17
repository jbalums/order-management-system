<?php

namespace Tests\Feature;

use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Models\Product;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_cancellation_restores_stock_updates_status_total_and_logs(): void
    {
        $firstProduct = Product::factory()->create([
            'price' => 10,
            'stock_quantity' => 10,
        ]);
        $secondProduct = Product::factory()->create([
            'price' => 7.50,
            'stock_quantity' => 8,
        ]);
        $order = Order::factory()->create();

        $firstItem = $order->addItem($firstProduct, 3);
        $secondItem = $order->addItem($secondProduct, 2);
        $order->confirm();
        $order->cancel();

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame('0.00', $order->total_amount);
        $this->assertSame(10, $firstProduct->refresh()->stock_quantity);
        $this->assertSame(8, $secondProduct->refresh()->stock_quantity);
        $this->assertSame(3, $firstItem->refresh()->cancelled_quantity);
        $this->assertSame(2, $secondItem->refresh()->cancelled_quantity);

        $this->assertDatabaseHas(InventoryLog::class, [
            'product_id' => $firstProduct->id,
            'order_id' => $order->id,
            'change_type' => 'return',
            'quantity_change' => 3,
            'reason' => "Order {$order->order_number} cancelled",
        ]);
        $this->assertDatabaseHas(InventoryLog::class, [
            'product_id' => $secondProduct->id,
            'order_id' => $order->id,
            'change_type' => 'return',
            'quantity_change' => 2,
            'reason' => "Order {$order->order_number} cancelled",
        ]);
        $this->assertDatabaseHas(OrderActivity::class, [
            'order_id' => $order->id,
            'activity_type' => Order::STATUS_CANCELLED,
            'description' => "Order {$order->order_number} cancelled",
            'status_from' => Order::STATUS_CONFIRMED,
            'status_to' => Order::STATUS_CANCELLED,
        ]);
    }

    public function test_partial_cancellation_restores_only_cancelled_quantities_updates_status_total_and_logs(): void
    {
        $firstProduct = Product::factory()->create([
            'price' => 10,
            'stock_quantity' => 10,
        ]);
        $secondProduct = Product::factory()->create([
            'price' => 7.50,
            'stock_quantity' => 8,
        ]);
        $order = Order::factory()->create();

        $firstItem = $order->addItem($firstProduct, 5);
        $secondItem = $order->addItem($secondProduct, 4);
        $order->confirm();
        $order->cancelItems([
            $firstItem->id => 2,
            $secondItem->id => 1,
        ]);

        $this->assertSame(Order::STATUS_PARTIALLY_CANCELLED, $order->status);
        $this->assertSame('52.50', $order->total_amount);
        $this->assertSame(7, $firstProduct->refresh()->stock_quantity);
        $this->assertSame(5, $secondProduct->refresh()->stock_quantity);
        $this->assertSame(2, $firstItem->refresh()->cancelled_quantity);
        $this->assertSame(1, $secondItem->refresh()->cancelled_quantity);

        $this->assertDatabaseHas(InventoryLog::class, [
            'product_id' => $firstProduct->id,
            'order_id' => $order->id,
            'change_type' => 'return',
            'quantity_change' => 2,
        ]);
        $this->assertDatabaseHas(InventoryLog::class, [
            'product_id' => $secondProduct->id,
            'order_id' => $order->id,
            'change_type' => 'return',
            'quantity_change' => 1,
        ]);
        $this->assertDatabaseHas(OrderActivity::class, [
            'order_id' => $order->id,
            'activity_type' => Order::STATUS_PARTIALLY_CANCELLED,
            'description' => "Order {$order->order_number} partially cancelled",
            'status_from' => Order::STATUS_CONFIRMED,
            'status_to' => Order::STATUS_PARTIALLY_CANCELLED,
        ]);
    }

    public function test_cancelling_remaining_quantity_after_partial_cancellation_fully_cancels_order(): void
    {
        $product = Product::factory()->create([
            'price' => 12,
            'stock_quantity' => 10,
        ]);
        $order = Order::factory()->create();
        $item = $order->addItem($product, 5);

        $order->confirm();
        $order->cancelItems([$item->id => 2]);
        $order->cancel();

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame('0.00', $order->total_amount);
        $this->assertSame(10, $product->refresh()->stock_quantity);
        $this->assertSame(5, $item->refresh()->cancelled_quantity);
    }

    public function test_over_cancellation_is_prevented_without_extra_restores(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 10,
        ]);
        $order = Order::factory()->create();
        $item = $order->addItem($product, 3);

        $order->confirm();
        $order->cancelItems([$item->id => 2]);

        $this->expectException(DomainException::class);

        try {
            $order->cancelItems([$item->id => 2]);
        } finally {
            $this->assertSame(Order::STATUS_PARTIALLY_CANCELLED, $order->refresh()->status);
            $this->assertSame(9, $product->refresh()->stock_quantity);
            $this->assertSame(2, $item->refresh()->cancelled_quantity);
            $this->assertDatabaseCount('inventory_logs', 2);
            $this->assertDatabaseCount('order_activities', 2);
        }
    }

    public function test_invalid_order_states_are_prevented(): void
    {
        $draftOrder = Order::factory()->create();

        try {
            $draftOrder->cancel();
            $this->fail('Draft orders should not be cancellable.');
        } catch (DomainException) {
            $this->assertSame(Order::STATUS_DRAFT, $draftOrder->refresh()->status);
        }

        $product = Product::factory()->create([
            'stock_quantity' => 5,
        ]);
        $order = Order::factory()->create();

        $order->addItem($product, 2);
        $order->confirm();
        $order->cancel();

        $this->expectException(DomainException::class);

        $order->cancel();
    }
}
