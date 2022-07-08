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
const gulp = require('gulp');
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
let uids4list = [];
let uids3list = [];

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

    var tasks = uids4list.map(function(folder){
      const folderName = folder.substring(folder.lastIndexOf('/') + 1)
      return gulp.src([
        `${folder}/*.scss`,
        `${folder}/*.js`,
        `${folder}/*.{jpg,png,svg}`,
        `${folder}/*.{woff,woff2}`,
      ]).pipe(gulp.dest(`${uids.dest}${folderName}`));
    });

    return merge(tasks);

    // let files = [];
    // uids4list.forEach(file => {
    //   files.push(`${file}/*.scss`);
    //   files.push(`${file}/*.js`);
    //   files.push(`${file}/*.{jpg,png,svg}`);
    //   files.push(`${file}/*.{woff,woff2}`);
    // })
    // console.log(files);
    // return src(files)
    //   .pipe(dest(`${uids.dest}`));

    // return src([
    //   `${uids.src}/**/*.scss`,
    //   `${uids.src}/**/*.js`,
    //   `${uids.src}/**/*.{jpg,png,svg}`,
    //   `${uids.src}/**/*.{woff,woff2}`,
    // ])
    //   .pipe(dest(`${uids.dest}`));
  });
}

function copyScss() {
  return Promise.all([addToList(`${uids4.src}/components`), addToList(`${uids.src}/components`, true)]);

  // loop through the node modules components directory for uids4.

  // for any folder name that matches a name in the readyList...
    // We want to add that directory to an array. We want a list of paths that we can do the src.pipe thing with
  // loop through the components directory for uids3
  // for any folder name that does not match a name in the readyList...
    // We want to add that directory to an array. We want a list of paths that we can do the src.pipe thing with
  // Loop through all the files in the temp directory

}

function addToList(filePath, invert = false) {
  return fs.promises.readdir(filePath)
    .then(files => {
      files.forEach(function (file, index) {
        if (
          (!invert && !uids4.readylist.includes(file))
          ||
          (invert && uids4.readylist.includes(file))
        ) {
          return;
        }
        const fullFilePath = path.join(filePath, file);

        if (fs.existsSync(fullFilePath) && fs.lstatSync(fullFilePath).isDirectory()) {
          // Make one pass and make the file complete
          uids4list.push(fullFilePath);
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
