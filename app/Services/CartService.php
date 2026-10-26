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
        $cachedItems = $this->cache()->get($this->guestVariantCartKey(), null);

        if (is_null($cachedItems)) {
            $cachedItems = session('cart_variant_items', []);

            if ($cachedItems !== []) {
                $this->putGuestVariantCart($cachedItems);
                session()->forget('cart_variant_items');
            }
        }

        return $this->normalizeVariantCart($cachedItems ?? []);
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
            $requestedVariantId = (int) ($entry['variant_id'] ?? 0);
            $price = $requestedVariantId ? ($variant ? (float) $variant->price : null) : (float) $product->price;
            $quantity = (int) ($entry['quantity'] ?? 0);

            if ($quantity < 1) {
                continue;
            }

            $items->push((object) [
                'id' => 'variant-' . $product->id . '-' . ($variant?->id ?? 0),
                'product_id' => $product->id,
                'variant_id' => $requestedVariantId ?: null,
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
        $this->cache()->forget($this->guestVariantCartKey());
        session()->forget('cart_variant_items');
    }

    /** Remove only the exact product/variant lines included in a completed order. */
    public function removeItems(iterable $items): void
    {
        if (! $this->canUseCart()) {
            return;
        }

        $productIds = [];
        $variantKeys = [];
        foreach ($items as $item) {
            $productId = (int) ($item->product_id ?? 0);
            if ($productId < 1) {
                continue;
            }

            if (filled($item->variant_id ?? null)) {
                $variantKeys[] = $productId . ':' . (int) $item->variant_id;
            } else {
                $productIds[] = $productId;
            }
        }

        if (auth()->check() && $productIds !== []) {
            Cart::query()->whereIn('product_id', array_unique($productIds))->delete();
        } elseif ($productIds !== []) {
            $guestItems = $this->guestCart();
            foreach (array_unique($productIds) as $productId) {
                unset($guestItems[$productId]);
            }
            $this->putGuestCart($guestItems);
        }

        if ($variantKeys !== []) {
            $variantItems = $this->variantCart();
            foreach (array_unique($variantKeys) as $key) {
                unset($variantItems[$key]);
            }
            if ($variantItems === []) {
                $this->cache()->forget($this->guestVariantCartKey());
                session()->forget('cart_variant_items');
            } else {
                $this->putGuestVariantCart($variantItems);
            }
        }

        if (! auth()->check() && $this->guestCart() === [] && $this->variantCart() === []) {
            $this->forgetGuestCart($this->guestCartToken());
        }
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
        $guestVariants = $this->normalizeVariantCart(
            $this->cache()->get($this->guestVariantCartKey($token), session('cart_variant_items', []))
        );
        $existingVariantCart = $this->normalizeVariantCart(session('cart_variant_items', []));
        $validatedVariants = $existingVariantCart;

        DB::transaction(function () use ($user, $guestCart, $guestVariants, &$validatedVariants): void {
            $productIds = collect(array_keys($guestCart))
                ->merge(collect($guestVariants)->pluck('product_id'))
                ->unique()->sort()->values();
            $products = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

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

            $variantIds = collect($guestVariants)->pluck('variant_id')->unique()->sort()->values();
            $variants = $variantIds->isEmpty()
                ? collect()
                : ProductVariant::whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            foreach ($guestVariants as $entry) {
                $product = $products->get($entry['product_id']);
                $variant = $variants->get($entry['variant_id']);

                if (! $product || ! $variant || (int) $variant->product_id !== (int) $product->id || $variant->quantity < 1) {
                    continue;
                }

                $key = $this->variantCartKey($product, $variant->id);
                $entry['quantity'] = min((int) $entry['quantity'], (int) $variant->quantity);
                $existingQuantity = (int) ($validatedVariants[$key]['quantity'] ?? 0);
                $mergedQuantity = min($existingQuantity + (int) $entry['quantity'], (int) $variant->quantity);

                $validatedVariants[$key] = [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'quantity' => $mergedQuantity,
                ];
            }
        });

        if ($validatedVariants !== []) {
            session()->put('cart_variant_items', $validatedVariants);
        } else {
            session()->forget('cart_variant_items');
        }

        $this->cache()->forget($this->guestVariantCartKey($token));
        $this->cache()->forget($this->guestCartKey($token));

        if (session('cart_token') === $token) {
            session()->forget('cart_token');
        }
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
        $this->putGuestVariantCart($items);
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
            $this->putGuestVariantCart($items);
        }
    }

    protected function removeVariant(int $productId, int $variantId): void
    {
        $items = $this->variantCart();
        $product = Product::find($productId);

        if ($product) {
            unset($items[$this->variantCartKey($product, $variantId)]);
            $this->putGuestVariantCart($items);
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

    protected function putGuestVariantCart(array $items): void
    {
        $this->cache()->put(
            $this->guestVariantCartKey(),
            $this->normalizeVariantCart($items),
            config('cart.cache_ttl')
        );
        session()->forget('cart_variant_items');
    }

    protected function forgetGuestCart(string $token): void
    {
        $this->cache()->forget($this->guestCartKey($token));
        $this->cache()->forget($this->guestVariantCartKey($token));

        if (session('cart_token') === $token) {
            session()->forget('cart_token');
        }
    }

    protected function guestCartKey(?string $token = null): string
    {
        return 'cart:' . ($token ?? $this->guestCartToken());
    }

    protected function guestVariantCartKey(?string $token = null): string
    {
        return 'cart:variant:' . ($token ?? $this->guestCartToken());
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

    protected function normalizeVariantCart($cart): array
    {
        return collect(is_array($cart) ? $cart : [])
            ->filter(fn ($entry) => is_array($entry))
            ->mapWithKeys(fn ($entry, $key) => [
                (string) $key => [
                    'product_id' => (int) ($entry['product_id'] ?? 0),
                    'variant_id' => (int) ($entry['variant_id'] ?? 0),
                    'quantity' => max(1, (int) ($entry['quantity'] ?? 0)),
                ],
            ])
            ->all();
    }

    protected function canUseCart(): bool
    {
        return !auth()->check() || auth()->user()->can('customer');
    }
}
