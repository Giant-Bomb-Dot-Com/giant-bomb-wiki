GbSessionProvider
=================

The _GbSessionProvider_ is an identity extension for Mediawiki. It automatically creates a Mediawiki session based on prior authentication, using a JWT cookie session issued by a Giant Bomb Next system (GBN). If necessary, the extension creates a Mediawiki user on behalf of the verified GBN session cookie.

What this extension does relies on the understanding of how Mediawiki handles authentication and authorization, and how it is extensible. 
https://www.mediawiki.org/wiki/Manual:SessionManager_and_AuthManager#SessionProvider. 

Its basic flow follows a Mediawiki example to use a cookie already set by an external authentication system. https://www.mediawiki.org/wiki/Manual:SessionManager_and_AuthManager/SessionProvider_examples

In this case, authentication is already taken care of by the GBN system. The other pre-requisite

Users are automatically logged into Mediawiki upon successful verification. If the JWT include a `premium` claim, the new user is assigned to the `subscriber` group and will have the `gb-premium` user right.

## Pre-requisites

* Mediawiki 1.43.5+ (LTE)
* access to the `gb_wiki` cookie from Giant Bomb Next
* a way to verify the cookie
  * https://giantbomb.com/.well-known/jwks.json

## Development

The PHP code resides in this repo and gets copied to the container during the /installwiki.sh step. There's probably a better way to do this, but for now, the development loop is to:

1. Modify any extension php code in `extensions/GbSessionProvider` or in `config/LocalSettings.php`
2. `docker compose build`
3. `docker compose up -d`
4. `docker compose exec wiki /bin/bash /installwiki.sh`
5. Check in the browser
   - http://localhost:8080/index.php/Special:Version and if the debug UI is enabled, scroll to the bottom.
   - http://localhost:8080/wiki/Special:Preferences#mw-prefsection-personal to see the logged-in user preferences/groups.

### Testing JWT

In `config/LocalSettings.php` locate the following configuration settings for the extension GbSessionProvider.
* `$wgGbSessionProviderJWKSUri` is a string with URI for the JWKS
* `$wgGbSessionProviderTestModeEnabled` is a boolean to create first a `gb_wiki` cookie containing the content of the next setting.
* `$wgGbSessionProviderTestJWT` is a string that should contain a JWT to be verified using the JWKS
