## 2024-05-18 - Avoid Unbounded Product Queries
**Learning:** Using `wc_get_products` with `limit => -1` can cause unbounded memory usage when only the count is needed.
**Action:** Use `paginate => true` with a `limit => 1` to get the `total` without fetching all records into memory.
