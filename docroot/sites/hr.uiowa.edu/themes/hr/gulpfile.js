// Include gulp.
var gulp = require('gulp');
var config = require('./config.json');
// Include plugins.
var sass = require('gulp-sass');
var plumber = require('gulp-plumber');
var autoprefixer = require('gulp-autoprefixer');
var glob = require('gulp-sass-glob');
// CSS.
gulp.task('css', function() {
    return gulp.src(config.css.src)
        .pipe(glob())
        .pipe(sass({
            style: 'compressed',
            errLogToConsole: true,
            includePaths: config.css.includePaths
        }))
        .pipe(autoprefixer(['last 2 versions', '> 1%', 'ie 9', 'ie 10']))
        .pipe(gulp.dest(config.css.dest));
});
// Static Server + Watch
gulp.task('serve', ['css'], function() {
    gulp.watch(config.css.src, ['css']);
});
// Default Task
gulp.task('default', ['serve']);
