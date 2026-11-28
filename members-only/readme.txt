=== Members Only ===
Contributors: rohansalave
Tags: block, logged in, restrict content, memberpress, learndash
Requires at least: 6.5
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A block that shows its content only to signed-in or signed-out visitors, chosen roles, MemberPress members or LearnDash students.

== Description ==

Members Only is a container block. Put any blocks inside it and choose who sees them. Everyone else sees a short message with a Log in button that returns them to the page, or nothing at all.

* **Works on activation.** No settings page.
* **Audiences:** signed-in visitors, signed-out visitors, chosen roles, MemberPress memberships and LearnDash courses (when those plugins are active).
* **Clear next step** for people who can't see the section.
* **Editors still see hidden sections** on the front end, labelled with who they are shown to.
* **Fails closed** if MemberPress or LearnDash is deactivated, and the check runs in PHP so hidden blocks never reach the page source.

= For developers =

Filters: `members_only_can_view` ( $can, $args, $user_id ), `members_only_show_to_editors` ( $show, $args, $post_id ), `members_only_prompt_html` ( $html, $args, $user_id ).

== Installation ==

1. Upload the `members-only` folder to `/wp-content/plugins/`, or upload the zipped folder in Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Add the Members Only block in the block editor.

== Changelog ==

= 1.0.0 =
* First release.
