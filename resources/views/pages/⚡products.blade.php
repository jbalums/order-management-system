<?php

use App\Models\Product;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Products')] class extends Component {
    public ?int $editing_product_id = null;

    public string $name = '';

    public string $description = '';

    public string $price = '';

    public int $stock_quantity = 0;

    public ?int $adjust_product_id = null;

    public string $adjust_product_name = '';

    public int $adjust_quantity = 0;

    public string $adjust_reason = '';

    public function openProductForm(): void
    {
        $this->resetProductForm();

        Flux::modal('product-form')->show();
    }

    public function openAdjustStockForm(int $productId): void
    {
        $this->resetAdjustStockForm();

        $product = Product::query()->findOrFail($productId);

        $this->adjust_product_id = $product->id;
        $this->adjust_product_name = $product->name;

        Flux::modal('adjust-form')->show();
    }

    public function createProduct(): void
    {
        $validated = $this->validate($this->productRules());

        $product = Product::create($validated);

        if ($product->stock_quantity > 0) {
            $product->inventoryLogs()->create([
                'change_type' => 'initial',
                'quantity_change' => $product->stock_quantity,
                'reason' => 'Initial stock',
            ]);
        }

        $this->resetProductForm();

        Flux::modal('product-form')->close();
        Flux::toast(variant: 'success', text: __('Product created.'));
    }

    public function editProduct(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);

        $this->editing_product_id = $product->id;
        $this->name = $product->name;
        $this->description = $product->description ?? '';
        $this->price = $product->price;
        $this->stock_quantity = $product->stock_quantity;

        $this->resetValidation(['name', 'description', 'price', 'stock_quantity']);

        Flux::modal('product-form')->show();
    }

    public function updateProduct(): void
    {
        $validated = $this->validate($this->productRules());

        Product::query()
            ->findOrFail($this->editing_product_id)
            ->update($validated);

        $this->resetProductForm();

        Flux::modal('product-form')->close();
        Flux::toast(variant: 'success', text: __('Product updated.'));
    }

    public function cancelEdit(): void
    {
        $this->resetProductForm();

        Flux::modal('product-form')->close();
    }

    public function dismissProductForm(): void
    {
        $this->resetProductForm();
    }

    public function closeAdjustStockForm(): void
    {
        $this->resetAdjustStockForm();

        Flux::modal('adjust-form')->close();
    }

    public function dismissAdjustStockForm(): void
    {
        $this->resetAdjustStockForm();
    }

    public function deleteProduct(int $productId): void
    {
        $product = Product::query()
            ->withCount('orderItems')
            ->findOrFail($productId);

        if ($product->order_items_count > 0) {
            $this->addError('products', __('Products used in orders cannot be deleted.'));

            return;
        }

        DB::transaction(function () use ($product): void {
            $product->inventoryLogs()->delete();
            $product->delete();
        });

        if ($this->editing_product_id === $product->id) {
            $this->resetProductForm();
        }

        Flux::toast(variant: 'success', text: __('Product deleted.'));
    }

    public function adjustStock(): void
    {
        $validated = $this->validate([
            'adjust_product_id' => ['required', 'integer', 'exists:products,id'],
            'adjust_quantity' => ['required', 'integer', 'not_in:0'],
            'adjust_reason' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated): void {
            $product = Product::query()
                ->whereKey($validated['adjust_product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $newQuantity = $product->stock_quantity + $validated['adjust_quantity'];

            if ($newQuantity < 0) {
                throw ValidationException::withMessages([
                    'adjust_quantity' => __('Stock cannot go below zero.'),
                ]);
            }

            $product->forceFill(['stock_quantity' => $newQuantity])->save();

            $product->inventoryLogs()->create([
                'change_type' => 'adjustment',
                'quantity_change' => $validated['adjust_quantity'],
                'reason' => $validated['adjust_reason'],
            ]);
        });

        $this->resetAdjustStockForm();

        Flux::modal('adjust-form')->close();
        Flux::toast(variant: 'success', text: __('Stock updated.'));
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        return Product::query()
            ->withCount(['inventoryLogs', 'orderItems'])
            ->latest()
            ->get();
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function productRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
        ];
    }

    private function resetProductForm(): void
    {
        $this->reset('editing_product_id', 'name', 'description', 'price', 'stock_quantity');
        $this->resetValidation(['name', 'description', 'price', 'stock_quantity']);
    }

    private function resetAdjustStockForm(): void
    {
        $this->reset('adjust_product_id', 'adjust_product_name', 'adjust_quantity', 'adjust_reason');
        $this->resetValidation(['adjust_product_id', 'adjust_quantity', 'adjust_reason']);
    }
};
?>

<section class="w-full space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl" level="1">{{ __('Products') }}</flux:heading>
            <flux:subheading>{{ __('Manage products and inventory levels.') }}</flux:subheading>
        </div>
        <div>
            <flux:button type="button" variant="primary" icon="plus" wire:click="openProductForm">
                {{ __('Add product') }}
            </flux:button>
        </div>
    </div>

    <flux:modal name="product-form" class="w-full max-w-2xl" @close="dismissProductForm">
        <form wire:submit="{{ $editing_product_id ? 'updateProduct' : 'createProduct' }}" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg" level="2">
                    {{ $editing_product_id ? __('Edit product') : __('Add product') }}
                </flux:heading>

                <flux:subheading>
                    {{ $editing_product_id ? __('Update product details and inventory quantity.') : __('Create a new product and optionally record its initial stock.') }}
                </flux:subheading>
            </div>

            <div class="space-y-4">
                <flux:input wire:model="name" :label="__('Name')" placeholder="{{ __('Shipping Box') }}" required />
                <flux:textarea wire:model="description" :label="__('Description')" rows="3" placeholder="{{ __('Optional product notes') }}" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="price" :label="__('Price')" type="number" min="0" step="0.01" placeholder="0.00" required />
                    <flux:input wire:model="stock_quantity" :label="$editing_product_id ? __('Stock quantity') : __('Initial stock')" type="number" min="0" step="1" required />
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:button type="button" variant="filled" wire:click="cancelEdit">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ $editing_product_id ? __('Update product') : __('Save product') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="adjust-form" class="w-full max-w-xl" @close="dismissAdjustStockForm">
        <form wire:submit="adjustStock" class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg" level="2">{{ __('Adjust stock') }}</flux:heading>
                <flux:subheading>
                    {{ $adjust_product_name ? __('Record a stock change for :product.', ['product' => $adjust_product_name]) : __('Record an inventory increase, reduction, or correction.') }}
                </flux:subheading>
            </div>

            <input type="hidden" wire:model="adjust_product_id">
            <flux:input wire:model="adjust_quantity" :label="__('Quantity change')" type="number" step="1" required />
            <flux:input wire:model="adjust_reason" :label="__('Reason')" required />

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <flux:button type="button" variant="filled" wire:click="closeAdjustStockForm">
                    {{ __('Cancel') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ __('Update stock') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    <div class="space-y-3">
        <flux:heading level="2">{{ __('Current inventory') }}</flux:heading>

        @error('products')
        <flux:callout variant="danger" icon="x-circle" heading="{{ $message }}" />
        @enderror

        @forelse ($this->products as $product)
        <div wire:key="product-{{ $product->id }}" class="flex flex-col gap-3 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading level="3">{{ $product->name }}</flux:heading>
                <flux:text>{{ $product->description ?: __('No description') }}</flux:text>
                <flux:text class="text-sm">{{ __(':count inventory log(s)', ['count' => $product->inventory_logs_count]) }}</flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <flux:badge>{{ __('Stock: :count', ['count' => $product->stock_quantity]) }}</flux:badge>
                <flux:badge>{{ __('Price: ₱ :price', ['price' => number_format($product->price, 2)]) }}</flux:badge>
                <flux:button type="button" variant="filled" wire:click="editProduct({{ $product->id }})">
                    {{ __('Edit') }}
                </flux:button>
                <flux:button type="button" variant="filled" wire:click="openAdjustStockForm({{ $product->id }})">
                    {{ __('Adjust stock') }}
                </flux:button>
                <flux:button type="button" variant="danger" wire:click="deleteProduct({{ $product->id }})" :disabled="$product->order_items_count > 0">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
        @empty
        <div class="rounded-xl border border-dashed border-neutral-300 p-6 text-sm text-neutral-600 dark:border-neutral-700 dark:text-neutral-300">
            {{ __('No products yet.') }}
        </div>
        @endforelse
    </div>
</section>