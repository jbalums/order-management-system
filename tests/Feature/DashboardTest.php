<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_shows_basic_reporting_metrics(): void
    {
        Product::factory()->create(['stock_quantity' => 3]);
        $product = Product::factory()->create([
            'name' => 'Notebook',
            'price' => 10,
            'stock_quantity' => 10,
        ]);
        $order = Order::factory()->create();

        $order->addItem($product, 2);
        $order->confirm();

        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total products')
            ->assertSee('Low stock')
            ->assertSee('Confirmed orders')
            ->assertSee('$20.00')
            ->assertSee('Notebook')
            ->assertSee($order->order_number);
    }
}
