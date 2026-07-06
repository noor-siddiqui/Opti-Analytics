## 2024-10-27 - OOM caused by wc_get_products with limit -1
**Learning:** Using `wc_get_products` with `limit => -1` can cause unbounded memory usage (OOM errors) in large stores.
**Action:** When an accurate total count is needed alongside a subset of products for display, always use `paginate => true` with a reasonable limit (e.g., 50) instead of fetching all objects. Access the total count via the `total` property of the returned object and items via the `products` property.
