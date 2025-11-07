module.exports = {
  testEnvironment: "jsdom",
  moduleFileExtensions: ["js", "json", "vue"],
  transform: {
    "^.+\\.vue$": "@vue/vue3-jest",
    "^.+\\.js$": "babel-jest",
  },
  testEnvironmentOptions: {
    customExportConditions: ["node", "node-addons"],
  },
  setupFilesAfterEnv: ["<rootDir>/jest.setup.js"],
  testMatch: ["**/skins/**/__tests__/**/*.spec.js"],
  collectCoverageFrom: [
    "skins/**/components/**/*.vue",
    "!skins/**/components/VueSingleFileComponentExample.vue",
    "!skins/**/components/AnotherVueComponent.vue",
  ],
  testPathIgnorePatterns: ["/node_modules/"],
  globals: {
    "vue-jest": {
      babelConfig: false,
      templateCompiler: {
        compilerOptions: {
          whitespace: "condense",
        },
      },
    },
  },
};
