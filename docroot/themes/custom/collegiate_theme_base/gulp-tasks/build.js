/**
 * @file
 * Task: Build.
 */

 /* global module */

module.exports = function (gulp, plugins, options) {
  'use strict';
  plugins.runSequence.options.showErrorStackTrace = false;

  gulp.task('build', function(cb) {
    plugins.runSequence(
      ['clean:css'],
      ['compile:sass'],
      ['minify:css'],
      ['lint:js-gulp',
        'lint:js-with-fail',
        'lint:css-with-fail',
        'compile:js'],
      cb);
  });

  gulp.task('build:dev', function(cb) {
    plugins.runSequence(
      ['clean:css'],
      ['compile:sass'],
      ['minify:css'],
      ['lint:js-gulp',
        'lint:js',
        'lint:css',
        'compile:js'],
      cb);
  });
};
