## 2024-05-18 - Avoid OOM with wc_get_products count
**Learning:** Using `limit => -1` in `wc_get_products` to count items by counting the returned IDs array causes unbounded memory usage and database I/O overhead on large catalogs.
**Action:** Always use `paginate => true` with `limit => 1` and access the `total` property of the result object to perform a lightweight `COUNT(*)` query.
