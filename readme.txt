=== Cryptum Checkout ===
Contributors: Blockforce
Tags: cryptocurrency, crypto, checkout, woocommerce, e-commerce, ecommerce, store, payments, gateway, cryptocurrency checkout, blockchain, celo
Requires at least: 5.5
Tested up to: 5.7
Stable tag: 0.0.1
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

This plugin connects your WooCommerce store to the Cryptum Checkout Payment Gateway so you can start accepting cryptocurrencies.



== Description ==


== Installation ==

= Minimum Requirements =
* PHP 5.2 or greater is recommended
* WordPress 4.7 or greater is recommended
* WooCommerce 2.1 or greater is recommended


= Automatic installation =

Automatic installation is the easiest option -- WordPress will handle the file transfer, and you won’t need to leave your web browser.

* To do an automatic install of the Cryptum Checkout WooCommerce Gateway Plugin, log in to your WordPress dashboard, navigate to the Plugins menu, and click “Add New.”

* In the search field type “Cryptum Checkout,” then click “Search Plugins.” Once you’ve found us, you can view details about it such as the point release, rating, and description. Most importantly of course, you can install it by Clicking “Install Now,” and WordPress will take it from there.

* Go to the WooCommerce Section, click Settings, Press the "Payments" Tab at the top, Enable Cryptum Checkout, and press Manage. 

* (See "Setup Connection" section for connecting to our platform.)



= Setup Connection =

In this step we will choose what Cryptocurrencies we want to accept, and create our Cryptum Checkout Connection.

* Now Create a free account at Cryptum, in the Dashboard Press "New Connection" enter your Store and Cryptocurrency Wallet details, save.

* On Cryptum Checkout Dashboard Navigate to API Keys, generate and Copy the API Key.

* Navigate to Installation Scripts > Store Integrations > WooCommerce, Paste the API Key, and click Generate Scripts.

* Copy and Paste all of the Settings you generated into your WooCommerce Gateway Setup Page, Click Save Changes.

* Create a test order and test the integration.



= Manual installation =

In some cases you may have to manually install the plugin, to do so is fairly simple.

* FTP into your webserver, or use the Cpanel File Manager.


* Go into the public_html/your_wordpress_folder/wp-content/plugins folder.


* Upload the zip file.


* Extract the zip file contents into the plugins folder. The extracted files should be located at: public_html/your_wordpress_folder/wp-content/plugins/cryptocurrencycheckout-woocommerce-gateway


* In your Wordpress Admin Dashboard, go to "Plugins" Section, and Click "Installed Plugins", Activate the Cryptum Checkout WooCommerce Gateway.


* Go to the WooCommerce Section, click Settings, Press the "Payments" Tab at the top, Enable Cryptum Checkout, and press Manage.


* (See "Setup Connection" section for connecting to our platform.)



== Screenshots ==



== Changelog ==

= 1.0 =

* Initial testing Complete.
