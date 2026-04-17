<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_logs_page(): void
    {
        $this->get(route('logs.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_logs_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('logs.index'))
            ->assertOk()
            ->assertSee('Logs')
            ->assertSee('Inventory Activity')
            ->assertSee('Order Activity');
    }

    public function test_inventory_events_are_displayed(): void
    {
        $product = Product::factory()->create([
            'name' => 'Shipping Box',
            'stock_quantity' => 10,
        ]);
        $order = Order::factory()->create();

        $product->inventoryLogs()->create([
            'change_type' => 'initial',
            'quantity_change' => 10,
            'reason' => 'Initial stock',
        ]);
        $product->inventoryLogs()->create([
            'change_type' => 'adjustment',
            'quantity_change' => -2,
            'reason' => 'Damaged stock',
        ]);
        $product->inventoryLogs()->create([
            'order_id' => $order->id,
            'change_type' => 'return',
            'quantity_change' => 1,
            'reason' => "Order {$order->order_number} cancelled",
        ]);

        Livewire::test('pages::logs')
            ->assertSee('Shipping Box')
            ->assertSee('Addition')
            ->assertSee('+10')
            ->assertSee('Initial stock')
            ->assertSee('Deduction')
            ->assertSee('-2')
            ->assertSee('Damaged stock')
            ->assertSee('Restore')
            ->assertSee('+1')
            ->assertSee($order->order_number);
    }

    public function test_order_events_are_displayed(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 10,
        ]);
        $order = Order::factory()->create([
            'order_number' => 'ORD-TEST-001',
        ]);

        $order->addItem($product, 2);
        $order->confirm();
        $order->cancel();

        Livewire::test('pages::logs')
            ->assertSee('Created')
            ->assertSee('Order ORD-TEST-001 created')
            ->assertSee('Confirmed')
            ->assertSee('Order ORD-TEST-001 confirmed')
            ->assertSee('Cancelled')
            ->assertSee('Order ORD-TEST-001 cancelled')
            ->assertSee('Status: Cancelled');
    }
}
