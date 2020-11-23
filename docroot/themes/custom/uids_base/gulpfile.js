/**
 * @file
 * Include gulp.
 */

const { src, dest, parallel, series, watch } = require('gulp');

// Include plugins.
const sass = require('gulp-sass');
const del = require ('del');
const postcss = require('gulp-postcss');
const autoprefixer = require('autoprefixer');
const cssnano = require('cssnano')
const glob = require('gulp-sass-glob');
const sourcemaps = require('gulp-sourcemaps');
const mode = require('gulp-mode')();

/*
 * Directories here
 */
const paths = {
  src: `${__dirname}/scss/**/*.scss`,
  dest: `${__dirname}/assets`
};

const uids = {
  src: '../../../../node_modules/@uiowa/uids/src',
  dest: `${__dirname}/uids/`,
}

// Clean
function clean() {
  return del([
    `${paths.dest}/css/**`,
    `${uids.dest}/**/*`,
  ]);
}

function copyUids() {
  return src([
    `${uids.src}/**/*.scss`,
    `${uids.src}/**/*.js`,
    `${uids.src}/**/*.{jpg,png,svg}`,
    `${uids.src}/**/*.{woff,woff2}`,
  ])
    .pipe(dest(`${uids.dest}`));
}

function fontCopy() {
  return src([`${uids.src}/assets/fonts/*.{woff,woff2}`])
    .pipe(dest('./assets/fonts'));
}

// SCSS bundled into CSS task.
function css() {
  return src(`${paths.src}`)
    .pipe((mode.development(sourcemaps.init())))
    .pipe(glob())
    .pipe(sass({
        includePaths: [
          "./node_modules",
          "./uids/",
        ]
      }).on('error', sass.logError))
    .pipe(postcss([ autoprefixer(), cssnano()]))
    .pipe((mode.development(sourcemaps.write('./'))))
    .pipe(dest(`${paths.dest}/css`));
}

// Watch files.
function watchFiles() {
  watch(paths.src, { usePolling: true }, compile);
  // @todo Watch other changes?
}

const copy = parallel(copyUids, fontCopy);
const compile = series(clean, copy, css);

exports.copy = copy;
exports.css = css;
exports.default = compile;
exports.watch = watchFiles;
