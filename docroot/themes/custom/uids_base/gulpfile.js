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
const fs = require('fs');
const path = require('path');
const merge = require('merge-stream');

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

const uids4 = {
  src: '../../../../node_modules/@uiowa/uids4/src',
  readylist: [
    'button',
  ],
}

// Globals
let uidslist = [];

// Clean
function clean() {
  return del([
    `${paths.dest}/css/**`,
    `${uids.dest}/**/*`,
  ]);
}

function copyUids(done) {
  const createUidsList = new Promise(((resolve, reject) => {
    resolve(copyScss());
  }));

  createUidsList.then(value => {
    done();

    var tasks = uidslist.map(function(folder){
      const folderName = folder.substring(folder.lastIndexOf('/') + 1)
      return src([
        `${folder}/*.scss`,
        `${folder}/*.js`,
        `${folder}/*.{jpg,png,svg}`,
        `${folder}/*.{woff,woff2}`,
      ]).pipe(dest(`${uids.dest}${folderName}`));
    });

    return merge(tasks);
  });
}

function copyScss() {
  // Set both the uids4 and uids components directories to be checked against the ready list.
  // Then, add to the final file list if need be.
  return Promise.all([
    addToList(`${uids4.src}/components`),
    addToList(`${uids.src}/components`, true)
  ]);
}

function addToList(filePath, invert = false) {
  // Read the filepath.
  return fs.promises.readdir(filePath)
    // Then, for each file in that path...
    .then(files => {
      files.forEach(function (file, index) {

        // If we have set it to ignored, ignore it.
        if (
          (!invert && !uids4.readylist.includes(file))
          ||
          (invert && uids4.readylist.includes(file))
        ) {
          return;
        }

        // Otherwise, if it is a valid path, construct the full filepath and add it to the uidsList.
        const fullFilePath = path.join(filePath, file);

        if (fs.existsSync(fullFilePath) && fs.lstatSync(fullFilePath).isDirectory()) {
          uidslist.push(fullFilePath);
        }
      });
    })
    .catch(err => {
      console.log(err)
    });
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
  watch(paths.src, compile);
}

const copy = parallel(copyUids, fontCopy);
const compile = series(clean, copy, css);

exports.copy = copy;
exports.copyScss = copyScss;
exports.copyUids = copyUids;
exports.css = css;
exports.default = compile;
exports.watch = watchFiles;
