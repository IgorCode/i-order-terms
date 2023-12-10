/* global module */
"use strict";

module.exports = function(grunt) {

	// Timing
	require('time-grunt')(grunt);

	// Project configuration
	grunt.initConfig({
		// Metadata
		pkg: grunt.file.readJSON('package.json'),

		dirs: {
			plugin : 'src',
			release: {
				plugin: 'release/trunk'
			}
		},

		// Task configurations
		phplint: {
			plugin: [
				'<%= dirs.plugin %>/**/*.php'
			]
		},
		phpdocumentor: {
			options: {
				command: 'run'
			},
			all: {
				options: {
					directory: '<%= dirs.plugin %>',
					target: 'docs'
				}
			}
		},
		jshint: {
			options: {
				node: false
			},
			gruntfile: {
				options: {
					node: true
				},
				src: 'Gruntfile.js'
			},
			plugin: {
				expand: true,
				cwd: '<%= dirs.plugin %>/js/',
				src: [
					'*.js',
					'!*.min.js'
				]
			}
		},
		csslint: {
			options: {
				'adjoining-classes': false,
				'box-model': false,
				'ids': false,
				'import': 2
			},
			plugin: {
				expand: true,
				cwd: '<%= dirs.plugin %>/css/',
				src: [
					'*.css',
					'!*.min.css'
				]
			}
		},
		checktextdomain: {
			options: {
				create_report_file: false,
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_html_x:1,2c,3d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'_ex:1,2c,3d',
					'_n:1,2,4d',
					'_nx:1,2,4c,5d',
					'_n_noop:1,2,3d',
					'_nx_noop:1,2,3c,4d'
				]
			},
			plugin: {
				options: {
					text_domain: 'i-order-terms'
				},
				files: [{
					expand: true,
					src: [
						'<%= dirs.plugin %>/**/*.php'
					]
				}]
			}
		},


		clean: {
			publish: [
				'<%= dirs.release.plugin %>/**/*'
			]
		},
		copy: {
			publish: {
				expand: true,
				cwd: '<%= dirs.plugin %>/',
				src: '**/*',
				dest: '<%= dirs.release.plugin %>/'
			}
		},

		uglify: {
			publish: {
				expand: true,
				cwd: '<%= dirs.plugin %>/js/',
				src: [
					'*.js',
					'!*.min.js'
				],
				dest: '<%= dirs.release.plugin %>/js/',
				ext: '.min.js'
			}
		},
		cssmin: {
			publish: {
				expand: true,
				cwd: '<%= dirs.plugin %>/css/',
				src: [
					'*.css',
					'!*.min.css'
				],
				dest: '<%= dirs.release.plugin %>/css/',
				ext: '.min.css'
			}
		},

		makepot: {
			options: {
				domainPath: 'languages/',
				potFilename: 'i-order-terms.pot',
				exclude: [],
				include: [],

				potHeaders: {
					poedit: true,
					language: 'en_US',
					'Report-Msgid-Bugs-To': 'https://wordpress.org/support/plugin/i-order-terms',
					'po-revision-date': (function() {
						return grunt.template.today('UTC:yyyy-mm-dd H:MM+0000');
					}()),
					'last-translator': 'Igor Jerosimic',
					'language-team': 'Igor Jerosimic',
					'x-poedit-keywordslist': true
				},
				potComments: '',
				updateTimestamp: true,
				updatePoFiles: false,
				processPot: function( pot ) {
					var translation,
						excludedId = [],
						excludedMeta = [
							'Theme Name of the plugin/theme',
							'Author URI of the plugin/theme',
							'Author of the plugin/theme'
						];

					for (translation in pot.translations['']) {
						if (typeof pot.translations[''][translation].msgid !== 'undefined') {
							if (excludedId.indexOf(pot.translations[''][translation].msgid) >= 0) {
								// console.log('Excluded meta: ' + pot.translations[''][translation].msgid);
								delete pot.translations[''][translation];

								continue;
							}
						}

						if (typeof pot.translations[''][translation].comments.extracted !== 'undefined') {
							if (excludedMeta.indexOf(pot.translations[''][translation].comments.extracted) >= 0) {
								// console.log('Excluded meta: ' + pot.translations[''][ translation ].comments.extracted);
								delete pot.translations[''][translation];
							}
						}
					}

					return pot;
				}
			},
			publish: {
				options: {
					type: 'wp-plugin',
					mainFile: 'i-order-terms.php',

					cwd: '<%= dirs.release.plugin %>'
				}
			}
		},


		watch: {
			gruntfile: {
				files: '<%= jshint.gruntfile.src %>',
				tasks: ['jshint:gruntfile']
			}
		}
	});


	// These plugins provide necessary tasks
	grunt.loadNpmTasks('grunt-checktextdomain');
	grunt.loadNpmTasks('grunt-contrib-clean');
	// grunt.loadNpmTasks('grunt-contrib-compress');
	grunt.loadNpmTasks('grunt-contrib-copy');
	grunt.loadNpmTasks('grunt-contrib-csslint');
	grunt.loadNpmTasks('grunt-contrib-cssmin');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-phpdocumentor');
	grunt.loadNpmTasks('grunt-phplint');
	grunt.loadNpmTasks('grunt-wp-i18n');


	// == Tasks ==
	grunt.registerTask('ctd', ['checktextdomain']);
	grunt.registerTask('phpdoc', ['phpdocumentor']);

	grunt.registerTask('default', ['jshint', 'phplint:plugin', 'csslint:plugin', 'ctd:plugin']);

	grunt.registerTask('build:release', [
		'jshint',
		'phplint:plugin',
		'csslint:plugin',
		'ctd:plugin',

		'clean:publish',
		'copy:publish',
		'uglify:publish',
		'cssmin:publish',
		'makepot:publish'
	]);
};
