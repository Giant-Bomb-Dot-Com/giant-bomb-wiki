/**
	@file
	@brief Misc. utility functions used in our testsuite.
*/

"use strict";

var nodeUrl = require("url"),
  request = require("request"),
  MWBot = require("mwbot"),
  Page = require("wdio-mediawiki/Page"),
  fs = require("fs-ext"), // for fs.flock()
  // eslint-disable-next-line no-redeclare
  Promise = require("bluebird"),
  Api = require("wdio-mediawiki/Api");

/**
	@brief Runs from before() section of wdio.conf.js.
*/
module.exports.install = function (browser) {
  // HACK: Compatibility with "wdio-mediawiki" package, which incorrectly looks for browser.options.password
  // (which was correct for WDIO 4, but should be "browser.config.password" in WDIO 5)
  browser.options.username = browser.config.username;
  browser.options.password = browser.config.password;

  /**
		@brief Make browser.url() ignore "Are you sure you want to leave this page?" alerts.
	*/
  var oldUrlFunc = browser.url.bind(browser);

  var newUrlFunc = function (url) {
    /* Try to suppress beforeunload events.
			This doesn't work reliably in IE11, so there is a fallback acceptAlert() below.
			We can't remove this browser.execute(), because Safari doesn't support acceptAlert().
		*/
    browser.execute(function () {
      window.onbeforeunload = null;
      if (window.$) {
        $(window).off(
          "beforeunload pageshow",
        ); /* See [mediawiki.confirmCloseWindow.js] in MediaWiki core */
      }
    });

    var ret = oldUrlFunc(url);

    try {
      /* Fallback for IE11.
				Not supported by SafariDriver, see browser.execute() above. */
      browser.acceptAlert();
    } catch (e) {}

    return ret;
  };

  Object.defineProperty(browser, "url", {
    writable: true,
    value: newUrlFunc,
  });

  /**
		@brief Returns random string which is unlikely to be the same in two different tests.
	*/
  browser.getTestString = function () {
    return Date.now() + " " + Math.random();
  };

  /**
		@brief Precreates a test page (using moderator's account).
		@return Promise which is resolved with the Title of newly created page.
	*/
  browser.precreatePageAsync = function () {
    var PageName = "ExistingPage " + browser.getTestString(),
      Content = "Initial content " + browser.getTestString();

    return Api.edit(PageName, Content).then(() => {
      return PageName;
    });
  };

  /**
		@brief Creates new account and logins into it via API.
		@return MWBot
	*/
  browser.loginIntoNewAccount = function () {
    var username = "Test User " + Date.now() + " " + Math.random(),
      password = "123456";

    /* Because API is executed directly by the test (not in the browser
			controlled by Selenium), we need to obtain the login cookies
			from API and feed them to the browser.
		*/
    var cookieJar = request.jar();
    var bot = new MWBot(
      {
        apiUrl: `${browser.options.baseUrl}/api.php`,
        verbose: true,
      },
      { jar: cookieJar },
    );

    var lockfile = fs.openSync(__filename, "r");

    browser.call(() =>
      bot
        .getCreateaccountToken()
        .then(() => {
          // Workaround for T199393
          // (MediaWiki deadlocks on simultaneous CreateAccount attempts)
          fs.flockSync(lockfile, "ex");
        })
        .then(() =>
          bot
            .request({
              // Create the new account. Note: this alone does NOT login.
              action: "createaccount",
              createreturnurl: browser.options.baseUrl,
              createtoken: bot.createaccountToken,
              username: username,
              password: password,
              retype: password,
            })
            .finally(() => {
              fs.flockSync(lockfile, "un"); // Unlock
            })
            .then((apiResult) => {
              if (apiResult.createaccount.status !== "PASS") {
                return Promise.reject(
                  new Error(
                    "loginIntoNewAccount(): failed to create account: " +
                      apiResult.createaccount.message,
                  ),
                );
              }
            })
            .then(() =>
              bot
                .request({
                  action: "query",
                  meta: "tokens",
                  type: "login",
                })
                .then((ret) =>
                  bot
                    .request({
                      action: "clientlogin",
                      username: username,
                      password: password,
                      loginreturnurl: browser.options.baseUrl,
                      logintoken: ret.query.tokens.logintoken,
                    })
                    .then((apiResult) => {
                      if (apiResult.clientlogin.status !== "PASS") {
                        return Promise.reject(
                          new Error(
                            "loginIntoNewAccount(): failed to login: " +
                              apiResult.clientlogin.message,
                          ),
                        );
                      }
                    }),
                ),
            ),
        ),
    );

    // eslint-disable-next-line no-underscore-dangle
    for (var cookie of cookieJar._jar.toJSON().cookies) {
      // Feed these login cookies to Selenium-controlled browser
      browser.setCookies({
        name: cookie.key,
        value: cookie.value,
      });
    }

    return bot;
  };

  /** @brief Logout from the currently used MediaWiki user account. */
  browser.logout = function () {
    if (browser.desiredCapabilities.browserName === "safari") {
      /* With SafariDriver, HttpOnly cookies can't be deleted by deleteCookie() */
      new Page().openTitle("Special:UserLogout");
    } else {
      /* Quick logout: forget the session cookie */
      browser.deleteCookie();
    }
  };

  /** @brief Select $link by selector. Adds $link.query field to the returned $link */
  browser.getLink = function (selector) {
    var $link = $(selector);

    Object.defineProperty($link, "query", {
      get: function () {
        var url = nodeUrl.parse($link.getAttribute("href"), true, true),
          query = url.query;

        if (!query.title) {
          /* URL like "/wiki/Cat?action=edit" */
          var title = url.pathname.split("/").pop();
          if (title !== "index.php") {
            query.title = title;
          }
        }

        return query;
      },
    });

    return $link;
  };

  /**
		@brief Enable mobile skin (from Extension:MobileFrontend) for further requests.
		@note This preference is saved as a cookie. If the cookies are deleted, skin will revert to desktop.
	*/
  browser.switchToMobileSkin = function () {
    browser.setCookies({ name: "mf_useformat", value: "true" });
  };
};
