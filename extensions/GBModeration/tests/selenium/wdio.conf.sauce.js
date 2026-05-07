// This configuration uses SauceLabs (third-party service) to run remote Selenium for us.

"use strict";

var merge = require("deepmerge"),
  conf = require("./wdio.conf").config;

// Overwrite default settings
conf = merge(conf, {
  maxInstances: 5,

  services: ["sauce"],
  user: process.env.SAUCE_USERNAME || "",
  key: process.env.SAUCE_ACCESS_KEY || "",
  region: "us",
  sauceConnect: true,

  waitforTimeout: 40000,
  mochaOpts: {
    timeout: 180000,
  },
});

conf.capabilities = [
  {
    platform: "Windows 10",
    browserName: "MicrosoftEdge",
    version: "14.14393",
  },
  {
    platform: "Windows 8.1",
    browserName: "internet explorer",
    version: "11.0",
  },
  {
    platform: "macOS 10.13",
    browserName: "safari",
    version: "11.1",
    exclude: [
      // SafariDriver doesn't support sendKeys() to contenteditable,
      // so we can't test VisualEditor in it
      "specs/visualeditor.js",
    ],
  },
  { browserName: "chrome", version: "latest" },
  { browserName: "firefox", version: "latest" },
].map(function (capability) {
  // Group all tests in SauceLabs Dashboard by the build ID
  capability.build =
    process.env.TRAVIS_JOB_NUMBER +
    " [" +
    process.env.branch +
    "] - Testsuite of Extension:Moderation";

  return capability;
});

exports.config = conf;
