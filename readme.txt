=== Drafts for Friends ===
Contributors: ipekarik
Tags: draft, share, link, account
Tested up to: 4.3.1
License: GPLv2 or later

Share your drafts, pending posts and pending review posts to anyone, regardless whether or not they have a username for your blog.

== Description ==
This plugin allows you to share a unique and temporary link to any of your drafts, pending posts and pending review posts, After a preset time period the link will expire, revoking access to shared drafts. Modified from Drafts for Friends originally by Neville Longbottom.

== Installation ==
Plug 'n' play.

1. Upload the plugin folder to your '/wp-content/plugins/ directory
2. Activate the plugin from the Plugins menu in the admin area.
3. Find the plugin under the Posts menu in the admin area

== Changelog ==
// version 2.6
* Added column "Status" in the admin page table, detailing the shared post status
* Added color-coding to "Status" and "Expires in" columns
* Private posts can no longer be shared
* Trashed posts can no longer be shared
* Various plugin security updates and bugfixes

// version 2.5
* Editors and admins can now share other user's posts, not just their own
* Added column "Author" in the admin page table
* Added input form validation
* Added color-coded status messages for all actions
* Various plugin security updates and bugfixes

// version 2.4
* Replaced plain HTML table with standard WP_List_Table output
* Added bulk delete and bulk delete expired features
* Fixed broken translation support.
* Added Croatian translation.

// version 2.3
* Added check if post has already been shared to avoid duplicate entries in the table.
* Added nonce security check to forms and actions.
* Secured files from direct access 
* JS/CSS is now output only on plugin page and nowhere else.
* Increased unique link security while reducing link complexity and length 
* Fixed fetching of all future posts
* Fixed various bugs and PHP warnings and restructured code to be more legible and aligned with WP Codex

// version 2.2
* Added column "Expires in" in the admin page table that displays remaining time to link expiry in human readable format
* Forked from Neville Longbottom