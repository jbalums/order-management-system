<?php

namespace Tests\Feature;

use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_products_page(): void
    {
        $this->get(route('products.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_products_page(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('products.index'))
            ->assertOk()
            ->assertSee('Products');
    }

    public function test_product_can_be_created_with_initial_inventory_log(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::products')
            ->set('name', 'Shipping Box')
            ->set('description', 'Medium box')
            ->set('price', '9.99')
            ->set('stock_quantity', 12)
            ->call('createProduct')
            ->assertHasNoErrors();

        $product = Product::query()->where('name', 'Shipping Box')->firstOrFail();

        $this->assertSame('9.99', $product->price);
        $this->assertSame(12, $product->stock_quantity);
        $this->assertDatabaseHas(InventoryLog::class, [
            'product_id' => $product->id,
            'change_type' => 'initial',
            'quantity_change' => 12,
            'reason' => 'Initial stock',
        ]);
    }

    public function test_stock_can_be_adjusted_with_inventory_log(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create(['stock_quantity' => 5]);

        Livewire::test('pages::products')
            ->set('adjust_product_id', $product->id)
            ->set('adjust_quantity', 7)
            ->set('adjust_reason', 'Restock')
            ->call('adjustStock')
            ->assertHasNoErrors();

        $this->assertSame(12, $product->refresh()->stock_quantity);
        $this->assertDatabaseHas(InventoryLog::class, [
            'product_id' => $product->id,
            'change_type' => 'adjustment',
            'quantity_change' => 7,
            'reason' => 'Restock',
        ]);
    }

    public function test_stock_adjustment_cannot_reduce_stock_below_zero(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create(['stock_quantity' => 5]);

        Livewire::test('pages::products')
            ->set('adjust_product_id', $product->id)
            ->set('adjust_quantity', -6)
            ->set('adjust_reason', 'Correction')
            ->call('adjustStock')
            ->assertHasErrors(['adjust_quantity']);

        $this->assertSame(5, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_logs', 0);
    }
}
