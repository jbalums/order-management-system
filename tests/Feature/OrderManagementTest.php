<?php

namespace Tests\Feature;

use App\Models\InventoryLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_orders_page(): void
    {
        $this->get(route('orders.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_orders_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('orders.index'))
            ->assertOk()
            ->assertSee('Orders');
    }

    public function test_order_can_be_created_and_confirmed(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create([
            'price' => 25,
            'stock_quantity' => 6,
        ]);

        Livewire::test('pages::orders')
            ->set('items.0.product_id', $product->id)
            ->set('items.0.quantity', 2)
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::query()->firstOrFail();

        $this->assertSame(Order::STATUS_CONFIRMED, $order->status);
        $this->assertSame('50.00', $order->total_amount);
        $this->assertSame(4, $product->refresh()->stock_quantity);
        $this->assertDatabaseHas(InventoryLog::class, [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'change_type' => 'sale',
            'quantity_change' => -2,
        ]);
    }

    public function test_order_creation_rejects_insufficient_stock(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create([
            'stock_quantity' => 1,
        ]);

        Livewire::test('pages::orders')
            ->set('items.0.product_id', $product->id)
            ->set('items.0.quantity', 2)
            ->call('createOrder')
            ->assertHasErrors(['items']);

        $this->assertSame(1, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('inventory_logs', 0);
    }

    public function test_confirmed_order_can_be_cancelled_from_orders_page(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create([
            'price' => 10,
            'stock_quantity' => 5,
        ]);
        $order = Order::factory()->create();

        $order->addItem($product, 3);
        $order->confirm();

        Livewire::test('pages::orders')
            ->call('cancelOrder', $order->id)
            ->assertHasNoErrors();

        $this->assertSame(Order::STATUS_CANCELLED, $order->refresh()->status);
        $this->assertSame(5, $product->refresh()->stock_quantity);
        $this->assertDatabaseHas(InventoryLog::class, [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'change_type' => 'return',
            'quantity_change' => 3,
        ]);
    }
}
