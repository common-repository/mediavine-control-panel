=== Mediavine Control Panel ===
Contributors: mediavine
Donate link: https://www.mediavine.com
Tags: advertising, mediavine
Requires at least: 5.2
Tested up to: 6.6
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Manage your ads, analytics and more with our lightweight plugin!

== Description ==

Mediavine Control Panel connects your WordPress blog to your Mediavine account. Simply install the plugin, provide your mediavine account name, and take advantage of our cutting edge features

* Easy to use interface makes it simple to adjust your settings
* Keep your ads.txt up to date via redirecting to Mediavine's servers or writing to a publisher's filesystem
* Provide content creation tools for placing content like videos and playlists from your Dashboard into pages, posts, and categories
* Integrating with third party Wordpress plugins (like WP Rocket) that may be preventing valid ad placement or ad loading
* Assist with the MCM approval process via Launch Mode
* Automatically generate your video sitemap
* Inserting ads on your Web Stories content

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/mediavine-control-panel` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->Mediavine Control Panel screen to configure the plugin

== Frequently Asked Questions ==

= Where can I find support articles =
[Visit our Mediavine Help site](https://help.mediavine.com/)

= How can I contact Mediavine support? =
On the Settings->Mediavine Control Panel screen, you will find an icon to the bottom right that will contact the Mediavine support team. You can also email Mediavine at [publishers@mediavine.com](mailto:publishers@mediavine.com).

= Where do I report security bugs found in this plugin? =
Please report security bugs found in the source code of the
Mediavine Control Panel plugin through the [Patchstack
Vulnerability Disclosure Program](https://patchstack.com/database/vdp/mediavine-control-panel). The
Patchstack team will assist you with verification, CVE assignment, and
notify the developers of this plugin.

# Security Policy
## Reporting Security Bugs
Please report security bugs found in the
Mediavine Control Panel plugin's source code through the
[Patchstack Vulnerability Disclosure
Program](https://patchstack.com/database/vdp/mediavine-control-panel). The Patchstack team will
assist you with verification, CVE assignment, and notify the
developers of this plugin.

== Screenshots ==

== Changelog ==

= 2.10.5 =

- FIXED: Improved shortcode attribute sanitization to address potential XSS security vulnerability.

= 2.10.4 =

- CHANGED: Added support for publishers to use an offering code that doesn't resolve to a valid domain.

= 2.10.3 =

- CHANGED: Improved form validation for publishers refreshing launch mode status.

= 2.10.2 =

- FIXED: Resolved an issue where some publishers would have their "Include Script Wrapper" value flip to "Exclude Script Wrapper".

= 2.10.1 =

- FIXED: Updated video upload credentials.

= 2.10.0 =

- NEW: MCP 2.10.0 now requires a minimum PHP version of 7.3 to run.
- NEW: Improved onboarding of MCP sites by optionally automatically adding the Google Publisher Tag (GPT) verification code snippet to sites in Launch Mode.
- NEW: Added support for PubNation publishers to use MCP for their sites on WordPress.
- CHANGED: Improved the logic for handling ads.txt files. Validation and verification are improved after plugin or core updates.
- CHANGED: Adjusted the code to make sure all MCP site-specific settings are cleared when changing the site ID.
- REMOVED: Took out the “Do Not Optimize Placement” and “Do Not Autoplay nor Optimize Placement” controls from video and playlist settings as part of sunsetting Autoplay functionality.
- REMOVED: Changed the UI for embedded videos and playlists to remove volume settings.
- FIXED: Resolved an issue where the Mediavine Help button didn’t always appear on the MCP Settings page.
- FIXED: Corrected a problem where changing the selected aspect ratio for videos in the Classic Editor would have the UI revert back to showing 16:9. Videos would actually change their ratio, but the UI would still show 16:9.
- FIXED: Added code to ensure proper styling of MCP blocks when the Classic Editor plugin is active.
- FIXED: Resolved an issue where publishers couldn’t edit or delete a playlist while using the Classic Editor.
- FIXED: Added code to resolve an instance where a site might not update properly during an upgrade.
- FIXED: Resolved an issue where the “Exclude Script Wrapper” setting could switch to “Include Script Wrapper” after an update.

= 2.9.0 =
- Updated to require PHP 7.1+
- Updated to require WP 5.2+
- Removed AMP integrations (Web Stories integration is not affected)
- Improved how ads.txt syncing method is checked
- Improved validation and experience for publishers using the "write" method for syncing ads.txt
- Added ability to force recheck of the ads.txt method
- Improved Launch Mode checking and validation
- Enhanced security for settings form
- Resolved issue with content editing when using Classic Editor
- Optimized front end library filesize
- Fixed deprecation warnings in newer versions of Wordpress
- Improved PHP 8.x support
- Removed jQuery as a dependency for settings form
- Cleaned up internal option names used throughout to follow a standardized pattern
- Improved process when migrating between plugin versions
- Added new hooks `mcp_pre_migrate_to_latest_version` and `mcp_post_migrate_to_latest_version`
- Refined Ad Settings block messaging when ads are enabled
- Added additional debug information to help with troubleshooting settings and WP Cron scheduled tasks
- Improved overall code stability and best practices

= 2.8.0 =
* FEATURE: Add MCM Approval workflow.
* FEATURE: Add support-only option to override Launch Mode.
* FIX: Potential console errors while using the WordPress Dashboard.

= 2.7.0 =
* FEATURE: Adds automated "launch mode" for new publishers in process of being verified.

= 2.6.7 =
* FIX: Fixes support for selectively disabling JSON-LD schema.

= 2.6.6 =
* FIX: Corrects a build error in the 2.6.4 release.

= 2.6.4 =
* FIX: Adds better logic for determining if the Ads.txt redirect method should be used
* FIX: Removes featured category videos from displaying on category archive pages
* FIX: Removes MCP authorization admin notice that wasn't clearing after authorization
* FIX: Featured video checks for more legacy video embeds

= 2.6.3 =
* FIX: Fixes conflict where MCP was breaking redirects from Redirection plugin

= 2.6.2 =
* FIX: Fixes conflict with Ads.txt manager plugin
* FIX: Fixes conflict with Redirection plugin and Ads.txt files

= 2.6.1 =
* FIX: Fixes issue where Ads.txt files were not redirecting properly when the WordPress query wasn't standard

= 2.6.0 =
* FEATURE: Adds the ability to disable ads on a per post/page basis, with an option to have an expires date
* FEATURE: Adds AMP Web stories ad support
* FEATURE: Ads.txt files are now controlled with a 301 redirect to Mediavine's servers by default. The old method of writing a file to the domain's root still exists, but as a fallback that can be filtered.
* FIX: Removes deprecation notice on sites running newer versions of AMP
* FIX: Featured video now checks for Mediavine Videos within WP Tasty recipe cards and for legacy video embeds
* FIX: Adjusts featured video logic to not display on protected posts before the password has been entered

= 2.5.0 =
* FEATURE: Adds Playlist content blocks
* FEATURE: Adds Featured Video or Playlist support to Categories
* FIX: Outputs placeholder if admin ads are disabled and user has admin rights
* FIX: Fixes compatibility issue with plugins such as EditorsKit that modify Gutenberg shortcodes
* FIX: Removes deprecation notice on sites running newer versions of AMP
* FIX: Non-admins can authenticate with MCP for video adding

= 2.4.0 =
* FEATURE: Adds the ability to connect to the Mediavine Dashboard and directly upload videos to Mediavine
* FEATURE: Adds Intercom button to admin
* FEATURE: Adds class `mv-content-wrapper` to post & page wrapper for ad targeting.
* FEATURE: Provides an update notice about an upcoming minimum requirement of WP 5.2
* FIX: Removes unnecessary script load from AMP pages
* FIX: Fixes Analytics code on AMP pages
* FIX: "Use current aspect ratio" for videos no longer forces to 16:9
* COSMETIC: Adds new UI to match updated Mediavine Dashboard

= 2.3.0 =
* FEATURE: Updates to the latest markup for video embeds

= 2.2.5 =
* ENHANCEMENT: Remove rogue code from a previous release

= 2.2.4 =
* ENHANCEMENT: HTTPS links always used for both script wrapper and video embeds

= 2.2.3 =
* FIX: Revert back to 2.2.1 fixing an conflict between optimization plugins and ad display

= 2.2.2 =
* ENHANCEMENT: HTTPS links always used for both script wrapper and video embeds
* ENHANCEMENT: Local model override available for future integration with Trellis

= 2.2.1 =
* ENHANCEMENT: Adds ability to disable JSON-LD schema output for videos
* ENHANCEMENT: Adds ability to disable video sitemap url
* FIX: Old video shortcodes can switch to between visual and text views on Classic editor
* FIX: Inserted video shortcodes have normalized property values
* FIX: Prevent PHP 5.3 versions from giving a fatal error

= 2.2.0 =
* FEATURE: Adds support for Video Sitemaps
* FIX: Videos now use custom thumbnails when set in Mediavine Dashboard
* FIX: Video button is properly aligned in Classic Editor
* FIX: Backward compatibility for shortcodes using "sticky" attribute

= 2.1.2 =
* ENHANCEMENT: Adds a clearer description to the Include Script Wrapper setting

= 2.1.1 =
* FIX: Fixes issue with new video placement logic

= 2.1.0 =
* FEATURE: Add support for new video placement settings
* FEATURE: Intercom chat will display history
* FIX: Fixes issue with incorrect ID being saved when reinserting a video
* FIX: Fixes an issue with a class sometimes recursively calling itself
* FIX: Prevent videos from rendering inside Gutenberg or Relevanssi search results
* CHANGE: New sites will include script wrapper by default

= 2.0.1 =
* FIX: Fixes issue with Cloudflare 414 errors

= 2.0.0 =
* FEATURE: Incorporated Publisher Identity Service v2
* FEATURE: Adds pagination to videos

= 1.9.12 =
* FIX: Compatibility with official AMP 1.0 plugin's Classic mode

= 1.9.11 =
* FIX: Hotfix issue with potential PHP fatal when checking for AMP

= 1.9.10 =
* FIX: Compatibility with official AMP plugin's template modes
* FIX: Prevent issue where delete buttons in Create would not work
* FIX: Prevent issue where images in TinyMCE could not be edited

= 1.9.9 =
* FIX: Marking videos as sticky will now actually make them sticky
* FIX: More compatibility with official AMP 1.0 plugin

= 1.9.8 =
* FIX: Compatibility with official AMP 1.0 plugin

= 1.9.7 =
* ENHANCEMENT: Adds Gutenberg support

= 1.9.6 =
* FIX: Issue with videos sometimes not displaying on posts
* ENHANCEMENT: Add target class to video shortcode render

= 1.9.5 =
* ENHANCEMENT: Only enable ads.txt cron job if site_id exists
* FIX: `[mv_video]` shortcode now compatible with Jetpack shortcodes
* FIX: Prevents re-enabling ads.txt on activation if it was previously disabled manually
* FIX: Disable ads for admin users with Page Builder utilities activated on the site.

= 1.9.4 =
* FEATURE: Adds targeted ads and GDPR consent form for AMP for WP
* FIX: Gracefully goes into legacy mode if on older versions of WordPress (4.4 and below)
* FIX: Prefixes variables to prevent plugin conflicts using global variables

= 1.9.3 =
* Improves TinyMCE stability
* Improves compatibility with Create

= 1.9.2 =
* Improves shortcode render on non-sticky cards
* Improves database table creation fallback

= 1.9.1 =
* Improves settings table creation
* Provides fallback if table cannot be created
* Improves script to shortcode replacement

= 1.9.0 =
* Login with Mediavine
* Insert Mediavine videos straight to your Editor without visiting your Dashboard
* Using this tool will eliminate Mediavine Script Tag issues

= 1.8.4 =
* Remove non-EU countries from AMP Geo
* Fix bug with AMP for WP validation
* Block script wrapper from customizer

= 1.8.3 =
* Fix AMP Bug
* Improves compatibility with other AMP plugins

= 1.8.2 =
* Adds GDPR Support for AMP Pages

= 1.8.1 =
* Improves file path reliability

= 1.8.0 =
* Improves ads.txt reliability

= 1.7.9 =
* Fix AMP Bug

= 1.7.8 =
* Removes Ads.txt mismatch notifications

= 1.7.7 =
* Adds Intercom button to settings page

= 1.7.6 =
* Removes notifications regarding Ads.txt mismatch to improve user experience

= 1.7.5 =
* Adds Ads.txt autoupdate on first out-of-date check
* Adds support for MVCP_ROOT_PATH and MVCP_ROOT_URL config defines
* Adds better failed Ads.txt update notifications
* Adds pre-activation hook to prevent version incompatibility
* Fixes Ads.txt update problems on some hosts

= 1.7.4 =
* Adds option to disable Automatic Ads.txt syncing
* Fixes a bug saying ads.txt updated when it didn't

= 1.7.3 =
* Fixes blank Ads.txt files

= 1.7.2 =
* Fixes AMP problems on some hosts
* Internal build only

= 1.7.1 =
* Fixes issues relating to AMP for WP

= 1.7.0 =
* Adds Ad.txt sync
* Removes Upgrade CSP option
* Adds block CSP Option

= 1.6.0 =
* Adds google ad fraud protection

= 1.5.2 =
* Removes CRON Cleanup

= 1.5.0 =
* Rolls back to 1.3.9

= 1.4.0 =
* BAD VERSION

= 1.3.9 =
* Minor bugfixes

= 1.3.8 =
* General Bugfixes & Improvements

= 1.3.7 =
* Adds AMP ad settings

= 1.3.6 =
* Minor Bugfixes with AMP Video

= 1.3.5 =
* Adds AMP Backout option

= 1.3.4 =
* Settings page improvements

= 1.3.3 =
* Fixes additional conflicts with AMP for WP

= 1.3.2 =
* Bugfixes & Improvements

= 1.3.1 =
* Fixes a bug that could cause the plugin to crash

= 1.3.0 =
* Adds AMP support for Mediavine Videos
* Fixes a fatal conflict with AMP For WP
* Fixes an instance where AMP for WP could cause less than optimal search results
* Adds settings button to plugin list

= 1.2.0 =
* Adds Secure Content Settings
* Fixes a bug where the script wrapper would sometimes appear low in the page

= 1.1.1 =
* Fixed a bug that was preventing some settings from saving

= 1.1.0 =
* Fixed a bug that was preventing some settings from saving

= 1.0.1 =
* Fixes a bug that was preventing some settings from saving

= 1.0 =
* Initial Plugin Build

== Upgrade Notice ==

= 2.6.4 =
* This update removes featured category videos from appearing on category archive pages

= 2.6.3 =
* This update fixes a conflict where MCP was breaking redirects from Redirection plugin

= 2.6.2 =
* This update fixes a conflict with Ads.txt redirects and the Ads.txt Manager plugin

= 2.6.1 =
* This update fixes an issue with Ads.txt redirects

= 2.6.0 =
* This update adds ads.txt redirect support, AMP web story support, and per post/page ad settings

= 2.5.0 =
* This update adds playlists blocks as well as featured video/playlist support to categories

= 2.4.0 =
* This update adds the ability to upload videos to Mediavine

= 2.3.0 =
* This update uses the newest markup for video embeds

= 2.2.5 =
* This update fixes an issue with async tags on the script wrapper

= 2.2.4 =
* This update now uses HTTPS links for both the Mediavine script wrapper and video embeds

= 2.2.3 =
* Reverts back to 2.2.1 fixing an conflict between optimization plugins and ad display

= 2.2.2 =
* This update now uses HTTPS links for both the Mediavine script wrapper and video embeds

= 2.1.0 =
* This update fixes an issue with the new video settings placement logic

= 2.0.1 =
* This update improves Cloudflare compatibility

= 2.0.0 =
* This update improves the Mediavine login experience

= 1.9.12 =
* This update provides better AMP compatibility

= 1.9.11 =
* This update fixes a potential PHP fatal error

= 1.9.10 =
* This update provides better AMP compatibility

= 1.9.9 =
* This update improves video support and gives better AMP compatibility

= 1.9.8 =
* This update fixes compatibility with official AMP 1.0 plugin

= 1.9.7 =
* Adds Gutenberg support for videos

= 1.9.6 =
* This update improves video display on posts

= 1.9.5 =
* This update improves compatibility with Jetpack

= 1.9.4 =
* This update improves support with older versions of WordPress and potential plugin conflicts
* Also provides better AMP for WP support

= 1.9.3 =
* This update improves reliability with video features in the editor

= 1.9.2 =
* This update improves reliability with video features

= 1.9.1 =
* This update improves reliability with video features

= 1.9.0 =
* This update adds the ability to login with Mediavine and easily add videos to your editor

= 1.8.4 =
* This update improves AMP plugin compatibility and GDPR support

= 1.8.3 =
* Fix AMP Bug
* Improves compatibility with other AMP plugins

= 1.8.2 =
* Adds GDPR Support for AMP Pages

= 1.8.1 =
* Improves file path reliability

= 1.8.0 =
* This update improves ads.txt reliability

= 1.7.8 =
* This update includes a bug fix to AMP

= 1.7.8 =
* This update includes general user experience enhancements

= 1.7.7 =
* Increases ease of contacting Mediavine support

= 1.7.6 =
* This update includes general user experience enhancements

= 1.7.4 =
* This update includes performance enhancements and improvements to our ads.txt manager.

= 1.7.3 =
* General bugfixes & performance Enhancements
* Enhances Ads.txt features

= 1.7.2 =
* General bugfixes & performance Enhancements

= 1.7.1 =
* Fixes issues relating to AMP for WP

= 1.7.0 =
* Fixes a conflict with AMP for WP
* General Bugfixes and Improvements

= 1.6.0 =
* Adds google ad fraud protection

= 1.5.2 =
* Minor bugfix for users unable to upgrade to 1.5.1

= 1.3.9 =
* Minor bugfixes & Language improvements

= 1.3.8 =
* General Bugfixes & Improvements

= 1.3.7 =
* Adds settings for AMP Ad Units

= 1.3.6 =
* Fixes a bug with some videos not showing up in AMP

= 1.3.5 =
* Minor bugfixes & Improvements

= 1.3.4 =
* Improves Settings Page

= 1.3.3 =
* Fixes additional conflicts with AMP for WP

= 1.3.2 =
* Critical Bugfixes & Improvements

= 1.3.1 =
* Fixes a bug that could cause the plugin to crash

= 1.3.0 =
* Adds AMP support for Mediavine Videos & General Plugin Improvements

= 1.2.0 =
* Adds Security Enhancements & General Plugin Improvements

= 1.1.1 =
* Fixed a bug that was preventing some settings from saving

= 1.1.0 =
* Fixed a bug that was preventing some settings from saving

= 1.0.1 =
* Fixed a bug that was preventing some settings from saving

= 1.0 =
* Initial Plugin Build
