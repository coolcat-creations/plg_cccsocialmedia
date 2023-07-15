# plg_cccsocialmedia

For Joomla 3.10.x and Joomla 4 - PHP 7.4 +

Joomla Plugin to Display Open Graph and Twitter Options in your menu items and articles. The speciality about this plugin is the feature, that it recognizes the user agent and delivers the right open graph image to the various plattforms, as well as th e many fallback options to make sure anything is filled in the og data.

This plugin creates a new tab in your menu items and your articles and enables you to add open graph and twitter data to your page. You can also set up global and/or fallback options in you plugin parameters.

This plugin also does recognize the useragents google, facebook and pinterest and delivers the right open graph image according to the useragent. So if google scrapes an image from your site, google will receive the squared image. If pinterest is checking on your site, it will get the protrait image and if facebook or other agents want to get your open graph image it will get the 1:1.91 ratio image.

The menu parameters overwrite the article parameters and the article parameters overwrite the global paramters.
Means: 
- Priority A - Menu
- Priority B - Article
- Priority C - Global plugin params

To make sure you have at least something in the open graph data the plugin has additional fallbacks:

- if any of the open graph images are empty create a fallback to the article intro image and its alt-text
- if any of the open graph images are empty and the intro image is empty create a fallback to the article fulltext image and its alt-text
- if the open graph title is empty get the page title from menu item. If there is an article title take this one.
- if the open graph or twitter description is empty use the meta description
- if the open graph published time is empty get the creation date of the article if existent

This SEO relevant plugin is also used on https://seo-sommer.de
