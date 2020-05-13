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
const mode = require('gulp-mode')();

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
    .pipe((mode.development(sourcemaps.init())))
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
    .pipe((mode.development(sourcemaps.write('./'))))
    .pipe(dest(config.css.dest));
}

// Watch files.
function watchFiles() {
  // Watch SCSS changes.
  watch(paths.scss + '**/*.scss', { usePolling: true }, parallel(css, copy));
}

exports.copy = parallel(copy, fontCopy);
exports.css = css;
exports.default = parallel(fontCopy, series(copy, css));
exports.watch = watchFiles;
