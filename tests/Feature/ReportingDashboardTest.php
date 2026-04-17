<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportingDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_reports_page(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_reports_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Total orders')
            ->assertSee('Inventory Status')
            ->assertSee('Active revenue');
    }

    public function test_order_totals_and_revenue_are_calculated_correctly(): void
    {
        $confirmedProduct = Product::factory()->create([
            'price' => 20,
            'stock_quantity' => 20,
        ]);
        $partiallyCancelledProduct = Product::factory()->create([
            'price' => 15,
            'stock_quantity' => 20,
        ]);
        $cancelledProduct = Product::factory()->create([
            'price' => 9,
            'stock_quantity' => 20,
        ]);

        $confirmedOrder = Order::factory()->create();
        $confirmedOrder->addItem($confirmedProduct, 2);
        $confirmedOrder->confirm();

        $partiallyCancelledOrder = Order::factory()->create();
        $partiallyCancelledItem = $partiallyCancelledOrder->addItem($partiallyCancelledProduct, 4);
        $partiallyCancelledOrder->confirm();
        $partiallyCancelledOrder->cancelItems([$partiallyCancelledItem->id => 1]);

        $cancelledOrder = Order::factory()->create();
        $cancelledOrder->addItem($cancelledProduct, 3);
        $cancelledOrder->confirm();
        $cancelledOrder->cancel();

        Order::factory()->create();

        Livewire::test('pages::reports')
            ->assertSet('totalOrders', 4)
            ->assertSet('confirmedOrders', 1)
            ->assertSet('cancelledOrders', 1)
            ->assertSet('activeRevenue', '85.00')
            ->assertSee('$85.00');
    }

    public function test_inventory_overview_data_is_calculated_correctly(): void
    {
        Product::factory()->create([
            'name' => 'Out Box',
            'price' => 5,
            'stock_quantity' => 0,
        ]);
        Product::factory()->create([
            'name' => 'Low Box',
            'price' => 7.50,
            'stock_quantity' => 3,
        ]);
        Product::factory()->create([
            'name' => 'Healthy Box',
            'price' => 12,
            'stock_quantity' => 12,
        ]);

        Livewire::test('pages::reports')
            ->assertSet('totalProducts', 3)
            ->assertSet('totalStock', 15)
            ->assertSet('lowStockProducts', 2)
            ->assertSet('outOfStockProducts', 1)
            ->assertSee('Out Box')
            ->assertSee('Stock: 0')
            ->assertSee('Low Box')
            ->assertSee('Stock: 3')
            ->assertSee('Healthy Box')
            ->assertSee('Stock: 12');
    }
}
