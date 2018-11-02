[![Build Status](https://travis-ci.org/ms609/citation-bot.svg?branch=master)](https://travis-ci.org/ms609/citation-bot)
[![codecov](https://codecov.io/gh/ms609/citation-bot/branch/master/graph/badge.svg)](https://codecov.io/gh/ms609/citation-bot)
[![Project Status: Inactive - The project has reached a stable, usable state but is no longer being actively developed; support/maintenance will be provided as time allows.](https://www.repostatus.org/badges/latest/inactive.svg)](https://www.repostatus.org/#inactive)

# Citation bot

## GitHub repository details
There are two main branches of the bot: 
- The **master** code is implemented at https://tools.wmflabs.org/citations/, and is intended for public use.
- The **development** branch is intended for major restructuring and testing, and is implemented at https://tools.wmflabs.org/citations-dev/ .  

## Overview

This is some basic documentation about what this bot is and how some of the parts connect.

This is more properly a bot-gadget-tool combination. The parts are:

* DOIBot, found in index.html (web frontend) and process_page.php (information is
  POSTed to this and it does the citation expansion; backend). This automatically
  posts a new page revision with expanded citations and thus requires a bot account.
  All activity takes place on Tool Labs.
* Citation expander (:en:Mediawiki:Gadget-citations.js) + gadgetapi.php. This
  is comprises an Ajax front-end in the on-wiki gadget and a PHP backend API.
* [Generic template](https://github.com/ms609/citation-bot/blob/master/generate_template.php) creates the wiki reference given an identifier (for example given a doi: <https://tools.wmflabs.org/citations/generate_template.php?doi=10.1109/SCAM.2013.6648183>)

Bugs and requested changes are listed here: https://en.wikipedia.org/wiki/User_talk:Citation_bot .

## Structure

Basic structure of a Citation bot script:
* define configuration constants
* require `expandFns.php`, which will set up the rest of the needed functions
* use Page functions to fetch/expand/post the page's text


A quick tour of the main files:
* `constants.php`: constants defined
* `wikiFunctions.php`: functions related to Wikipedia ineractions, including some marked
   as "untested".
* `WikipediaBot.php`: functions to facilitate HTTP access to the Wikipedia API.
* `DOItools.php`: defines text/name functions
* `expandFns.php`: sets up needed functions, requires most of the other files listed here
* `apiFunctions.php`: sets up needed functions

Class files:
* `Page.php`: Represents an individual page to expand citations on. Key methods are
  `Page::get_text_from()`, `Page::expand_text()`, and `Page::write()`.
* `Template.php`: most of the actual expansion happens here.
  `Template::process()` handles some of template expansion and checking;
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

The bot requires php >= 5.6, whereas the WMFlabs servers by default (as of 2018) run 5.5.9.
To access php5.6, one must run the bot as a webservice:

    become citations[-dev]
    webservice stop
    webservice --backend=kubernetes php5.6 start

Or for testing in the shell:

    webservice --backend=kubernetes php5.6 shell

Before entering the k8s shell, it may be necessary to install phpunit 
(as wget is not available in the k8s shell):

    wget https://phar.phpunit.de/phpunit-5.phar
    webservice --backend=kubernetes php5.6 shell
    php phpunit-5.phar --bootstrap expandFns.php tests/phpunit/TemplateTest.php

