var gulp = require('gulp');
var source = require('vinyl-source-stream');
var notify = require("gulp-notify");

var browserify = require('browserify');
var watchify = require('watchify');
var tsify = require('tsify');

var config = {
  publicPath : __dirname + '/javascript/dist',
  source: {
    path: __dirname + '/javascript/src',
    main: 'woocommerce-cc-gateway.ts',
    result: 'woocommerce-cc-gateway.js'
  }
};

gulp.task('compile-js', function (){
  var bundler = browserify(
    {
      basedir: config.source.path,
      cache: {},
      packageCache: {}
    })
    .add(config.source.path + '/' + config.source.main)
    .plugin(tsify);

  bundler = watchify(bundler);

  var bundle = function(bundler){
    bundler.bundle()
      .pipe(source(config.source.result))
      .pipe(gulp.dest(config.publicPath))
      .pipe(notify('Bundle re-bundled.'));
  };

  bundler.on('update', function(){
    bundle(bundler);
  });

  bundle(bundler);
});
