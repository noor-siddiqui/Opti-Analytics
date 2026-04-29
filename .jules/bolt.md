## 2026-04-29 - Efficient Product Counting in WooCommerce
**Learning:** Using `wc_get_products` with `'limit' => -1` and `count()` causes unbounded memory usage when querying large product datasets, as it fetches all matching IDs into memory.
**Action:** When counting WooCommerce products, always use `wc_get_products` with `'paginate' => true` and `'limit' => 1`. This returns an object containing a `total` property (the total number of matching products), avoiding massive memory consumption.
