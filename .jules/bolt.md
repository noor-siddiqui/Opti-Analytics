## 2026-06-08 - WooCommerce Products Query Optimization
**Learning:** Using `wc_get_products` with `limit => -1` can cause unbounded memory usage (OOM errors) in large stores. Using `paginate => true` and a small `limit` allows fetching the accurate total count via the `total` property without loading all objects into memory.
**Action:** When only a subset of products is needed for display but an accurate total count is required, always use `paginate => true` with a reasonable limit instead of fetching all IDs or objects.
