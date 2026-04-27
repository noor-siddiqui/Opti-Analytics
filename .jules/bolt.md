## 2026-04-27 - WooCommerce Product Counting Memory Limit
**Learning:** Calling `wc_get_products` with `limit => -1` and `return => ids` to simply get a count of products (like out-of-stock count) loads an unbounded array of IDs into memory, leading to potential memory exhaustion on large stores.
**Action:** Always use `wc_get_products` with `paginate => true` and `limit => 1` (or another small number) when you only need a count, and retrieve the value from the returned object's `total` property.
