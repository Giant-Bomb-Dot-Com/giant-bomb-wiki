/* eslint-env node */
module.exports = function (grunt) {
  var conf = grunt.file.readJSON("extension.json");

  grunt.loadNpmTasks("grunt-banana-checker");
  grunt.loadNpmTasks("grunt-eslint");
  grunt.loadNpmTasks("grunt-stylelint");

  grunt.initConfig({
    eslint: {
      options: {
        extensions: [".js", ".json"],
        cache: true,
      },
      all: [
        "**/*.{js,json}",
        "!**/{vendor,node_modules}/**",
        "!**/wdio.conf*.js",
      ],
    },
    banana: conf.MessagesDirs,
    stylelint: {
      all: ["**/*.css", "!**/{vendor,node_modules}/**"],
    },
  });

  grunt.registerTask("test", ["eslint", "banana", "stylelint"]);
  grunt.registerTask("default", "test");
};
