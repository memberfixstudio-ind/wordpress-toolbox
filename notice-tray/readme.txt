=== Notice Tray ===
Contributors: rohansalave
Tags: admin, notices, admin bar, dashboard, clutter
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Moves plugin and theme notices off the top of every admin screen into a tidy tray in the admin bar. Errors stay visible. No settings.

== Description ==

Install a handful of plugins and every wp-admin screen starts with a stack of banners: welcome tours, rating requests, upgrade offers and setup reminders. Notice Tray collects them into a button in the admin bar with a count, so you read them when you choose to.

* **Nothing to set up.** Activate it and the clutter is gone.
* **Problems stay visible.** Errors, "Settings saved", "Post updated" and inline notices stay on the page.
* **Nothing is thrown away.** Notices are moved, not hidden. Links and dismiss buttons keep working, and the count goes down as you dismiss them.
* **Warnings stand out.** The count turns amber when a warning or update notice is waiting, and warnings are listed first.
* **Accessible.** The button announces how many notices are waiting, focus moves into the tray when it opens, and Escape closes it.
* **Each person decides.** "Show notices on the page instead" switches the tray off for you only; a "Tray off" link in the admin bar turns it back on.
* **Stays out of the block editor**, which has its own notice area.

= For developers =

Keep a notice on the page by giving it the `notice-tray-keep` class, or add selectors with the `notice_tray_keep_selectors` filter. Turn the tray off on particular screens with the `notice_tray_enabled` filter. If your script adds notices after the page loads, trigger `jQuery( document ).trigger( 'notice-tray:refresh' )` to update the count.

== Installation ==

1. Upload the `notice-tray` folder to `/wp-content/plugins/`, or upload the ZIP in Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Click "Notices" in the admin bar to see what's waiting.

== Frequently Asked Questions ==

= Where did my notices go? =

Click "Notices" in the admin bar. They are in the tray, with their links and dismiss buttons intact.

= Can I turn it off? =

Yes. Use "Show notices on the page instead" at the bottom of the tray. It only affects your own account.

= Does it store anything? =

Only each person's "tray off" choice, as the `notice_tray_paused` user meta. It is removed when the plugin is deleted.

== Changelog ==

= Unreleased =
* Deleting the plugin now removes the saved per-user "tray off" choice.

= 1.0.0 =
* First release.
