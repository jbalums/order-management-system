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

    public function test_order_can_be_created_as_draft_with_one_item(): void
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
        $item = $order->items()->firstOrFail();

        $this->assertSame(Order::STATUS_DRAFT, $order->status);
        $this->assertSame('50.00', $order->total_amount);
        $this->assertSame(6, $product->refresh()->stock_quantity);
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('25.00', $item->unit_price);
        $this->assertDatabaseCount('inventory_logs', 0);
    }

    public function test_order_can_be_created_with_multiple_items_and_total(): void
    {
        $this->actingAs(User::factory()->create());
        $firstProduct = Product::factory()->create(['price' => 10]);
        $secondProduct = Product::factory()->create(['price' => 7.50]);

        Livewire::test('pages::orders')
            ->set('items.0.product_id', $firstProduct->id)
            ->set('items.0.quantity', 2)
            ->call('addLine')
            ->set('items.1.product_id', $secondProduct->id)
            ->set('items.1.quantity', 3)
            ->call('createOrder')
            ->assertHasNoErrors();

        $order = Order::query()->with('items')->firstOrFail();

        $this->assertSame(Order::STATUS_DRAFT, $order->status);
        $this->assertSame('42.50', $order->total_amount);
        $this->assertCount(2, $order->items);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $firstProduct->id,
            'quantity' => 2,
            'unit_price' => 10,
        ]);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $secondProduct->id,
            'quantity' => 3,
            'unit_price' => 7.50,
        ]);
    }

    public function test_order_item_unit_price_is_captured_from_product_price_at_creation_time(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create(['price' => 19.99]);

        Livewire::test('pages::orders')
            ->set('items.0.product_id', $product->id)
            ->set('items.0.quantity', 1)
            ->call('createOrder')
            ->assertHasNoErrors();

        $product->update(['price' => 29.99]);

        $order = Order::query()->firstOrFail();

        $this->assertSame('19.99', $order->items()->firstOrFail()->unit_price);
        $this->assertSame('19.99', $order->total_amount);
    }

    public function test_invalid_quantities_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create();

        Livewire::test('pages::orders')
            ->set('items.0.product_id', $product->id)
            ->set('items.0.quantity', 0)
            ->call('createOrder')
            ->assertHasErrors(['items.0.quantity' => ['min']]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_invalid_products_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::orders')
            ->set('items.0.product_id', 999)
            ->set('items.0.quantity', 1)
            ->call('createOrder')
            ->assertHasErrors(['items.0.product_id' => ['exists']]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_duplicate_products_in_order_form_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create();

        Livewire::test('pages::orders')
            ->set('items.0.product_id', $product->id)
            ->set('items.0.quantity', 1)
            ->call('addLine')
            ->set('items.1.product_id', $product->id)
            ->set('items.1.quantity', 2)
            ->call('createOrder')
            ->assertHasErrors(['items.0.product_id' => ['distinct']]);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
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
