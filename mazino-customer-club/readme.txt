=== Mazino Customer Club ===
Contributors: mazinograph
Tags: customer club, elementor, sms, membership, loyalty
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Elementor widget for customer club signup plus member management, CSV/Excel export and sms.ir messaging.

== Description ==

Mazino Customer Club adds a fully editable Elementor widget that lets visitors join your customer club by entering their name, mobile number and Jalali (Persian) birth date.

**Features**

* Dedicated Elementor widget: every text, image, label, thank-you message and colour can be edited from the Elementor panel.
* Server-side validation for name, Iranian mobile numbers and Jalali dates, duplicate protection, nonce, honeypot and rate limiting.
* Members screen in wp-admin with search, sorting, pagination, bulk delete, CSV and XLSX export.
* sms.ir integration with two sending modes:
  * Approved template (VERIFY) — works for every account, no dedicated line required.
  * Dedicated line (free text) — for accounts that own a purchased line.
* Single, bulk and scheduled campaigns, recipient groups (all members, birthdays of the month, birthdays today, custom numbers).
* Automatic birthday greetings: every day at the chosen hour the plugin greets members whose Jalali birth date matches today.
* Dynamic placeholders: {name}, {first_name}, {mobile} and {club} (the café or restaurant name set in the settings).
* Campaign history with delivery status, cancel for scheduled sends and credit display.
* Shortcode `[mazino_customer_club]` for sites without Elementor.

**Data ownership**

Member data stays in your own database. Removing the plugin keeps the tables unless `MCC_REMOVE_DATA_ON_UNINSTALL` is defined as true.

== Installation ==

1. Upload the plugin zip through Plugins → Add New → Upload Plugin.
2. Activate the plugin.
3. Add the “Customer Club” widget from the Elementor panel, or use the shortcode.
4. Open “Customer Club → SMS settings” and paste your sms.ir API key.

== Frequently Asked Questions ==

= Does it work without Elementor? =
Yes, use the `[mazino_customer_club]` shortcode.

= Do I need a dedicated SMS line? =
No. Choose the approved-template (VERIFY) mode; a dedicated line is only needed for free-text sending.

= Is the birth date required? =
It is configurable per widget. Without a birth date the member is simply skipped by the birthday automation.

= Can I export the members? =
Yes, as CSV or XLSX from the members screen.

== Changelog ==

= 1.5.0 =
* Code quality release: all Plugin Check errors resolved (prepared statements with %i, escaped exception messages, sanitised request data, plugin headers and readme).
* Removed set_time_limit calls and manual textdomain loading.

= 1.4.0 =
* Single-recipient “Send SMS” button and modal in the members table.
* Automatic template parameters for member name, first name, mobile and business name.
* New “Café / restaurant name” setting.

= 1.3.0 =
* Approved template (VERIFY) sending mode with background queue and local scheduling.
* Template picker with parameter fields and preview.

= 1.2.0 =
* sms.ir integration: single, bulk and scheduled sending, birthday automation, campaign history.

= 1.1.0 =
* Members list rendered directly in the admin screen; About page with branding.

= 1.0.0 =
* Initial release.
