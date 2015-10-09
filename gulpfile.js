var gulp = require('gulp'),
    sass = require('gulp-sass'),
    minifycss = require('gulp-minify-css'),
    del = require('del'),
    uglify = require('gulp-uglify');


gulp.task('clean', function(){
  return del(['_sass/vendor']);
});

gulp.task('sass', function(){
    return gulp
        .src('bower_components/foundation/scss/**/*.scss')
        .pipe(gulp.dest('_sass/vendor/foundation'));
});

gulp.task('default', ['clean'], function() {
    gulp.start('sass');
});
