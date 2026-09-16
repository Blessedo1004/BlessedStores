<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
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

    public function items(): Collection
    {
        if (auth()->check()) {
            return Cart::with('product.productImages')->latest()->get();
        }

        $cart = $this->guestCart();

        if ($cart === []) {
            return collect();
        }

        $products = Product::with('productImages')
            ->whereIn('id', array_keys($cart))
            ->get()
            ->keyBy('id');

        $availableCart = [];
        $items = collect();

        foreach ($cart as $productId => $quantity) {
            $product = $products->get($productId);

            if (!$product) {
                continue;
            }

            $availableCart[$productId] = $quantity;
            $items->push((object) [
                'id' => $productId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'product' => $product,
            ]);
        }

        if ($availableCart !== $cart) {
            $this->putGuestCart($availableCart);
        }

        return $items;
    }

    public function count(): int
    {
        return $this->items()->sum('quantity');
    }

    public function add(Product $product, int $quantity = 1): bool
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be at least 1.']);
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

    public function updateQuantity(int $productId, int $quantity): void
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be at least 1.']);
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

    public function remove(int $productId): void
    {
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
        if (auth()->check()) {
            Cart::query()->delete();
            return;
        }

        $this->cache()->forget($this->guestCartKey());
    }

    public function mergeGuestCart(User $user, ?string $token = null): void
    {
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
}
