<?php

namespace Tests\Feature;

use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\Product;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderInventoryFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_confirmation_reduces_stock_and_writes_inventory_log(): void
    {
        $product = Product::factory()->create([
            'name' => 'Desk',
            'price' => 12.50,
            'stock_quantity' => 10,
        ]);
        $order = Order::factory()->create();

        $order->addItem($product, 3);
        $order->confirm();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertSame(7, $product->refresh()->stock_quantity);
        $this->assertSame('37.50', $order->total_amount);

        $this->assertDatabaseHas(InventoryLog::class, [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'change_type' => 'sale',
            'quantity_change' => -3,
        ]);

        $this->assertDatabaseCount('inventory_logs', 1);
    }

    public function test_order_confirmation_rejects_insufficient_stock_without_changes(): void
    {
        $product = Product::factory()->create([
            'name' => 'Chair',
            'price' => 40,
            'stock_quantity' => 2,
        ]);
        $order = Order::factory()->create();

        $order->addItem($product, 3);

        $this->expectException(DomainException::class);

        try {
            $order->confirm();
        } finally {
            $this->assertSame(Order::STATUS_DRAFT, $order->refresh()->status);
            $this->assertSame(2, $product->refresh()->stock_quantity);
            $this->assertDatabaseCount('inventory_logs', 0);
        }
    }

    public function test_confirmed_order_cancellation_restores_stock_and_writes_inventory_log(): void
    {
        $product = Product::factory()->create([
            'price' => 15,
            'stock_quantity' => 8,
        ]);
        $order = Order::factory()->create();

        $order->addItem($product, 4);
        $order->confirm();
        $order->cancel();

        $this->assertSame(Order::STATUS_CANCELLED, $order->status);
        $this->assertSame(8, $product->refresh()->stock_quantity);

        $this->assertDatabaseHas(InventoryLog::class, [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'change_type' => 'return',
            'quantity_change' => 4,
        ]);

        $this->assertDatabaseCount('inventory_logs', 2);
    }

    public function test_draft_order_cancellation_is_prevented_without_inventory_change(): void
    {
        $order = Order::factory()->create();

        $this->expectException(DomainException::class);

        try {
            $order->cancel();
        } finally {
            $this->assertSame(Order::STATUS_DRAFT, $order->refresh()->status);
            $this->assertDatabaseCount('inventory_logs', 0);
        }
    }

    public function test_foundation_models_have_relationships_casts_and_defaults(): void
    {
        $product = Product::factory()->create([
            'price' => 19.99,
            'stock_quantity' => 4,
        ]);
        $order = Order::factory()->create();
        $item = $order->addItem($product, 2);
        $log = $product->inventoryLogs()->create([
            'order_id' => $order->id,
            'change_type' => 'adjustment',
            'quantity_change' => 3,
            'reason' => 'Cycle count',
        ]);

        $this->assertSame(Order::STATUS_DRAFT, $order->status);
        $this->assertNotEmpty($order->order_number);
        $this->assertSame('39.98', $order->refresh()->total_amount);
        $this->assertSame('19.99', $product->price);
        $this->assertSame(4, $product->stock_quantity);
        $this->assertTrue($order->items->contains($item));
        $this->assertTrue($product->orderItems->contains($item));
        $this->assertTrue($order->inventoryLogs->contains($log));
        $this->assertTrue($product->inventoryLogs->contains($log));
    }
}
