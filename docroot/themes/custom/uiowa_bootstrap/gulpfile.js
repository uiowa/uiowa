/**
 * @file
 * Include gulp.
 */

var gulp = require('gulp');
var config = require('./config.json');

// Include plugins.
var sass = require('gulp-sass');
var plumber = require('gulp-plumber');
var notify = require('gulp-notify');
var autoprefixer = require('gulp-autoprefixer');
var glob = require('gulp-sass-glob');
var sourcemaps = require('gulp-sourcemaps');

// CSS.
gulp.task('css', function () {
  return gulp.src(config.css.src)
    .pipe(glob())
    .pipe(plumber({
      errorHandler: function (error) {
        notify.onError({
          title:    "Gulp",
          subtitle: "Failure!",
          message:  "Error: <%= error.message %>",
          sound:    "Beep"
        })(error);
        process.exit(1);
      }}))
    .pipe(sourcemaps.init())
    .pipe(sass({
      outputStyle: 'compressed',
      errLogToConsole: true,
      includePaths: config.css.includePaths
    }))
    .pipe(autoprefixer(['last 2 versions', '> 1%', 'ie 9', 'ie 10']))
    .pipe(sourcemaps.write('./'))
    .pipe(gulp.dest(config.css.dest));
});

// JS.
gulp.task('js', function () {
  return gulp.src(config.js.src)
    .pipe(sourcemaps.init())
    .pipe(plumber({
      errorHandler: function (error) {
        notify.onError({
          title:    "JS",
          subtitle: "Failure!",
          message:  "Error: <%= error.message %>",
          sound:    "Beep"
        })(error);
        process.exit(1);
      }}))
    .pipe(gulp.dest(config.js.dest));
});

// Default Task.
gulp.task('default');
