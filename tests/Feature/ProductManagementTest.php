<?php

namespace Tests\Feature;

use App\Models\InventoryLog;
use App\Models\OrderItem;
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

    public function test_product_form_modal_opens_for_creation(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::products')
            ->set('editing_product_id', 123)
            ->set('name', 'Existing name')
            ->set('description', 'Existing description')
            ->set('price', '15.00')
            ->set('stock_quantity', 7)
            ->call('openProductForm')
            ->assertSet('editing_product_id', null)
            ->assertSet('name', '')
            ->assertSet('description', '')
            ->assertSet('price', '')
            ->assertSet('stock_quantity', 0)
            ->assertDispatched('modal-show', name: 'product-form');
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
            ->assertHasNoErrors()
            ->assertDispatched('modal-close', name: 'product-form');

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

    public function test_product_validation_fails_for_invalid_data(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::products')
            ->set('name', '')
            ->set('price', '-1')
            ->set('stock_quantity', -2)
            ->call('createProduct')
            ->assertHasErrors([
                'name' => ['required'],
                'price' => ['min'],
                'stock_quantity' => ['min'],
            ]);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_product_can_be_updated(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create([
            'name' => 'Old Box',
            'description' => 'Old description',
            'price' => 5,
            'stock_quantity' => 3,
        ]);

        Livewire::test('pages::products')
            ->call('editProduct', $product->id)
            ->assertSet('editing_product_id', $product->id)
            ->assertDispatched('modal-show', name: 'product-form')
            ->set('name', 'Updated Box')
            ->set('description', 'Updated description')
            ->set('price', '12.75')
            ->set('stock_quantity', 8)
            ->call('updateProduct')
            ->assertHasNoErrors()
            ->assertDispatched('modal-close', name: 'product-form');

        $product->refresh();

        $this->assertSame('Updated Box', $product->name);
        $this->assertSame('Updated description', $product->description);
        $this->assertSame('12.75', $product->price);
        $this->assertSame(8, $product->stock_quantity);
    }

    public function test_product_can_be_deleted_when_not_used_by_orders(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create();
        InventoryLog::factory()->create(['product_id' => $product->id]);

        Livewire::test('pages::products')
            ->call('deleteProduct', $product->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing(Product::class, ['id' => $product->id]);
        $this->assertDatabaseMissing(InventoryLog::class, ['product_id' => $product->id]);
    }

    public function test_product_used_by_order_items_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create();

        OrderItem::factory()->create(['product_id' => $product->id]);

        Livewire::test('pages::products')
            ->call('deleteProduct', $product->id)
            ->assertHasErrors(['products']);

        $this->assertDatabaseHas(Product::class, ['id' => $product->id]);
    }

    public function test_adjust_stock_modal_opens_for_selected_product(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create([
            'name' => 'Shipping Box',
            'stock_quantity' => 5,
        ]);

        Livewire::test('pages::products')
            ->set('adjust_product_id', 999)
            ->set('adjust_product_name', 'Old product')
            ->set('adjust_quantity', 4)
            ->set('adjust_reason', 'Old reason')
            ->call('openAdjustStockForm', $product->id)
            ->assertSet('adjust_product_id', $product->id)
            ->assertSet('adjust_product_name', 'Shipping Box')
            ->assertSet('adjust_quantity', 0)
            ->assertSet('adjust_reason', '')
            ->assertDispatched('modal-show', name: 'adjust-form');
    }

    public function test_stock_can_be_adjusted_with_inventory_log(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create(['stock_quantity' => 5]);

        Livewire::test('pages::products')
            ->call('openAdjustStockForm', $product->id)
            ->set('adjust_quantity', 7)
            ->set('adjust_reason', 'Restock')
            ->call('adjustStock')
            ->assertHasNoErrors()
            ->assertDispatched('modal-close', name: 'adjust-form');

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
            ->call('openAdjustStockForm', $product->id)
            ->set('adjust_quantity', -6)
            ->set('adjust_reason', 'Correction')
            ->call('adjustStock')
            ->assertHasErrors(['adjust_quantity']);

        $this->assertSame(5, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('inventory_logs', 0);
    }
}
