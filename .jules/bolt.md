## 2024-06-01 - Avoid unbound product queries for counting
**Learning:** Using `wc_get_products` with `limit => -1` to fetch all product IDs just to count them (`count($oos_ids)`) is a major performance and memory bottleneck in WooCommerce for stores with large catalogs.
**Action:** When needing a count of products matching specific criteria, always use `wc_get_products` with `paginate => true` and a small `limit` (e.g., `limit => 1`), and retrieve the count using the `->total` property of the returned object.
