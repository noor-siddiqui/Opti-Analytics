## 2024-04-26 - [Unbounded memory usage when counting objects]
**Learning:** Using `limit => -1` when only trying to get a count of products can cause severe memory issues and out-of-memory crashes on large stores, since it still fetches an array of all matched IDs into PHP memory.
**Action:** When counting WooCommerce products or posts, prefer using `paginate => true` with a small `limit` (like 1) and checking the `total` property of the returned object. This relies on an efficient `SQL COUNT()` query rather than loading unbounded data arrays into memory.
