=== Session Watch ===
Contributors: rohansalave
Tags: sessions, security, users, logout, membership
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See who is signed in, on which devices and from where. Spot logins shared across IP addresses and sign out one device or everyone else.

== Description ==

WordPress keeps a list of every browser each person is logged in on, but only shows it to that person. Session Watch adds Users > Signed in, a screen that lists every active session on the site and lets admins end them.

* **Nothing to set up.** No settings page, and the plugin stores nothing of its own.
* **Readable at a glance.** Device ("Safari on iOS"), IP address, login time and expiry for every session.
* **Shared logins stand out.** Accounts signed in from several IP addresses are flagged and can be filtered.
* **End exactly what you mean.** One device, one person everywhere, or everyone except you.
* **You can't lock yourself out.** Your current browser is never ended.
* **Users list column** with each person's session count.
* **Fast on big sites.** One query reads every session.

= For developers =

Filters: `session_watch_capability`, `session_watch_ip_threshold`, `session_watch_device`. Action: `session_watch_sessions_ended` ( $user_id, $count, $scope ).

== Installation ==

1. Upload the `session-watch` folder to `/wp-content/plugins/`, or upload the ZIP in Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Go to Users > Signed in.

== Frequently Asked Questions ==

= Does "several IP addresses" mean an account is shared? =

Not always. Phones switch between Wi-Fi and mobile data, and VPNs change addresses. Treat it as a reason to look closer.

= Who can see the screen? =

Users with the `edit_users` capability (administrators, or super admins on multisite). Change it with the `session_watch_capability` filter.

= Does it work with plugins that store sessions elsewhere? =

No. If another plugin replaces WordPress's session storage, Session Watch shows a notice instead of reading or changing it.

== Changelog ==

= 1.0.0 =
* First release.
