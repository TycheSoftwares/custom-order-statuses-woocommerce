=== Custom Order Status for WooCommerce ===
Contributors: tychesoftwares
Tags: woocommerce, order status, woo commerce, custom status
Requires at least: 4.4
Tested up to: 5.8
Stable tag: trunk
Requires PHP: 5.6
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Add custom order statuses to WooCommerce.

== Description ==

This plugin lets you add [custom order statuses](https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/?utm_source=wprepo&utm_medium=topprolink&utm_campaign=CustomStatus) to WooCommerce. When adding status, you can set:

* Custom status **slug**.
* Custom status **label**.
* Custom status **icon**.
* Custom status **icon and column color**.

### Check out the PRO version of [Custom Order Status for WooCommerce plugin](https://www.tychesoftwares.com/store/premium-plugins/custom-order-status-woocommerce/?utm_source=wprepo&utm_medium=prolink&utm_campaign=CustomStatus).

Added custom statuses can be added to admin order list **bulk actions** and to admin **reports**.

### Some of our Pro plugins

1. **[Abandoned Cart Pro for WooCommerce](https://www.tychesoftwares.com/store/premium-plugins/woocommerce-abandoned-cart-pro/?utm_source=wprepo&utm_medium=link&utm_campaign=CustomStatus "Abandoned Cart Pro for WooCommerce")**

2. **[Booking & Appointment Plugin for WooCommerce](https://www.tychesoftwares.com/store/premium-plugins/woocommerce-booking-plugin/?utm_source=wprepo&utm_medium=link&utm_campaign=CustomStatus "Booking & Appointment Plugin for WooCommerce")**

3. **[Order Delivery Date Pro for WooCommerce](https://www.tychesoftwares.com/store/premium-plugins/order-delivery-date-for-woocommerce-pro-21/?utm_source=wprepo&utm_medium=link&utm_campaign=CustomStatus "Order Delivery Date Pro for WooCommerce")**

4. **[Product Delivery Date Pro for WooCommerce](https://www.tychesoftwares.com/store/premium-plugins/product-delivery-date-pro-for-woocommerce/?utm_source=wprepo&utm_medium=link&utm_campaign=CustomStatus "Product Delivery Date Pro for WooCommerce")**

5. **[Deposits For WooCommerce](https://www.tychesoftwares.com/store/premium-plugins/deposits-for-woocommerce/?utm_source=wprepo&utm_medium=link&utm_campaign=CustomStatus "Deposits For WooCommerce")**

6. **[Payment Gateway Based Fees and Discounts for WooCommerce - Pro](https://www.tychesoftwares.com/store/premium-plugins/payment-gateway-based-fees-and-discounts-for-woocommerce-plugin/?utm_source=wprepo&utm_medium=link&utm_campaign=CustomStatus "Payment Gateway Based Fees and Discounts for WooCommerce - Pro")**

7. **[Custom Order Numbers for WooCommerce - Pro](https://www.tychesoftwares.com/store/premium-plugins/custom-order-numbers-woocommerce/?utm_source=wprepo&utm_medium=link&utm_campaign=CustomStatus "Custom Order Numbers for WooCommerce - Pro")**

8. **[Product Input Fields for WooCommerce - Pro](https://www.tychesoftwares.com/store/premium-plugins/product-input-fields-for-woocommerce/?utm_source=wprepo&utm_medium=link&utm_campaign=WCPGBasedFees "Product Input Fields for WooCommerce - Pro")**

9. **[Call for Price for WooCommerce - Pro](https://www.tychesoftwares.com/store/premium-plugins/woocommerce-call-for-price-plugin/?utm_source=wprepo&utm_medium=link&utm_campaign=WCPGBasedFees "Call for Price for WooCommerce - Pro")**

10. **[Price based on User Role for WooCommerce - Pro](https://www.tychesoftwares.com/store/premium-plugins/price-user-role-woocommerce/?utm_source=wprepo&utm_medium=link&utm_campaign=WCPGBasedFees "Price based on User Role for WooCommerce - Pro")**

11. **[Currency per Product for WooCommerce - Pro](https://www.tychesoftwares.com/store/premium-plugins/currency-per-product-for-woocommerce/?utm_source=wprepo&utm_medium=link&utm_campaign=WCPGBasedFees "Currency per Product for WooCommerce - Pro")**

### Some of our other free plugins

1. **[Abandoned Cart for WooCommerce](https://wordpress.org/plugins/woocommerce-abandoned-cart/ "Abandoned Cart for WooCommerce")**

2. **[Order Delivery Date for WooCommerce - Lite](https://wordpress.org/plugins/order-delivery-date-for-woocommerce/ "Order Delivery Date for WooCommerce - Lite")**

3. **[Product Delivery Date for WooCommerce - Lite](https://wordpress.org/plugins/product-delivery-date-for-woocommerce-lite/ "Product Delivery Date for WooCommerce")**

4. **[Payment Gateway Based Fees and Discounts for WooCommerce](https://wordpress.org/plugins/checkout-fees-for-woocommerce/ "Payment Gateway Based Fees and Discounts for WooCommerce")**

5. **[Custom Order Numbers for WooCommerce](https://wordpress.org/plugins/custom-order-numbers-for-woocommerce/ "Custom Order Numbers for WooCommerce")**

6. **[Product Input Fields for WooCommerce](https://wordpress.org/plugins/product-input-fields-for-woocommerce/ "Product Input Fields for WooCommerce")**

7. **[Call for Price for WooCommerce](https://wordpress.org/plugins/woocommerce-call-for-price/ "Call for Price for WooCommerce")**

8. **[Price based on User Role for WooCommerce](https://wordpress.org/plugins/price-by-user-role-for-woocommerce/ "Price based on User Role for WooCommerce")**

9. **[Currency per Product for WooCommerce](https://wordpress.org/plugins/currency-per-product-for-woocommerce/ "Currency per Product for WooCommerce")**

== Installation ==

1. Upload the entire plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Start by visiting plugin settings at "WooCommerce > Settings > Custom Order Status".

== Screenshots ==

1. Custom order status tool.
2. Order with custom status.

== Changelog ==

= 2.0.3 - 14/09/2021 = 
* Enhancement - Added an option to select the default status for Cheque and Paypal payment methods.
* Fix - After migration, slugs were coming empty in some site. This is fixed now.
* Fix - When slug in custom order status starts with wc- then order status don't gets changed to that custom order status. This is fixed now.
* Fix - Text color for the custom order status was coming on the orders page even if the settings for enabling color option is kept disabled. This is fixed now.

= 2.0.2 - 09/04/2021 = 
* Fix - Order status was getting changed when we refresh the Thankyou page. This has been fixed.
* Fix - When we change the slug of the custom order status, orders having that custom status was not seen on the orders page. This has been fixed.

= 2.0.1 - 01/12/2020 = 
* Enhancement - Added new setting "Enable color" to show background color for the statuses in the order page.
* Fix - When we click on the "Custom Order Status Tool" link from the plugin, it shows an error. This has been fixed.
* Fix - After deleting the plugin old data does not get removed. This has been fixed.
* Fix - Orders are getting deleted which has the custom order status when the plugin is deleted. This has been fixed.
* Fix - The status is not getting changed to the custom one when the status is created with 3 words. This has been fixed.
* Fix - Added icons to the labels. This has been fixed.

= 2.0.0 - 05/11/2020 = 
* Feature - Added Custom Post Type for custom statuses with the support of previous tool page.
* Feature - Added Global level email function to send the email to customer & admin on change of custom order status.

= 1.4.11 - 10/09/2020 = 
* Updated compatibility with WordPress 5.5
* Updated compatibility with WC 4.5

= 1.4.9 - 19/03/2020 = 
* Updated compatibility with WC 4.0.0

= 1.4.8 - 23/09/2019 = 
* Made the plugin code compliant with WPCS standards.

= 1.4.7 - 27/03/2019 =
* Added uninstall.php to ensure the plugin settings are removed from the DB when the plugin is deleted.
* Added check to ensure slugs from WC core cannot be added as custom statuses. This has been done to avoid conflicts.
* Applied some bug fixes to ensure the plugin is in sync with the PRO version.

= 1.4.6 - 16/11/2018 =
* Author name and URL updated due to handover of the plugins.

= 1.4.5 - 31/10/2018 =
* Compatibility with WooCommerce 3.5.0 tested.

= 1.4.4 - 16/10/2018 =
* Feature - Emails - Email content - `{order_details}` replaced value added.
* Feature - Default order status - "Default order status for BACS / COD" options added (instead of forcing BACS and COD to "default order status", as was added in previous plugin version 1.4.3).

= 1.4.3 - 15/10/2018 =
* Dev - Default order status - Forcing BACS and COD payment gateways to "default order status".

= 1.4.2 - 27/09/2018 =
* Dev - WPML / Polylang plugins compatibility (`wpml-config.xml` file) added.

= 1.4.1 - 23/09/2018 =
* Feature - "Add custom statuses to admin order preview action buttons" option added.
* Dev - Code refactoring.
* Dev - Minor admin settings restyling.

= 1.4.0 - 03/09/2018 =
* Feature - "Emails" section added.
* Feature - "Make custom status orders paid" option added.
* Dev - Code refactoring.
* Dev - Admin settings divided into sections, restyled and descriptions updated.
* Dev - "Enable plugin" option removed.

= 1.3.5 - 22/06/2018 =
* Feature - "Make custom status orders editable" option added.
* Dev - Plugin URI updated to wpfactory.com.
* Dev - Settings are saved as main class property.

= 1.3.4 - 24/05/2018 =
* Dev - "Advanced: Filters priority" option added.

= 1.3.3 - 15/05/2018 =
* Dev - "Text Color" option added.

= 1.3.2 - 15/05/2018 =
* Dev - "Enable Colors in Status Column" option added.
* Dev - "WC tested up to" added to the plugin header.

= 1.3.1 - 10/05/2017 =
* Fix - `Too few arguments to function Alg_WC_Custom_Order_Statuses_Settings_Section::get_settings()` fixed.

= 1.3.0 - 30/04/2017 =
* Dev - WooCommerce 3.x.x compatibility - Order ID.
* Dev - Custom Order Status Tool - Sanitizing slug before adding new status.
* Dev - Custom Order Status Tool - "Delete with fallback" separate button added. Simple "Delete" button now deletes statuses without any fallback.
* Dev - Custom Order Status Tool - "Edit" functionality moved from Pro to free version.
* Tweak - readme.txt and plugin header updated.
* Tweak - Custom Order Status Tool - Restyled.
* Tweak - Custom Order Status Tool - Code refactoring.
* Tweak - Link changed from `coder.fm` to `wpcodefactory.com`.

= 1.2.1 - 23/01/2017 =
* Dev - "Reset settings" button added.
* Tweak - readme.txt fixed.

= 1.2.0 - 17/01/2017 =
* Fix - Tool - Add - Checking for duplicate default WooCommerce status added.
* Dev - Tool - "Edit" custom status button added.
* Dev - Fallback status on delete.
* Dev - "Add Custom Statuses to Admin Order List Action Buttons" options added.
* Dev - Extended (paid) version added.
* Tweak - Plugin "Tags" updated.

= 1.1.0 - 14/12/2016 =
* Fix - `load_plugin_textdomain()` moved from `init` hook to constructor.
* Fix - All `get_option` calls have default value now.
* Dev - Language (POT) file added. Domain 'custom-order-statuses-for-woocommerce' changed to 'custom-order-statuses-woocommerce'.
* Dev - Bulk actions added in proper way for WordPress version >= 4.7.
* Tweak - Donate link updated.

= 1.0.0 - 12/11/2016 =
* Initial Release.

== Upgrade Notice ==

= 1.0.0 =
This is the first release of the plugin.
