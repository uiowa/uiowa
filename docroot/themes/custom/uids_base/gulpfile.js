/**
 * @file
 * Include gulp.
 */

const { src, dest, parallel, series, watch } = require('gulp');
const config = require('./config.json');

// Include plugins.
const sass = require('gulp-sass');
const plumber = require('gulp-plumber');
const prefix = require('gulp-autoprefixer');
const glob = require('gulp-sass-glob');
const sourcemaps = require('gulp-sourcemaps');
const browsersync = require('browser-sync');

/*
 * Directories here
 */
var paths = {
  build: './assets/',
  scss: './scss/'
};

function copy() {
  return src([
    '../../../../node_modules/@uiowa/uids/**/*.scss',
    '../../../../node_modules/@uiowa/uids/**/*.js',
    '../../../../node_modules/@uiowa/uids/**/*.twig',
    '../../../../node_modules/@uiowa/uids/**/images/*'
  ])
    .pipe(dest('./uids/'));
}

function fontCopy() {
  return src([
    '../../../../node_modules/@uiowa/uids/assets/**/*.woff',
    '../../../../node_modules/@uiowa/uids/assets/**/*.woff2'
  ])
    .pipe(dest('./assets/'));
}

// SCSS bundled into CSS task.
function css() {
  return src(config.css.src)
    .pipe(sourcemaps.init())
    .pipe(glob())
    // Stay live and reload on error.
    .pipe(plumber({
      handleError: function (err) {
        console.log(err);
        process.exit(1);
      }
    }))
    .pipe(sass({
        outputStyle: 'compressed',
        includePaths: config.css.includePaths
      }).on('error', function (err) {
        console.log(err.message);
        process.exit(1);
      }))
    .pipe(prefix(['last 2 versions', '> 1%', 'ie 9', 'ie 10'], {
      cascade: true
    }))
    // .pipe(minifyCSS())
    .pipe(sourcemaps.write('./'))
    .pipe(dest(config.css.dest));
}

// BrowserSync.
function browserSync() {
  browsersync({
    // server: {
    // baseDir: paths.build
    // },.
    notify: false,
    browser: "google chrome",
    proxy: "https://clas.local.drupal.uiowa.edu/"
  });
}

// BrowserSync reload.
function browserReload() {
  return browsersync.reload;
}

// Watch files.
function watchFiles() {
  // Watch SCSS changes.
  watch(paths.scss + '**/*.scss', parallel(css, copy))
    .on('change', browserReload());
}

const watching = parallel(watchFiles, browserSync);

// exports.js = js;.
exports.copy = parallel(copy, fontCopy);
exports.css = css;
exports.default = parallel(fontCopy, series(copy, css));
exports.watch = watching;
