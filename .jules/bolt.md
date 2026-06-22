## 2024-05-24 - Unbounded memory usage with wc_get_products
**Learning:** Using `wc_get_products` with `limit => -1` can cause unbounded memory usage (OOM errors) in large stores. When an accurate total count is needed alongside a subset of products for display, retrieving all objects is extremely inefficient.
**Action:** Always use `paginate => true` with a reasonable limit (e.g., 50) instead of fetching all objects. Access the total count via the `total` property of the returned paginated object.
