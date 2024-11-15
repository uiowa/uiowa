/**
 * @file
 * Include gulp.
 */
const { src, dest, parallel, series, watch } = require('gulp');

// Include plugins.
const gulpSass = require('gulp-sass')(require('sass'));
const del = require('del');
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
  ], { encoding: false })
    .pipe(dest(`${uids.dest}`));
}

// SCSS bundled into CSS task.
function css() {
  return src(`${paths.src}`)
    .pipe((mode.development(sourcemaps.init())))
    .pipe(glob())
    .pipe(gulpSass({
      includePaths: [
        "./node_modules",
      ],
      silenceDeprecations: ['import', 'legacy-js-api']
    }).on('error', gulpSass.logError))
    .pipe(postcss([ autoprefixer(), cssnano()]))
    .pipe((mode.development(sourcemaps.write('./'))))
    .pipe(dest(`${paths.dest}/css`));
}

// Watch files.
function watchFiles() {
  watch(paths.src, compile);
}

const copy = parallel(copyUids);
const compile = series(clean, copy, css);

exports.copy = copy;
exports.css = css;
exports.default = compile;
exports.watch = watchFiles;
