'use strict';

module.exports = function(grunt) {

    var resFiles = {
        less: ['res/src/css/**/*.less'],
        css: ['res/src/css/**/*.css'],
        js: ['res/src/js/**/*.js'],
        img: ['res/src/img/**/*.{png,jpg,gif}'],

        lessNg: ['res/src/ng/**/*.less'],
        cssNg: ['res/src/ng/**/*.css'],
        jsNg: ['res/src/ng/**/*.js'],
        htmlNg: ['res/src/ng/**/*.html']
    };

    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),

        // styles:
        less: {
            less:{
                flatten: true,
                expand: true,
                src: resFiles.less,
                dest: 'res/src/css/',
                ext: '.css'
            },
            ng:{
                flatten: true,
                expand: true,
                src: resFiles.lessNg,
                dest: 'res/src/ng/',
                ext: '.css'
            }
        },
        csslint: {
            options: {
                csslintrc: '.csslintrc'
            },
            development: {
                src: resFiles.css.concat(resFiles.cssNg)
            }
        },
        autoprefixer: {
            options: {
                browsers: ['last 2 versions']
            },
            css: {
                flatten: true,
                expand: true,
                src: resFiles.css,
                dest: 'res/dist/css/'
            },
            ng: {
                flatten: true,
                expand: true,
                src: resFiles.cssNg,
                dest: 'res/dist/ng/'
            }
        },
        cssmin: {
            css: {
                flatten: true,
                expand: true,
                src: 'res/dist/css/**/*.css',
                dest: 'res/dist/css/'
            },
            ng: {
                flatten: true,
                expand: true,
                src: 'res/dist/ng/**/*.css',
                dest: 'res/dist/ng/'
            }
        },
        //concat: {
        //    options: {
        //        // define a string to put between each file in the concatenated output
        //        //separator: ';\n'
        //    },
        //    theme: {
        //        // the files to concatenate
        //        files:{
        //            'style.css':[
        //                'res/src/theme-header.css'
        //            ]
        //        }
        //    }
        //},

        //  scripts:
        jshint: {
            options: {
                jshintrc: true
            },
            all: {
                src: resFiles.js.concat(resFiles.jsNg).concat('Gruntfile.js')
            }
        },
        uglify: {
            js: {
                flatten: true,
                expand: true,
                src: 'res/src/js/**/*.js',
                dest: 'res/dist/js/'
            },
            ng: {
                flatten: true,
                expand: true,
                src: 'res/src/ng/**/*.js',
                dest: 'res/dist/ng/'
            }
        },

        //  html templates
        htmlmin:{
            options: {
                removeComments: true,
                collapseWhitespace: true
            },
            ng: {
                flatten: true,
                expand: true,
                src: 'res/src/ng/**/*.html',
                dest: 'res/dist/ng/'
            }
        },

        //  images:
        imagemin: {
            dynamic: {
                files: [{
                    expand: true,
                    cwd: 'res/src/img/',
                    src: ['**/*.{png,jpg,gif}'],
                    dest: 'res/dist/img/'
                }]
            }
        },

        //  common:
        clean: {
            css: ['res/tmp/css'],
            js: ['res/tmp/js'],
            img: ['res/tmp/img'],
            all: ['res/tmp'],
            dist: ['res/dist']
        },
        watch: {
            less: {
                files:  resFiles.less.concat(resFiles.lessNg),
                tasks: ['less']
            },
            css: {
                files:  resFiles.css.concat(resFiles.cssNg),
                tasks: ['css']
            },
            js: {
                files: resFiles.js.concat(resFiles.jsNg),
                tasks: ['js']
            },
            html: {
                files:  resFiles.htmlNg,
                tasks: ['htmlmin']
            },
            img: {
                files:  resFiles.img,
                tasks: ['img']
            }
        }
    });

    // Load NPM tasks
    require('load-grunt-tasks')(grunt);

    // Making grunt default to force in order not to break the project.
    grunt.option('force', true);

    grunt.registerTask('css', ['csslint', 'autoprefixer', 'cssmin']);

    grunt.registerTask('js', ['jshint', 'uglify']);

    grunt.registerTask('img', ['imagemin']);

    grunt.registerTask('default', ['clean:dist', 'less', 'css', 'js', 'img', 'htmlmin', 'watch']);

};