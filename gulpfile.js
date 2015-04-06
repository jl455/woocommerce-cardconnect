var gulp = require('gulp');
var browserify = require('browserify');
var source = require('vinyl-source-stream');
var watchify = require('watchify');

gulp.task('browserify', function(){
  browserifyShare();
});

gulp.task('browserifyTests', browserifyTests);

function browserifyShare(){
  // you need to pass these three config option to browserify
  var b = browserify({
    cache: {},
    packageCache: {},
    fullPaths: true
  });
  b = watchify(b);
  b.on('update', function(){
    bundleShare(b);
  });

  b.add('./javascript/src/woocommerce-cc-gateway.js');
  bundleShare(b);
}

function bundleShare(b){
  b.bundle()
    .pipe(source('woocommerce-cc-gateway.js'))
    .pipe(gulp.dest('./javascript/dist'));
}

function browserifyTests(){
  // you need to pass these three config option to browserify
  var b = browserify({
    cache: {},
    packageCache: {},
    fullPaths: true
  });
  b = watchify(b);
  b.on('update', function(){
    bundleTests(b);
  });

  b.add('./javascript/spec/woocommerce-card-connect.spec.js');
  bundleTests(b);
}

function bundleTests(b){
  b.bundle()
    .pipe(source('woocommerce-card-connect.spec.js'))
    .pipe(gulp.dest('./javascript/spec/bundle'));
}
