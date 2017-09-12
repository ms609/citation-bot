[![Build Status](https://travis-ci.org/ms609/citation-bot.svg?branch=development)](https://travis-ci.org/ms609/citation-bot)
[![Project Status: Inactive - The project has reached a stable, usable state but is no longer being actively developed; support/maintenance will be provided as time allows.](http://www.repostatus.org/badges/latest/inactive.svg)](http://www.repostatus.org/#inactive)

# Citation bot

## Github repository details
There are two main branches of the bot: the **master** code is (should be) implemented at https://tools.wmflabs.org/citations/doibot.php, whereas the **development** branch is implemented at https://tools.wmflabs.org/citations-dev/doibot.php .  Edits should be made first to the development
branch, then – once fully tested – pulled to the master branch.

## Overview

This is some basic documentation about what this bot is and how some of the parts connect.

This is more properly a bot-gadget-tool combination. The parts are:

* DOIBot, found in doibot.html (web frontend) and doibot.php (information is
  POSTed to this and it does the citation expansion; backend). This automatically
  posts a new page revision with expanded citations and thus requires a bot account.
  All activity takes place on Tool Labs.
* Citation expander (:en:Mediawiki:Gadget-citations.js) + gadgetapi.php. This
  is comprises an Ajax front-end in the on-wiki gadget and a PHP backend API.

Bugs and requested changes are listed here: https://en.wikipedia.org/wiki/User_talk:Citation_bot .

## Structure

Basic structure of a Citation bot script:
* define configuration constants (for instance, `html_output` will allow or suppress
  buffered output)
* require `expandFns.php`, which will set up the rest of the needed functions
* use Page functions to fetch/expand/post the page's text


A quick tour of the main files:
* `credentials/doibot.login`: on-wiki login credentials
* `Snoopy.class.php`: 2000s-era http client/scraper. The scraper functions are
   not really used here and it could probably be fairly easily replaced with an
   updated library or a dedicated MediaWiki API client libary. Note that it
   appears to use curl only for https, so the path to curl on Labs must be
   correct or the bot will fail to log in because the request can't reach the
   server.
* `constants.php`: constants defined
* `wikifunctions.php`: functions related to Wikipedia ineractions, including some marked
   as "untested".
* `DOItools.php`: defines `$bot` (the Snoopy instance) and Crossref-related functions
* `expandFns.php`: sets up needed functions and global variables, requires most
  of the other files listed here
* `credentials/crossref.login` allows crossref searches.
* `logIn.php`: Logs the bot in to Wikipedia servers

Class files:
* `Page.php`: Represents an individual page to expand citations on. Key methods are
  `get_text_from`, `expand_text`, and `write`.
* `Item.php`: Item is the parent class for Template and Comment.
  * `Template.php`: most of the actual expansion happens here.
    `Template::process()` handles most of template expansion and checking;
    `Template::add_if_new()` is generally (but not always) used to add
     parameters to the updated template; `Template::tidy()` cleans up the
     template, but may add parameters as well and have side effects.
  * `Comment.php`: Handles comments, such as those forbidding bot activity.
* `Parameter.php`: contains information about template parameter names, values,
   and metadata, and methods to parse template parameters.

## Style and structure notes

Constants and definitions should be provided in `constants.php`.
Classes should be in individual files. The code is generally written densely. 
Beware assignments in conditionals, one-line `if`/`foreach`/`else` statements, 
and action taking place through method calls that take place in assignments or equality checks. 
Also beware the difference between `else if` and `elseif`.
