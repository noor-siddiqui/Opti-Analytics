## 2024-05-24 - Unbounded wc_get_products calls cause OOMs
**Learning:** Calling `wc_get_products` with `limit => -1` can cause Out Of Memory (OOM) errors on stores with large catalogs, because it loads all product objects into memory at once.
**Action:** When only an accurate total count is needed alongside a small subset of objects for display (e.g. out-of-stock items), always use `paginate => true` with a reasonable `limit` (e.g. 50). Access the true total count via the `total` property of the returned query object.
