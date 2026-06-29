## 2024-05-01 - OOM Bottleneck in Dashboard Products Query
**Learning:** Using `wc_get_products()` with `limit => -1` to count out-of-stock products causes fatal memory exhaustion (OOM) on large WooCommerce stores because it loads all product objects into memory at once.
**Action:** When only an accurate total count is needed alongside a small display subset (like a dashboard widget), always use `paginate => true` with a safe limit (e.g., 50). Extract the count from the returned object`s `total` property.
