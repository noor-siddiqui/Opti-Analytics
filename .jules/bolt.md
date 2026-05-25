
## 2024-05-25 - Efficiently Counting WooCommerce Products
**Learning:** Using `wc_get_products()` with `return => 'ids'` and `limit => -1` just to count the result loads an unbounded array into memory. This is a common performance bottleneck in WooCommerce for stores with many products.
**Action:** Always use `paginate => true` with `limit => 1` and access the `->total` property of the returned object to force an optimized `COUNT(*)` query without hydrating unnecessary data.
