Update the product variant implementation to support **general product variants**, not just size-based variants.

### Product Variant Concept

The product creation/editing UI should ask:

**“Does this product have different variants?”**

Provide:

* **No**
* **Yes**

A variant can represent different sizes, models, storage capacities, colors, configurations, etc.

Examples:

**Clothing**

* Small
* Medium
* Large

**iPhone**

* Pro 256GB
* Pro 512GB
* Pro Max 256GB
* Pro Max 512GB

### Variant Fields

Each variant should have:

* **Variant name** — required. This describes the variant to the customer, e.g. `Pro 256GB`, `Pro Max 512GB`, `Black - Large`, etc.
* **Size** — optional. Use the existing sizes from the database. This should not be required because many products do not have traditional sizes.
* **Price**
* **Quantity**
* **Weight**
* **SKU**

Do not assume that every variant has a size.

For example, an iPhone variant can have:

```text
Variant name: Pro 256GB
Size: None
Price: ₦...
Quantity: ...
Weight: ...
SKU: ...
```

while a clothing variant can have:

```text
Variant name: Medium
Size: M
Price: ₦...
Quantity: ...
Weight: ...
SKU: ...
```

### Add Product UI

If the user selects **No**, show the product-level:

* Price
* Quantity
* Weight
* SKU

If the user selects **Yes**, show a dynamic list of variant rows.

Each row should contain:

* Variant name
* Size (optional, populated from the existing sizes data)
* Price
* Quantity
* Weight
* SKU
* Add/remove controls

Allow the store owner to add and remove variant rows dynamically.

Do not hardcode sizes. Use the existing sizes data.

### Variant Validation in the UI

Prevent duplicate variants where appropriate. The same variant should not accidentally be added multiple times.

The variant name should clearly identify the variant to the store owner and, later, to the customer.

### Product Quick View

Update the customer-facing product quick view to support general variants.

If a product has variants:

* Display the available variant names.
* Allow the customer to select a variant before adding the product to the cart.
* If a variant has a size, display the size appropriately.
* Do not expose internal variant IDs to the customer.
* The selected variant determines the price and available quantity.
* The customer should not be able to add the product without selecting a variant.

For example:

```text
Choose a variant:

[Pro 256GB]
[Pro 512GB]
[Pro Max 256GB]
[Pro Max 512GB]
```

For clothing, the UI could instead show the variant names and/or associated sizes.

### Edit Product UI

The edit product UI should use the same general variant structure as the Add Product UI.

If the product has variants:

* Load its existing variants.
* Allow the store owner to edit variant names, sizes, prices, quantities, weights and SKUs.
* Allow variants to be added or removed.
* Do not assume that every variant has a size.

If the product does not have variants:

* Show the normal product-level price, quantity, weight and SKU fields.

### Important Scope

Do **not** create or modify migrations, database tables, Eloquent relationships, or validation rules. I will handle those separately.

Focus on:

1. Add Product UI
2. Edit Product UI
3. Product quick view
4. Variant selection
5. Cart behavior when adding a selected variant

Use the existing Livewire/Blade/Bootstrap architecture and keep the implementation as simple as possible. Do not introduce unnecessary abstractions or unrelated changes.
