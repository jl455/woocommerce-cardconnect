var gulp = require('gulp');
var notify = require('gulp-notify');

/*
 * Browserify/watchify bundler stuff
 */

var source = require('vinyl-source-stream');
var browserify = require('browserify');
var watchify = require('watchify');
var tsify = require('tsify');

var jsConfig = {
  publicPath : __dirname + '/javascript/dist',
  source: {
    path: __dirname + '/javascript/src',
    main: 'woocommerce-cc-gateway.js',
    result: 'woocommerce-cc-gateway.js'
  }
};

gulp.task('compile-js', function (){
  var bundler = browserify(
    {
      basedir: jsConfig.source.path,
      cache: {},
      packageCache: {}
    })
    .add(jsConfig.source.path + '/' + jsConfig.source.main)
    // .plugin(tsify);

  bundler = watchify(bundler);

  var bundle = function(bundler){
    bundler.bundle()
      .pipe(source(jsConfig.source.result))
      .pipe(gulp.dest(jsConfig.publicPath))
      .pipe(notify('Bundle re-bundled.'));
  };

  bundler.on('update', function(){
    bundle(bundler);
  });

  bundle(bundler);
});


/*
 * Build plugin
 */

var gulpIgnore = require('gulp-ignore');
var uglify = require('gulp-uglify');
var gulpif = require('gulp-if');

var excludeChecker = function(file){
  var excludeGlobs = [
    '.git',
    './dist',
    '.gitignore',
    './node_modules',
    './**/node_modules',
    '.DS_Store',
    'gulpfile.js',
    'package.json',
    './javascript/spec',
    './javascript/src',
    './javascript/.DS_Store',
    './javascript/tests.html',
    './javascript/tsconfig.json'
  ];
  console.log(file.name());
  return false;
  // if(excludeGlobs.indexOf(file) !== -1)
}

gulp.task('build', function() {
  gulp.src('./**/*')
    .pipe(gulpif(excludeChecker, uglify()))
    //.pipe(uglify({mangle: false}))
    .pipe(gulp.dest('./dist/'));
});
