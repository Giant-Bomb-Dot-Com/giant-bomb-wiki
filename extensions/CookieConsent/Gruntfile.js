// Copied with modification from CheckUser extension's Gruntfile.js
'use strict';
module.exports = function ( grunt ) {
	var conf = grunt.file.readJSON( 'extension.json' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );

	grunt.initConfig( {
		eslint: {
			options: {
				cache: true
			},
			all: '.'
		},
		banana: conf.MessagesDirs
	} );

	grunt.registerTask( 'test', [ 'eslint', 'banana' ] );
};
