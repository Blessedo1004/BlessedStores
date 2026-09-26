<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Cache\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WishlistService
{
    public function items(): Collection
    {
        if (! $this->canUseWishlist()) {
            return collect();
        }

        if (auth()->check()) {
            return Wishlist::query()
                ->where('user_id', auth()->id())
                ->with('product.productImages', 'variant')
                ->latest()
                ->get();
        }

        $entries = $this->guestWishlist();

        if ($entries === []) {
            return collect();
        }

        $products = Product::with(['productImages', 'productVariants'])
            ->whereIn('id', collect($entries)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        return collect($entries)->map(function (array $entry) use ($products) {
            $product = $products->get($entry['product_id']);

            if (! $product) {
                return null;
            }

            return (object) [
                'id' => $this->entryKey($entry['product_id'], $entry['variant_id']),
                'product_id' => $product->id,
                'variant_id' => $entry['variant_id'],
                'product' => $product,
                'variant' => $entry['variant_id'] ? $product->productVariants->firstWhere('id', $entry['variant_id']) : null,
            ];
        })->filter()->values();
    }

    public function count(): int
    {
        return $this->items()->count();
    }

    public function add(Product $product, ?int $variantId = null): bool
    {
        if (! $this->canUseWishlist()) {
            session()->flash('error', 'Only customers can add products to a wishlist.');
            return false;
        }

        if ($variantId && ! $product->productVariants()->whereKey($variantId)->exists()) {
            session()->flash('error', 'The selected variant is no longer available.');
            return false;
        }

        if (auth()->check()) {
            $exists = Wishlist::query()
                ->where('user_id', auth()->id())
                ->where('product_id', $product->id)
                ->when($variantId, fn ($query) => $query->where('variant_id', $variantId), fn ($query) => $query->whereNull('variant_id'))
                ->exists();

            if ($exists) {
                session()->flash('error', 'This item is already in your wishlist.');
                return false;
            }

            Wishlist::create([
                'user_id' => auth()->id(),
                'product_id' => $product->id,
                'variant_id' => $variantId,
            ]);
        } else {
            $entries = $this->guestWishlist();
            $key = $this->entryKey($product->id, $variantId);

            if (collect($entries)->contains(fn (array $entry) => $this->entryKey($entry['product_id'], $entry['variant_id']) === $key)) {
                session()->flash('error', 'This item is already in your wishlist.');
                return false;
            }

            $entries[] = ['product_id' => $product->id, 'variant_id' => $variantId];
            $this->putGuestWishlist($entries);
        }

        session()->flash('success', 'Item added to wishlist.');
        return true;
    }

    public function remove(int $productId, ?int $variantId = null): void
    {
        if (! $this->canUseWishlist()) {
            return;
        }

        if (auth()->check()) {
            Wishlist::query()
                ->where('user_id', auth()->id())
                ->where('product_id', $productId)
                ->when($variantId, fn ($query) => $query->where('variant_id', $variantId), fn ($query) => $query->whereNull('variant_id'))
                ->delete();
            return;
        }

        $this->putGuestWishlist(array_values(array_filter(
            $this->guestWishlist(),
            fn (array $entry) => $this->entryKey($entry['product_id'], $entry['variant_id']) !== $this->entryKey($productId, $variantId)
        )));
    }

    public function mergeGuestWishlist(User $user, ?string $token = null): void
    {
        if (! $user->can('customer')) {
            return;
        }

        $token ??= session('wishlist_token');

        if (! $token) {
            return;
        }

        foreach ($this->guestWishlist($token) as $entry) {
            $exists = Wishlist::query()
                ->where('user_id', $user->id)
                ->where('product_id', $entry['product_id'])
                ->when($entry['variant_id'], fn ($query) => $query->where('variant_id', $entry['variant_id']), fn ($query) => $query->whereNull('variant_id'))
                ->exists();

            if (! $exists) {
                Wishlist::create([
                    'user_id' => $user->id,
                    'product_id' => $entry['product_id'],
                    'variant_id' => $entry['variant_id'],
                ]);
            }
        }

        $this->cache()->forget($this->guestWishlistKey($token));

        if (session('wishlist_token') === $token) {
            session()->forget('wishlist_token');
        }
    }

    protected function guestWishlist(?string $token = null): array
    {
        return collect((array) $this->cache()->get($this->guestWishlistKey($token), []))
            ->map(fn ($entry) => [
                'product_id' => (int) ($entry['product_id'] ?? 0),
                'variant_id' => filled($entry['variant_id'] ?? null) ? (int) $entry['variant_id'] : null,
            ])
            ->filter(fn (array $entry) => $entry['product_id'] > 0)
            ->unique(fn (array $entry) => $this->entryKey($entry['product_id'], $entry['variant_id']))
            ->values()
            ->all();
    }

    protected function putGuestWishlist(array $entries): void
    {
        $this->cache()->put($this->guestWishlistKey(), $entries, config('cart.cache_ttl'));
    }

    protected function guestWishlistKey(?string $token = null): string
    {
        return 'wishlist:' . ($token ?? $this->guestWishlistToken());
    }

    protected function guestWishlistToken(): string
    {
        $token = session('wishlist_token');

        if (! $token) {
            $token = Str::random(40);
            session(['wishlist_token' => $token]);
        }

        return $token;
    }

    protected function entryKey(int $productId, ?int $variantId): string
    {
        return $productId . ':' . ($variantId ?? 'base');
    }

    protected function cache(): Repository
    {
        return Cache::store(config('cart.cache_store', 'redis'));
    }

    protected function canUseWishlist(): bool
    {
        return ! auth()->check() || auth()->user()->can('customer');
    }
}
