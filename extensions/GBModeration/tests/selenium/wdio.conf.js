exports.config = {
  /*
   * NOTE: this MediaWiki user must be BOTH moderator AND automoderated.
   */
  username: process.env.MEDIAWIKI_USER || "User 1",
  password: process.env.MEDIAWIKI_PASSWORD || "123456",

  before: function () {
    /* Always open Special:BlankPage before tests */
    require("wdio-mediawiki/BlankPage").open();

    /* Install additional functions, e.g. browser.selectByLabel() */
    require("./util/miscFunctions").install(browser);
  },

  /* Common WebdriverIO options */
  specs: ["specs/*.js"],
  maxInstances: 1,
  capabilities: [
    {
      browserName: "firefox",
      "moz:firefoxOptions": {
        args: ["-headless"],
      },
    },
    /*
		{
			browserName: 'chrome',
			'goog:chromeOptions': {
				args: ['--headless', '--disable-gpu', '--window-size=1280,800']
			}
		}
		*/
  ],
  sync: true,
  logLevel: "silent",
  coloredLogs: true,
  bail: 0,
  screenshotPath: "/tmp/",
  baseUrl: "http://127.0.0.1",
  waitforTimeout: 10000,
  connectionRetryTimeout: 90000,
  connectionRetryCount: 3,
  framework: "mocha",
  reporters: ["spec"],
  mochaOpts: {
    ui: "bdd",
    timeout: 50000,
  },
};
