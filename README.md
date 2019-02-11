Google Tag Manager extension for MediaWiki
==========================================

This extension adds the Google Tag Manager snippet to every page.
Right now this extension will only work with Kol-Zchut's "Helena" skin,
as it requires the skin's unique hook for after-<body> code insertion
(which isn't available out-of-the-box, for some reason).

## Configuration ##

### Configuration options
  
| Variable                                           | Value | Explanation
|----------------------------------------------------|-------|-------------------
| $wgGoogleTagManagerContainerID                     | null  | Your container ID


### Exemption from tracking
Users with the "noanalytics" right are exempt from tracking, and this will not be
loaded for them. By default only bots have this right.

## Changelog ##

### 0.2.0
Lint the code

### 0.1.0
- Initial version
