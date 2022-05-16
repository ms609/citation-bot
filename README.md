[![Build Status](https://github.com/ms609/citation-bot/workflows/Bot%20Full%20Test%20Suite/badge.svg)](https://github.com/ms609/citation-bot/actions?query=workflow%3A%22Bot+Full+Test+Suite%22)
[![Build Status](https://github.com/ms609/citation-bot/workflows/CodeQL/badge.svg)](https://github.com/ms609/citation-bot/actions?query=workflow%3A%22CodeQL%22)
[![Build Status](https://github.com/ms609/citation-bot/workflows/PHP%20Static%20Tests/badge.svg)](https://github.com/ms609/citation-bot/actions?query=workflow%3A%22PHP+Static+Tests%22)
[![codecov](https://codecov.io/gh/ms609/citation-bot/branch/master/graph/badge.svg)](https://codecov.io/gh/ms609/citation-bot)
[![Project Status: Inactive - The project has reached a stable, usable state but is no longer being actively developed; support/maintenance will be provided as time allows.](https://www.repostatus.org/badges/latest/inactive.svg)](https://www.repostatus.org/#inactive)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP ](https://img.shields.io/badge/PHP-7.3-blue.svg)](https://www.php.net)
[![GitHub issues](https://img.shields.io/github/issues/ms609/citation-bot.png)](https://github.com/ms609/citation-bot/issues)


# Citation bot

## GitHub repository details
There are one to two main branches of the bot: 
- The **master** code is implemented at https://citations.toolforge.org/, and is intended for public use.
- When needed, the **development** branch is intended for major restructuring and testing, and is implemented at https://citations-dev.toolforge.org/ .  

## Overview

This is some basic documentation about what this bot is and how some of the parts connect.

This is more properly a bot-gadget-tool combination. The parts are:

* DOIBot, found in `index.html` (web frontend) and `process_page.php` (information is
  POSTed to this and it does the citation expansion; backend). This automatically
  posts a new page revision with expanded citations and thus requires a bot account.
  All activity takes place on Tool Labs.
* Citation expander (https://en.wikipedia.org/wiki/MediaWiki:Gadget-citations.js) + `gadgetapi.php`. This
  is comprises an Ajax front-end in the on-wiki gadget and a PHP backend API.
* `generate_template.php` creates the wiki reference given an identifier (for example: https://citations.toolforge.org/generate_template.php?doi=10.1109/SCAM.2013.6648183)

Bugs and requested changes are listed here: https://en.wikipedia.org/wiki/User_talk:Citation_bot.

## Structure

Basic structure of a Citation bot script:
* define configuration constants
* require `setup.php`, which will set up the rest of the needed functions
* use Page functions to fetch/expand/post the page's text


A quick tour of the main files:
* `constants.php`: constants defined
* `WikipediaBot.php`: functions to facilitate HTTP access to the Wikipedia API.
* `NameTools.php`: defines name functions
* `setup.php`: sets up needed functions, requires most of the other files listed here
* `expandFns.php`: a variety of functions
* `apiFunctions.php`: sets up needed functions for expanding pmid/doi/etc
* `Zotero.php`: URL expansion related functions organized in a static class 

Class files:
* `Page.php`: Represents an individual page to expand citations on. Key methods are
  `Page::get_text_from()`, `Page::expand_text()`, and `Page::write()`.
* `Template.php`: most of the actual expansion happens here.
  `Template::add_if_new()` is generally (but not always) used to add
   parameters to the updated template; `Template::tidy()` cleans up the
   template, but may add parameters as well and have side effects.
* `Comment.php`: Handles comments, nokwiki, etc. tags
* `Parameter.php`: contains information about template parameter names, values,
   and metadata, and methods to parse template parameters.

## Style and structure notes

Constants and definitions should be provided in `constants.php`.
Classes should be in individual files. The code is generally written densely. 
Beware assignments in conditionals, one-line `if`/`foreach`/`else` statements, 
and action taking place through method calls that take place in assignments or equality checks. 
Also beware the difference between `else if` and `elseif`.

## Deployment

The bot requires PHP >= 7.3.

To run the bot from a new environment, you will need to create an `env.php` file (if one doesn't already exist) that sets the needed authentication tokens as environment variables. To do this, you can rename `env.php.example` to `env.php`, set the variables in the file, and then make sure the file is not world readable or writable:

    chmod o-rwx env.php

 To run the bot as a webservice from WM Toolforge:

    become citations[-dev]
    webservice stop
    webservice --backend=kubernetes start

Or for testing in the shell:

    webservice --backend=kubernetes shell

Before entering the k8s shell, it may be necessary to install phpunit (as wget is not available in the k8s shell).

## Dependency services

The bot accesses an instance of the Zotero translation server.  The source code for this server is https://github.com/ms609/translation-server

This can be updated by maintainers logging on to Toolforge, then entering the commands 

    become translation-server
    npm update
    webservice restart
    
In order to reduce complexity, the code currently uses a Wikipedia hosted and managed server instead at https://en.wikipedia.org/api/rest_v1/#/Citation/getCitation.

## Running on the command line
In order to run on the command line one needs OAuth tokens as documented in `env.php.example` (there are additional API keys that are needed to run some functions).  Change BOT_USER_AGENT in `setup.php` to something else. Use composer to `composer require mediawiki/oauthclient:1.2.0`.  Then the bot can be run such as:

    /usr/bin/php ./process_page.php "Covid Watch|Water|COVID-19_apps" --slow
    
The command line tool will first look for a file with the page in the local direcory and read that for the | deliminated page names.

