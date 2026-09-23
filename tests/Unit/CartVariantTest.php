<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Models\Store;
use App\Models\User;
use App\Services\CartService;

it('keeps separate variant items and applies variant pricing', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $store = Store::create([
        'user_id' => $user->id,
        'name' => 'Demo Store',
        'phone_number' => '08000000000',
        'logo' => 'logo.png',
        'description' => 'Sample store',
        'address' => '12 Main Street',
    ]);

    $product = new Product();
    $product->name = 'Cotton T-Shirt';
    $product->description = 'Great shirt';
    $product->store_id = $store->id;
    $product->user_id = $user->id;
    $product->price = 10000;
    $product->quantity = 50;
    $product->status = 'in-stock';
    $product->visibility = 'published';
    $product->cover_image = 'products/cover.jpg';
    $product->sku = 'TSHIRT-BASE';
    $product->weight = 0.4;
    $product->save();

    $small = Size::create(['name' => 'Small']);
    $medium = Size::create(['name' => 'Medium']);

    ProductVariant::create([
        'product_id' => $product->id,
        'size_id' => $small->id,
        'price' => 15000,
        'quantity' => 10,
        'weight' => 0.3,
        'sku' => 'TSHIRT-SMALL',
    ]);

    ProductVariant::create([
        'product_id' => $product->id,
        'size_id' => $medium->id,
        'price' => 16000,
        'quantity' => 7,
        'weight' => 0.35,
        'sku' => 'TSHIRT-MEDIUM',
    ]);

    $cart = app(CartService::class);

    $cart->add($product, 1, $medium->id);
    $cart->add($product, 1, $small->id);
    $cart->add($product, 2, $medium->id);

    expect($cart->count())->toBe(4)
        ->and($cart->items()->pluck('variant_id')->sort()->all())->toBe([$small->id, $medium->id])
        ->and($cart->items()->firstWhere('variant_id', $medium->id)->quantity)->toBe(3)
        ->and($cart->items()->firstWhere('variant_id', $medium->id)->price)->toBe(16000)
        ->and($cart->items()->sum(fn ($item) => $item->price * $item->quantity))->toBe(15000 + (16000 * 3));
});
