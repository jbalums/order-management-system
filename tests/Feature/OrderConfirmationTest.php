<?php

namespace Tests\Feature;

use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\OrderActivity;
use App\Models\Product;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_order_can_be_confirmed_from_orders_page(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create([
            'price' => 15,
            'stock_quantity' => 8,
        ]);
        $order = Order::factory()->create();

        $order->addItem($product, 3);

        Livewire::test('pages::orders')
            ->assertSee('Confirm')
            ->call('confirmOrder', $order->id)
            ->assertHasNoErrors()
            ->assertSee("Order {$order->order_number} confirmed");

        $this->assertSame(Order::STATUS_CONFIRMED, $order->refresh()->status);
        $this->assertSame(5, $product->refresh()->stock_quantity);
    }

    public function test_confirming_order_deducts_stock_and_writes_logs(): void
    {
        $firstProduct = Product::factory()->create([
            'price' => 10,
            'stock_quantity' => 10,
        ]);
        $secondProduct = Product::factory()->create([
            'price' => 7.50,
            'stock_quantity' => 6,
        ]);
        $order = Order::factory()->create();

        $order->addItem($firstProduct, 2);
        $order->addItem($secondProduct, 3);
        $order->confirm();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertSame(8, $firstProduct->refresh()->stock_quantity);
        $this->assertSame(3, $secondProduct->refresh()->stock_quantity);
        $this->assertSame('42.50', $order->total_amount);

        $this->assertDatabaseHas(InventoryLog::class, [
            'product_id' => $firstProduct->id,
            'order_id' => $order->id,
            'change_type' => 'sale',
            'quantity_change' => -2,
            'reason' => "Order {$order->order_number} confirmed",
        ]);
        $this->assertDatabaseHas(InventoryLog::class, [
            'product_id' => $secondProduct->id,
            'order_id' => $order->id,
            'change_type' => 'sale',
            'quantity_change' => -3,
            'reason' => "Order {$order->order_number} confirmed",
        ]);
        $this->assertDatabaseHas(OrderActivity::class, [
            'order_id' => $order->id,
            'activity_type' => 'confirmed',
            'description' => "Order {$order->order_number} confirmed",
            'status_from' => Order::STATUS_DRAFT,
            'status_to' => Order::STATUS_CONFIRMED,
        ]);
    }

    public function test_confirmation_fails_when_stock_is_insufficient_without_partial_deductions(): void
    {
        $firstProduct = Product::factory()->create([
            'stock_quantity' => 5,
        ]);
        $secondProduct = Product::factory()->create([
            'stock_quantity' => 1,
        ]);
        $order = Order::factory()->create();

        $order->addItem($firstProduct, 3);
        $order->addItem($secondProduct, 2);

        $this->expectException(DomainException::class);

        try {
            $order->confirm();
        } finally {
            $this->assertSame(Order::STATUS_DRAFT, $order->refresh()->status);
            $this->assertSame(5, $firstProduct->refresh()->stock_quantity);
            $this->assertSame(1, $secondProduct->refresh()->stock_quantity);
            $this->assertDatabaseCount('inventory_logs', 0);
            $this->assertDatabaseCount('order_activities', 0);
        }
    }

    public function test_confirming_twice_is_prevented(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 10,
        ]);
        $order = Order::factory()->create();

        $order->addItem($product, 4);
        $order->confirm();

        $this->expectException(DomainException::class);

        try {
            $order->confirm();
        } finally {
            $this->assertSame(Order::STATUS_CONFIRMED, $order->refresh()->status);
            $this->assertSame(6, $product->refresh()->stock_quantity);
            $this->assertDatabaseCount('inventory_logs', 1);
            $this->assertDatabaseCount('order_activities', 1);
        }
    }

    public function test_confirming_cancelled_order_is_prevented(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 10,
        ]);
        $order = Order::factory()->create();

        $order->addItem($product, 2);
        $order->confirm();
        $order->cancel();

        $this->expectException(DomainException::class);

        try {
            $order->confirm();
        } finally {
            $this->assertSame(Order::STATUS_CANCELLED, $order->refresh()->status);
            $this->assertSame(10, $product->refresh()->stock_quantity);
            $this->assertDatabaseCount('inventory_logs', 2);
            $this->assertDatabaseCount('order_activities', 2);
        }
    }
}
