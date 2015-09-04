This is some basic documentation about what this bot is and how some of the parts connect.

This is more properly a set of bots and tools. The parts are:

* DOIBot, found in doibot.html (tool's webpage) and doibot.php (information is POSTed to this and it does the citation expansion). This automatically posts a new page revision with expanded citations and thus requires a bot account. All activity takes place on Tool Labs.
* Citation expander (:en:Mediawiki:Gadget-citations.js) + text.php. This currently functions as a clickjacker (take a look at the JS) if you click the "Citations" button but apparently there's a second function that will automatically commit changes? "Link that can be added to the toolbox by widget, sends request in URL" <--also Citation bot 1?
* Bots that go through and create cite|doi (etc.) templates that are then transcluded into the page. This is no longer enwiki policy, so these parts of the bot should be retired. This includes `citewatch.php` and `arxivwatch.php`. This was done under the Citation bot 2 username.
* Bots that run and automatically expand citations on a page. This was done by `cron-doibot.php`. `category.php` would do the same for a user-supplied category. This used to (back in the Toolserver days) maintain a database of pages visited. This bot would be restarted on the hour in case it had gotten stuck on a page. `progress_doibot.php` was a way to look at the database and view the bot's progress. This ran under the Citation bot 1 username. 

Bugs and requested changes are listed here: https://en.wikipedia.org/wiki/User_talk:Citation_bot .

##Structure
Most of the action seems to be in `expandFns.php`. Most of the scripts listed above include/require it. It includes several files itself:

* `credentials/doibot.login`: on-wiki login credentials
* `Snoopy.class.php`: 2000s-era http client/scraper. The scraper functions are not really used here and it could probably be fairly easily replaced with an updated library or a dedicated MediaWiki API client libary. Note that it appears to use curl only for https, so the path to curl on Labs must be correct or the bot will fail to log in because the request can't reach the server.
* `DOITools.php`: defines `$bot` (the Snoopy instance), some regexes, capitalization
* `objects.php`: mix of variables, script, functions, and classes with their methods. Classes include `Page`, of which the key methods are `get_text_from`, `expand_text`, and `write`.
* `wikifunctions.php`: more constants and functions, and some functions marked as "untested".
* `credentials/crossref.login` appears to facilitate crossref and New York Times searches.

The following files appear to be broken or outdated and are commented out:
* `citewatchFns.php` appears to contain functions that check article/doi status in the previous Toolserver DB to facilitate creating Template:cite doi pages (now deprecated). Unclear whether this can be repurposed.
* `expand.php` contains one function (`old_expand_text`) which is used nowhere else in the bot.
* `credentials/mysql.login` appears to contain credentials for a nonexistent database--probably lost in the Toolserver-Tool Labs migration.

There is a heavy reliance on global variables and scripts are mixed in with functions. Convention here is to put the script portions at the top.
