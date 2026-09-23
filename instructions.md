Absolutely. Since you haven't built the full product page yet, the instructions should target the **product quick-view modal/component** instead. Everything else can remain the same.

## Refactor Add Product UI & Cart for Size Variants

Refactor the existing **Add Product** functionality in the Laravel ecommerce application.

Do **not** create or modify database tables, migrations, Eloquent relationships, or validation rules. I will handle those parts myself.

Focus only on:

1. Refactoring the **Add Product UI**
2. Refactoring the corresponding **Edit Product UI**, where necessary
3. Supporting size-based product variants in the UI
4. Updating the **product quick-view UI** to support variant selection
5. Updating the **cart functionality** to correctly handle selected variants

Follow the existing project's Laravel, Blade, Livewire, Bootstrap, and JavaScript conventions.

---

# 1. Add Product UI

Refactor the existing Add Product form into clear sections instead of one large form.

### Product Information

Keep the existing product information fields, such as:

* Product name
* Description
* Brand
* Category
* Product images
* Any other existing product fields already part of the application

Do not remove existing functionality unnecessarily.

---

# 2. Product Type / Size Selection

Add a clear section asking the store owner:

> **Does this product come in different sizes?**

Provide two options:

```text
○ No
  This product has one price and stock quantity.

○ Yes
  Each size can have its own price and stock quantity.
```

Do not expose technical terminology such as `product_variants` to the store owner.

The store owner should think of this as simply deciding whether the product has different sizes.

---

# 3. Product Without Sizes

When the store owner selects **No**, display the normal product pricing/inventory fields:

```text
Price
[ ₦              ]

Quantity
[                ]

Weight
[                ]

SKU
[                ]
```

These are the normal product-level fields already used by the application.

The existing Add Product behavior should continue to work for products without sizes.

---

# 4. Product With Sizes

When the store owner selects **Yes**, replace the normal product-level pricing/inventory interface with a dynamic **Sizes** section.

The UI should look approximately like:

```text
Sizes

Add the sizes available for this product.

┌───────────────────────────────────────────────────────┐
│ Size       Price       Quantity      Weight      SKU  │
├───────────────────────────────────────────────────────┤
│ Small      ₦15,000     10            0.30kg      ...  │
│ Medium     ₦16,000     7             0.35kg      ...  │
│ Large      ₦17,000     3             0.40kg      ...  │
└───────────────────────────────────────────────────────┘

[ + Add another size ]
```

Each row represents one size option.

The store owner should be able to:

* select a size
* enter the price for that size
* enter the quantity for that size
* enter the weight for that size
* enter the SKU for that size
* add another size
* remove a size

Prevent the UI from allowing the same size to be selected twice.

Use the existing size data in the application rather than hard-coding sizes.

---

# 5. Dynamic UI Behavior

Use the existing Livewire setup for dynamic behavior.

When the store owner switches between:

```text
No
```

and:

```text
Yes
```

the appropriate pricing/inventory interface should appear.

When the store owner clicks:

```text
+ Add another size
```

a new size row should appear without unnecessarily reloading the entire page.

When a size row is removed, the remaining rows should continue working correctly.

Keep the implementation clean and simple.

---

# 6. Product Editing

Apply the same concept to the existing Edit Product interface.

For a product without sizes, show:

```text
Price
Quantity
Weight
SKU
```

For a product with sizes, show the existing size rows:

```text
Size       Price       Quantity       Weight       SKU
Small      ₦15,000     10             0.30kg      ...
Medium     ₦16,000     7              0.35kg      ...
Large      ₦17,000     3              0.40kg      ...
```

Allow the store owner to:

* edit existing sizes
* change prices
* change quantities
* change weights
* change SKUs
* add another size
* remove a size

Preserve the existing product editing functionality.

---

# 7. Product Quick View

Do **not** build or modify a full product page. A full product page has not been implemented yet.

Instead, update the existing **product quick-view UI/modal** (which exists currently in views/store/new_arrivals) so that products with sizes allow the customer to select a size before adding the product to the cart.

For example:

```text
┌──────────────────────────────────────────────┐
│ Product Image                                │
│                                              │
│ Cotton T-Shirt                               │
│                                              │
│ Size:                                        │
│ [ Small ] [ Medium ] [ Large ] [ XL ]       │
│                                              │
│ Price: ₦16,000                               │
│                                              │
│ Quantity: [-] 1 [+]                          │
│                                              │
│ [ Add to Cart ]                              │
└──────────────────────────────────────────────┘
```

For products without sizes, the size selection should not appear.

The quick view should continue displaying the normal product information that it currently displays.

---

# 8. Quick View Variant Behavior

When the customer selects a size:

* visually indicate the selected size
* update the displayed price
* update the available quantity/stock information if the quick view currently displays stock information
* keep track of the selected variant

For example:

```text
Small  → ₦15,000
Medium → ₦16,000
Large  → ₦17,000
```

Selecting **Medium** should cause the quick view to display:

```text
Size: Medium
Price: ₦16,000
```

Do not expose the internal variant database ID to the customer.

---

# 9. Quick View Add to Cart

For a product without sizes:

```text
Product → Add to Cart
```

Continue using the existing behavior.

For a product with sizes:

The customer must select a size before adding the product to the cart.

The selected variant must be passed to the existing cart logic.

For example:

```text
Cotton T-Shirt
Size: Medium
Quantity: 2
```

should add the **Medium variant**, not merely the base product.

---

# 10. Cart Display

Update the cart UI so that when an item has a size variant, the selected size is displayed.

Example:

```text
Cotton T-Shirt

Size: Medium
Price: ₦16,000
Quantity: 2

[-] 2 [+]
```

For products without sizes, continue displaying the existing product information without a size.

---

# 11. Cart Quantity Updates

Make sure quantity updates continue to work correctly for variant products.

For example:

```text
Cotton T-Shirt
Size: Medium

Quantity: 2
```

Increasing the quantity should increase the quantity of the **Medium variant**, not the product's general quantity.

Likewise, removing the cart item should remove that specific variant from the cart.

---

# 12. Cart Price Calculation

When calculating the cart total:

```text
No variant:
product price × cart quantity

With variant:
variant price × cart quantity
```

Do not use the base product price for a variant cart item.

---

# 13. Cart Item Uniqueness

The cart must distinguish between different sizes of the same product.

These should be separate cart items:

```text
Cotton T-Shirt — Small
Cotton T-Shirt — Medium
Cotton T-Shirt — Large
```

But adding the same product and same size again should increase the existing cart item's quantity instead of creating another duplicate item.

For example:

```text
Cotton T-Shirt — Medium × 1
```

followed by another:

```text
Cotton T-Shirt — Medium × 1
```

should result in:

```text
Cotton T-Shirt — Medium × 2
```

---

# 14. Keep Existing Cart Architecture

Do not rewrite the entire cart system unnecessarily.

Work with the existing cart implementation and make the smallest clean changes necessary to support:

* products without sizes
* products with sizes
* selected variant IDs
* variant-specific prices
* variant-specific quantities
* separate cart items for different sizes

Preserve all existing cart functionality unrelated to variants.

---

# 15. Shipping

Do **not** implement shipping functionality as part of this refactor.

Do not add:

* shipping calculations
* shipping methods
* delivery fees
* delivery zones
* tracking
* shipping providers
* delivery addresses

Weight is currently just a product/variant attribute. It should not trigger any shipping logic.

---

# 16. Overall UX Goal

The store owner should experience the process as:

### Simple product

```text
Add Product

Product information...

Does this product come in different sizes?

○ No
○ Yes

Price: ₦15,000
Quantity: 20
Weight: 0.35kg
SKU: TSH-001

[Add Product]
```

### Size-based product

```text
Add Product

Product information...

Does this product come in different sizes?

○ No
● Yes

Sizes

Size       Price       Quantity      Weight      SKU
Small      ₦15,000     10            0.30kg      TSH-S
Medium     ₦16,000     7             0.35kg      TSH-M
Large      ₦17,000     3             0.40kg      TSH-L

[+ Add another size]

[Add Product]
```

The customer then interacts with the product through the **quick-view modal**:

```text
Cotton T-Shirt

Size:
[ Small ] [ Medium ] [ Large ]

Price: ₦16,000

Quantity: [-] 1 [+]

[ Add to Cart ]
```

The selected size determines the price and the specific inventory that the cart operates on.

Keep the UI intuitive, avoid unnecessary complexity, and reuse the application's existing components and styling wherever possible.

This keeps the scope exactly where you want it: **Add Product → Edit Product → Quick View → Cart**, while you handle the underlying database/model/validation work yourself.
