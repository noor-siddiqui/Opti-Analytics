## 2024-05-03 - Optimizing wc_get_products counts
**Learning:** When counting WooCommerce products based on specific criteria (e.g. out of stock status), fetching all IDs with `limit => -1` causes unbounded memory usage and slow performance, especially for large catalogs.
**Action:** Always use `wc_get_products` with `paginate => true` and a small `limit` (like 1), then access the `$query->total` property to get the count directly and efficiently without loading the entire result set into memory.
