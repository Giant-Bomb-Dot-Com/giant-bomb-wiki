# Changelog

## 0.2 (2026-02-02)

### Features

* added some integration tests to verify:
  * a valid gb_wiki cookie auto logs-in the user in the cookie
  * a missing gb_wiki cookie treats Mediawiki as unaunthenticated
  * an invalid gb_wiki cookie treats Mediawiki as unauthenticated
* added handling for returning Mediawiki users
* test the 'subscriber' user group and the 'gb-premium' user right

## 0.1 (2025-11-25)

### Features

* read and verify a Giant Bomb Next JWT cookie
* auto-login to Mediawiki
* assign logged-in user to premium user group if required
