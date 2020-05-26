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
    '../../../../node_modules/@uiowa/uids/src/**/*.scss',
    '../../../../node_modules/@uiowa/uids/src/**/*.js',
    '../../../../node_modules/@uiowa/uids/src/**/*.{jpg,png,svg}',
    '../../../../node_modules/@uiowa/uids/src/**/*.{woff,woff2}',
  ])
    .pipe(dest('./uids/'));
}

// function fontCopy() {
//   return src([
//   ])
//     .pipe(dest('./assets/'));
// }

// SCSS bundled into CSS task.
function css() {
  return src(config.css.src)
    .pipe((mode.development(sourcemaps.init())))
    .pipe(glob())
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

exports.copy = copy;
exports.css = css;
exports.default = series(copy, css);
exports.watch = watchFiles;
