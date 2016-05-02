=== WooCommerce CardConnect Payment Gateway ===
Contributors: jle1
Tags: woocommerce, payment, gateway, cardconnect
Requires at least: 3.5
Tested up to: 4.5
Stable tag: 2.0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==

The WooCommerce CardConnect Payment Gateway allows you to accept Visa, MasterCard, American Express and Discover payments in your WordPress WooCommerce store. CardConnect payment processing tokenizes sensitive data, safeguarding your customers from a data breach and removing the burden of PCI compliance.

CardConnect allows customers to checkout with a saved card.  Card details are saved on CardConnect servers and not on your site. The plugin supports the WooCommerce Subscription extension.

Visit [CardConnect](http://cardconnect.com) for more information.

Please note that WooCommerce (v2.1+) must be installed and active.

The WooCommerce Subscriptions extension (v2.x) is fully supported.

== Installation ==

* Upload plugin files to your plugins folder, or install using WordPress built-in Add New Plugin installer;
* Activate the plugin;
* Configure the plugin settings in WooCommerce > Settings > Checkout > Card Connect
* Contact your CardConnect representative for your merchant ID and credentials

== Frequently Asked Questions ==

= Does this plugin require that an SSL certificate be installed? =

It is recommended that you install an SSL certificate on your site for the checkout page, however the plugin does not require it.

= Is there an option for a sandbox account for testing? =

Yes. When you sign-up for a merchant account with CardConnect you will receive credentials for a sandbox account as well as a live account.

= Are there any special requirements needed from my hosting provider? =

You may need to request that your hosting provider open certain ports. Specific instructions will be provided when you activate your CardConnect account.

= Who do I contact if I need assistance? =

For further info or support, contact your CardConnect representative.

= Does this support the WooCommerce Subscriptions extension? =

Yes, we support both v2.x of the Subscriptions extension as well as the older v1.5.x .  We highly recommend that you use v2.x for best results.

= Does this support the WooCommerce Pre-Orders extension? =

Yes.

= Does this support all currencies supported by the WooCommerce store? =

We support all WooCommerce currencies except the Ukranian Hryvnia.

== Changelog ==
= 2.0.2 =
* Fix: Fixed detection of whether the Pre-Orders extension is installed or not.

= 2.0.1 =
* Fix: Improved handling for wp-admin CardConnect checkbox option 'Saved Cards - Allow customers to save payment information.'

= 2.0.0 =
* Major release to fully support the WooCommerce Subscriptions 2.x extension plugin for the WooCommerce store.
* Support for WooCommerce Pre-Orders extension has also been added.

= 1.0.7 =
* Fix: Corrected Merchant account field mapping for addresses

= 1.0.6 =
* Fix: Ensure 'site' field is populated in 'wp-admin > WooCommerce > Settings > Checkout > CardConnect' before performing
  port checks.
* Typo fix in checkout screen.

= 1.0.5 =
* Fix: Issue with error messages covering Card Connect fields.

= 1.0.4 =
* We now check that your server has the required ports open to allow communication with the CardConnect servers.  You'll
see this information at the bottom of your (wp-admin > WooCommerce > Settings > Checkout > CardConnect) settings screen
in the section titled 'Warnings/Messages'.  Refresh this page to re-perform the check.

= 1.0.3 =
* Minor CardConnect API update

= 1.0.2 =
* Minor Bug Fixes

= 1.0.1 =
* Bug Fix: Fixed bug that some users experienced where WooCommerce settings page would be blank white page

= 1.0.0 =
* Public Release
* Bug Fixes
* UI Tweaks

= 0.6.0 =
* New feature: Now integrate with WooCommerce Subscriptions

= 0.5.0 =
* New feature: Allow customer to store payment information on CardConnect servers for easy re-use
* Better tokenization handling
* Allow for template overrides

= 0.4.0 =
* Implement immediate tokenization of credit card number, with improved error feedback
* Allow unique CardConnect site names to be specified in gateway configuration
* Allow customer to supply discrete cardholder name if necessary
* UI Tweaks
* Bug fixes

= 0.1.0 =
* Beta release. Initial functionality includes tokenized transactions, easy toggle between prod/test environments,
and auth only/capture transactions.

== Upgrade Notice ==
= 2.0.0 =
Major release to fully support the WooCommerce Subscriptions 2.x extension plugin for the WooCommerce store.

= 1.0.1 =
Upgrade for bug fixes

= 1.0.0 =
Initial repository version
