## 2024-06-25 - [Optimize Out-of-Stock Products Query]
**Learning:** When counting WooCommerce products based on a specific criteria (like stock status), using `wc_get_products` with `'limit' => -1` can lead to unbounded memory usage, especially for stores with large catalogs.
**Action:** Always use `'limit' => 1` and `'paginate' => true` when only the total count of products is needed. Then, access the count via the `total` property of the returned object.
