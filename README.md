<div align="center">

# 📦 Low Stock Alert for WooCommerce

**One clear stock report instead of a flood of emails, plus low stock at a glance in your dashboard.**

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?style=flat&logo=wordpress&logoColor=white)
![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-96588A?style=flat&logo=woocommerce&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat&logo=php&logoColor=white)
![HPOS](https://img.shields.io/badge/HPOS-compatible-success?style=flat)
![Tests](https://img.shields.io/badge/end--to--end%20tests-passing-brightgreen?style=flat)
![License](https://img.shields.io/badge/license-GPL--2.0-orange?style=flat)

</div>

---

## 😩 The problem

WooCommerce sends one email per product, at the moment it runs low. They get buried, and there is no single place to see *everything* that needs restocking.

## ✨ What this plugin adds

| Feature | Details |
|---|---|
| 📧 **Stock report email** | Daily or weekly, at the hour you choose: every low and out-of-stock product (and variation) in one table with SKU, stock and a link to edit. Sent to as many people as you like. |
| 📊 **Dashboard widget** | "1 out of stock · 3 low" plus the 10 most urgent products. |
| 🔴 **Admin bar badge** | "Low stock: 4" on every admin page and the storefront, for shop managers. |
| 🗂️ **"Low stock" view** | A new view in **Products** next to All / Published / Drafts, listing only products that need restocking. |
| 🎯 **Your thresholds** | Uses WooCommerce's own low stock threshold: per product if set, otherwise the store default. Nothing new to configure. |
| 🤫 **Quiet when all is fine** | No email when nothing is low (can be turned off). |

---

## 🚀 Setup

1. **Code → Download ZIP**, then **Plugins → Add New → Upload Plugin** and activate.
2. Go to **WooCommerce → Settings → Stock Alerts**.
3. Add the email addresses and pick how often and at what time. Click **Save and send a report now** to see it right away.

| Setting | Default |
|---|---|
| Send to | Site admin email |
| How often | Every day |
| Send at | 09:00 (site time zone) |
| Include out-of-stock | Yes |
| Skip the email when nothing is low | Yes |
| Admin bar badge | Yes |

Thresholds: set per product in **Product → Inventory → Low stock threshold**, or for the whole store in **WooCommerce → Settings → Products → Inventory**.

> ℹ️ The report is sent by WP-Cron, which runs when your site has visitors. For exact timing on low-traffic sites, set up a real server cron for `wp-cron.php`.

---

## 🧑‍💻 For developers

~~~php
// Ignore products in the "made-to-order" category.
add_filter( 'lsa_items', function ( $items ) {
	return array_filter( $items, function ( $item ) {
		$id = $item['product']->get_parent_id() ?: $item['product']->get_id();
		return ! has_term( 'made-to-order', 'product_cat', $id );
	} );
} );

// Also send to the warehouse.
add_filter( 'lsa_recipients', fn( $to ) => array_merge( $to, array( 'warehouse@example.com' ) ) );
~~~

Functions: `lsa_get_items()`, `lsa_counts()`, `lsa_send_digest( $force )`.
Hooks: `lsa_items`, `lsa_recipients`, `lsa_digest_subject`, `lsa_option_{key}`.

---

## 🧪 Tested

Tested end-to-end on WordPress 6.8 + WooCommerce 10.3 (HPOS on):

- Finds out-of-stock, low by store threshold, low by a product's own threshold, and low **variations**; ignores well-stocked ones
- Out-of-stock first, then lowest stock
- Report scheduled daily at 09:00 site time, and stays at 09:00 after the time zone changes
- Email goes to every valid address (invalid ones skipped), subject "4 products need attention", table with names and SKUs
- Dashboard widget, admin bar "Low stock: 4", products list view with exactly the 4 products
- "Save and send a report now" button works
- After restocking: count drops to 0 and no email is sent

---

## 📁 Files

~~~
low-stock-alert-for-woocommerce/
├── low-stock-alert-for-woocommerce.php
├── includes/
│   ├── functions.php   # Settings, finding low stock, schedule
│   ├── digest.php      # The report email
│   └── admin.php       # Settings tab, dashboard widget, admin bar, products view
├── CHANGELOG.md
└── LICENSE
~~~

## 🗺️ Roadmap

- [ ] CSV export of the low stock list
- [ ] Reorder quantity and supplier per product
- [ ] Telegram / Slack notifications

## 📄 License

[GPL-2.0-or-later](LICENSE)

---

<div align="center">

### 👨‍💻 Built by Md Nazmul Islam (Developer Naz)

[![LinkedIn](https://img.shields.io/badge/LinkedIn-developernaz-0A66C2?style=flat&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/developernaz/)
[![GitHub](https://img.shields.io/badge/GitHub-developer--naz-181717?style=flat&logo=github&logoColor=white)](https://github.com/developer-naz)

More: [Order on WhatsApp](https://github.com/developer-naz/order-on-whatsapp-for-woocommerce) · [BD Delivery Charge](https://github.com/developer-naz/bd-delivery-charge-for-woocommerce) · [Fake Order Blocker](https://github.com/developer-naz/woocommerce-fake-order-blocker)

</div>
