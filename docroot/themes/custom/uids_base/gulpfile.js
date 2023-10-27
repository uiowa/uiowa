/**
 * @file
 * Include gulp.
 */
const { src, dest, parallel, series, watch } = require('gulp');

// Include plugins.
const gulpSass = require('gulp-sass');
const nodeSass = require('node-sass');
const sass = gulpSass(nodeSass);
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
  dest: `${__dirname}/assets`,
  node: `../../../../node_modules/`,
};

const uids3 = {
  src: `${paths.node}@uiowa/uids/src`,
  dest: `${__dirname}/uids3/`,
}

const uids = {
  src: `${paths.node}@uiowa/uids4/src`,
  dest: `${__dirname}/uids/`,
  readylist: [
    'button',
  ],
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
function copyUids3() {
  return src([
    `${uids3.src}/**/*.scss`,
    `${uids3.src}/**/*.js`,
    `${uids3.src}/**/*.{jpg,png,svg}`,
    `${uids3.src}/**/*.{woff,woff2}`,
  ])
    .pipe(dest(`${uids3.dest}`));
}

function fontCopy() {
  return src([`${uids3.src}/assets/fonts/*.{woff,woff2}`])
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
        ]
      }).on('error', sass.logError))
    .pipe(postcss([ autoprefixer(), cssnano()]))
    .pipe((mode.development(sourcemaps.write('./'))))
    .pipe(dest(`${paths.dest}/css`));
}

// Watch files.
function watchFiles() {
  watch(paths.src, compile);
}

const copy = parallel(copyUids3, copyUids, fontCopy);
const compile = series(clean, copy, css);

exports.copy = copy;
exports.css = css;
exports.default = compile;
exports.watch = watchFiles;
