<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Cache\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function guestCartToken(): string
    {
        $token = session('cart_token');

        if (!$token) {
            $token = Str::random(40);
            session(['cart_token' => $token]);
        }

        return $token;
    }

    public function guestCart(): array
    {
        return $this->normalizeCart($this->cache()->get($this->guestCartKey(), []));
    }

    public function variantCart(): array
    {
        return (array) session('cart_variant_items', []);
    }

    public function items(): Collection
    {
        if (!$this->canUseCart()) {
            return collect();
        }

        $items = collect();

        if (auth()->check()) {
            $baseItems = Cart::with('product.productImages')->latest()->get();

            foreach ($baseItems as $cartItem) {
                $product = $cartItem->product;
                $items->push((object) [
                    'id' => 'base-' . $product->id,
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'quantity' => (int) $cartItem->quantity,
                    'price' => (float) $product->price,
                    'product' => $product,
                    'variant' => null,
                ]);
            }
        } else {
            $cart = $this->guestCart();

            if ($cart !== []) {
                $products = Product::with('productImages')
                    ->whereIn('id', array_keys($cart))
                    ->get()
                    ->keyBy('id');

                $availableCart = [];

                foreach ($cart as $productId => $quantity) {
                    $product = $products->get($productId);

                    if (!$product) {
                        continue;
                    }

                    $availableCart[$productId] = $quantity;
                    $items->push((object) [
                        'id' => 'base-' . $productId,
                        'product_id' => $productId,
                        'variant_id' => null,
                        'quantity' => $quantity,
                        'price' => (float) $product->price,
                        'product' => $product,
                        'variant' => null,
                    ]);
                }

                if ($availableCart !== $cart) {
                    $this->putGuestCart($availableCart);
                }
            }
        }

        foreach ($this->variantCart() as $key => $entry) {
            $product = Product::with('productImages', 'productVariants.size')->find((int) ($entry['product_id'] ?? 0));

            if (!$product) {
                continue;
            }

            $variant = $product->productVariants->firstWhere('id', (int) ($entry['variant_id'] ?? 0));
            $price = $variant ? (float) $variant->price : (float) $product->price;
            $quantity = (int) ($entry['quantity'] ?? 0);

            if ($quantity < 1) {
                continue;
            }

            $items->push((object) [
                'id' => 'variant-' . $product->id . '-' . ($variant?->id ?? 0),
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity' => $quantity,
                'price' => $price,
                'product' => $product,
                'variant' => $variant,
            ]);
        }

        return $items;
    }

    public function count(): int
    {
        return $this->items()->sum('quantity');
    }

    public function add(Product $product, int $quantity = 1, ?int $variantId = null): bool
    {
        if (!$this->canUseCart()) {
            session()->flash('error', 'Only customers can add products to a cart.');
            return false;
        }

        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be at least 1.']);
        }

        if ($variantId) {
            return $this->addVariant($product, $quantity, $variantId);
        }

        $cart = auth()->check()
            ? $this->authenticatedCart()
            : $this->guestCart();
        $currentQuantity = $cart[$product->id] ?? 0;

        if ($currentQuantity >= $product->quantity) {
            session()->flash('error', "You have reached the available quantity for {$product->name}.");
            return false;
        }

        $newQuantity = $currentQuantity + $quantity;

        $this->validateStock($product, $newQuantity);
        $cart[$product->id] = $newQuantity;

        if (auth()->check()) {
            $item = Cart::where('product_id', $product->id)->first();
            $item ??= new Cart(['product_id' => $product->id]);
            $item->quantity = $newQuantity;
            $item->save();
            session()->flash('success', "Item updated in cart.");
            return true;
        }

        $this->putGuestCart($cart);
        session()->flash('success', "Item added to cart.");
        return true;
    }

    public function updateQuantity(int $productId, int $quantity, ?int $variantId = null): void
    {
        if (!$this->canUseCart()) {
            return;
        }

        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be at least 1.']);
        }

        if ($variantId) {
            $this->updateVariantQuantity($productId, $quantity, $variantId);
            return;
        }

        $product = Product::findOrFail($productId);
        $this->validateStock($product, $quantity);

        if (auth()->check()) {
            $item = Cart::where('product_id', $productId)->firstOrFail();
            $item->quantity = $quantity;
            $item->save();
            return;
        }

        $cart = $this->guestCart();
        if (array_key_exists($productId, $cart)) {
            $cart[$productId] = $quantity;
            $this->putGuestCart($cart);
        }
    }

    public function remove(int $productId, ?int $variantId = null): void
    {
        if (!$this->canUseCart()) {
            return;
        }

        if ($variantId) {
            $this->removeVariant($productId, $variantId);
            return;
        }

        if (auth()->check()) {
            Cart::where('product_id', $productId)->delete();
            return;
        }

        $cart = $this->guestCart();
        unset($cart[$productId]);
        $this->putGuestCart($cart);
    }

    public function clear(): void
    {
        if (!$this->canUseCart()) {
            return;
        }

        if (auth()->check()) {
            Cart::query()->delete();
        }

        $this->cache()->forget($this->guestCartKey());
        session()->forget('cart_variant_items');
    }

    public function mergeGuestCart(User $user, ?string $token = null): void
    {
        if (!$user->can('customer')) {
            return;
        }

        $token ??= session('cart_token');

        if (!$token) {
            return;
        }

        $key = $this->guestCartKey($token);
        $guestCart = $this->normalizeCart($this->cache()->get($key, []));

        if ($guestCart === []) {
            $this->forgetGuestCart($token);
            return;
        }

        DB::transaction(function () use ($user, $guestCart) {
            $products = Product::whereIn('id', array_keys($guestCart))->get()->keyBy('id');

            foreach ($guestCart as $productId => $quantity) {
                $product = $products->get($productId);

                if (!$product || $product->quantity < 1) {
                    continue;
                }

                $item = Cart::withoutGlobalScopes()
                    ->where('user_id', $user->id)
                    ->where('product_id', $productId)
                    ->first();
                $newQuantity = min(($item?->quantity ?? 0) + $quantity, (int) $product->quantity);

                if ($item) {
                    $item->quantity = $newQuantity;
                    $item->save();
                    continue;
                }

                Cart::create([
                    'user_id' => $user->id,
                    'product_id' => $productId,
                    'quantity' => $newQuantity,
                ]);
            }
        });

        $this->forgetGuestCart($token);
    }

    protected function addVariant(Product $product, int $quantity, int $variantId): bool
    {
        $variant = ProductVariant::where('product_id', $product->id)
            ->where('id', $variantId)
            ->first();

        if (!$variant) {
            session()->flash('error', 'The selected size is no longer available.');
            return false;
        }

        $stock = $variant->quantity;
        $current = $this->variantCart()[ $this->variantCartKey($product, $variantId) ]['quantity'] ?? 0;
        $newQuantity = $current + $quantity;

        if ($newQuantity > $stock) {
            session()->flash('error', "Only {$stock} item(s) are available for this variant.");
            return false;
        }

        $items = $this->variantCart();
        $items[$this->variantCartKey($product, $variantId)] = [
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'quantity' => $newQuantity,
        ];
        session(['cart_variant_items' => $items]);
        session()->flash('success', 'Item added to cart.');

        return true;
    }

    protected function updateVariantQuantity(int $productId, int $quantity, int $variantId): void
    {
        $variant = ProductVariant::where('product_id', $productId)
            ->where('id', $variantId)
            ->firstOrFail();

        $this->validateVariantStock($variant, $quantity);

        $items = $this->variantCart();
        $key = $this->variantCartKey(Product::findOrFail($productId), $variantId);

        if (isset($items[$key])) {
            $items[$key]['quantity'] = $quantity;
            session(['cart_variant_items' => $items]);
        }
    }

    protected function removeVariant(int $productId, int $variantId): void
    {
        $items = $this->variantCart();
        $product = Product::find($productId);

        if ($product) {
            unset($items[$this->variantCartKey($product, $variantId)]);
            session(['cart_variant_items' => $items]);
        }
    }

    protected function authenticatedCart(): array
    {
        return Cart::pluck('quantity', 'product_id')
            ->map(fn ($quantity) => (int) $quantity)
            ->all();
    }

    protected function validateStock(Product $product, int $quantity): void
    {
        if ($product->quantity < 1 || $quantity > $product->quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$product->quantity} item(s) are available.",
            ]);
        }
    }

    protected function validateVariantStock(ProductVariant $variant, int $quantity): void
    {
        if ($variant->quantity < 1 || $quantity > $variant->quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$variant->quantity} item(s) are available for this variant.",
            ]);
        }
    }

    protected function putGuestCart(array $cart): void
    {
        $this->cache()->put(
            $this->guestCartKey(),
            $this->normalizeCart($cart),
            config('cart.cache_ttl')
        );
    }

    protected function forgetGuestCart(string $token): void
    {
        $this->cache()->forget($this->guestCartKey($token));

        if (session('cart_token') === $token) {
            session()->forget('cart_token');
        }
    }

    protected function guestCartKey(?string $token = null): string
    {
        return 'cart:' . ($token ?? $this->guestCartToken());
    }

    protected function variantCartKey(Product $product, int $variantId): string
    {
        return $product->id . ':' . $variantId;
    }

    protected function cache(): Repository
    {
        return Cache::store(config('cart.cache_store', 'redis'));
    }

    protected function normalizeCart($cart): array
    {
        return collect(is_array($cart) ? $cart : [])
            ->mapWithKeys(fn ($quantity, $productId) => [(int) $productId => max(1, (int) $quantity)])
            ->all();
    }

    protected function canUseCart(): bool
    {
        return !auth()->check() || auth()->user()->can('customer');
    }
}
