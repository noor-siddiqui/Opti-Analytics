# Opti Analytics

[![Version](https://img.shields.io/badge/version-0.0.1--beta-blue.svg)](https://github.com/noor-siddiqui/Opti-Analytics)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-%3E%3D_8.0-orange.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D_8.1-777bb4.svg)](https://php.net/)

**Opti Analytics** is a high-performance sales analytics and financial reporting plugin for WooCommerce. It empowers store owners with deep insights into their profitability by integrating custom data points, tracking actual costs, and providing a dynamic, customizable dashboard.

## 🚀 Summary

Standard WooCommerce reporting often misses the "hidden" costs of doing business. Opti Analytics bridges this gap by allowing you to track **Cost of Goods Sold (COGS)**, manual shipping expenses, and any custom meta fields (like payment fees or specialized taxes) directly within your sales reports. Built with modern PHP 8.1+ standards and fully compatible with WooCommerce's High-Performance Order Storage (HPOS), it ensures your reporting stays fast as your store grows.

## ✨ Key Features

- **Advanced Profitability Tracking**: 
  - Supports WooCommerce Native COGS (v10.3+).
  - Snapshots historical COGS at the time of order for accurate "past performance" reporting.
- **Dynamic Custom Fields**:
  - Aggregate any **Order Meta** (e.g., `_stripe_fee`, `_cod_charge`).
  - Aggregate **Line Item Meta** (e.g., per-product custom costs × quantity).
- **Manual Shipping Costs**: Adds a dedicated input field to the WooCommerce Order admin to record actual shipping expenses for precise net margin calculation.
- **Customizable Dashboard**: 
  - Filter by date ranges (Today, This Week, Last Month, Custom, etc.).
  - Toggle-able KPI cards: Show only the metrics that matter to you.
  - Metrics include: Total Sales, Net Sales, Gross Sales, AOV, Discounts, Products Sold, Out of Stock count, and more.
- **Modern Architecture**:
  - **HPOS Ready**: Uses WooCommerce CRUD APIs for future-proof compatibility.
  - **PSR-4 Autoloading**: Clean, modular code structure via Composer.
  - **Developer Friendly**: Includes PHPCS configurations for WordPress Coding Standards.

## 🛠️ Installation

### Via GitHub (Development)
1. Clone the repository into your `wp-content/plugins` directory:
   ```bash
   git clone https://github.com/noor-siddiqui/Opti-Analytics.git opti-analytics
   ```
2. Navigate to the plugin folder and install dependencies:
   ```bash
   cd opti-analytics
   composer install
   ```
3. Activate the plugin via the WordPress Admin Dashboard.

## 📖 Usage

1. **Dashboard**: Navigate to **WooCommerce > Opti Analytics** to view your sales performance. Use the gear/toggle icon to show or hide specific metrics.
2. **Settings**: Go to **Opti Analytics > Settings** to define the meta keys you want to track (e.g., if you use a plugin that adds `_custom_fee`, add that key to the "Order Meta Fields" setting).
3. **Manual Shipping**: Open any WooCommerce Order. You will find a field to enter the "Actual Shipping Cost". This value will be used in the dashboard's "Actual Shipping" metric.

## 💻 Development

This plugin follows strict typing and WordPress Coding Standards.

### Linting
To check for coding standard violations:
```bash
composer run phpcs
```

To automatically fix violations:
```bash
composer run phpcbf
```

## 📄 License

This project is licensed under the GNU General Public License v3.0 - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Developer

**Author:** Noor Nabiul Alam Siddiqui
**Email:**  [siddiqui.sazal@gmail.com](mailto:siddiqui.sazal@gmail.com)
