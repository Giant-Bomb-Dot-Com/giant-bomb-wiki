// This configuration uses "selenium-standalone" plugin to automatically download and install
// both Selenium and necessary geckodriver/chromedriver,
// and then runs tests in headless Firefox and Chrome locally (but only if they are installed).

"use strict";

var merge = require("deepmerge"),
  conf = require("./wdio.conf").config;

// Overwrite default settings
conf = merge(conf, {
  maxInstances: 2,
  services: ["selenium-standalone"],
});

conf.capabilities = [
  {
    browserName: "firefox",
    "moz:firefoxOptions": {
      args: ["-headless"],
    },
  },
  {
    browserName: "chrome",
    "goog:chromeOptions": {
      args: ["--headless", "--disable-gpu", "--window-size=1280,800"],
    },
  },
];

exports.config = conf;
