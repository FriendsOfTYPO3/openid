This directory contains a modified version of the PHP OpenID library
(http://www.openidenabled.com/). We use only "Auth" directory from the library
and include also a copy of COPYING file to conform to the license requirements.

Current version of the library is 2.3.0
(git-checkout 2016-11-27; commit d8ef0dba1fa378fc22fe6d423f9423febb2d996d)
Source: https://github.com/openid/php-openid

The following modifications are made (search for <TYPO3-specific>):
- added cURL proxy settings from TYPO3 to the Auth/Yadis/ParanoidHTTPFetcher.php
- added phpdocs and some fixes to the library
