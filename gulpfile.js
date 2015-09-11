var
	browserify = require('browserify'),
	watchify = require('watchify'),
	gulp = require('gulp'),
	reactify = require('reactify'),
	source = require('vinyl-source-stream'),
	jshint = require('gulp-jshint'),
	uglify = require('gulp-uglify'),
	header = require('gulp-header'),
	buffer = require('vinyl-buffer'),
	rename = require('gulp-rename'),
	eol = require('gulp-eol'),
	gutil = require('gulp-util'),
	crlf = '\n',
	jshintStart = '/* jshint ignore:end */' + crlf + '\'use strict\';' + crlf + crlf,
	jshintEnd = crlf + crlf + '/* jshint ignore:start */',
	cfg = {
		license:
crlf +
'/*!' + crlf +
' * Copyright 2004-2015, AfterLogic Corp.' + crlf +
' * Licensed under AGPLv3 license or AfterLogic license' + crlf +
' * if commerical version of the product was purchased.' + crlf +
' * See the LICENSE file for a full license statement.' + crlf +
' */' + crlf + crlf,
		paths: {},
		watch: [],
		summary: {
			verbose: true,
			reasonCol: 'cyan,bold',
			codeCol: 'green'
		},
		uglify: {
			mangle: true,
			compress: true,
			drop_console: true,
			preserveComments: 'some'
		}
	}
;

cfg.paths.js = {
	all: {
		dest: './static/js/',
		name: 'all.js',
		min: 'all.min.js',
		afterlogic: true,
		watch: true,
		src: [
			"./core/js/entry.js"
		]
	}
};

function jsTask(sName, oData)
{
	if (oData && oData.src)
	{
		gulp.task('js:' + sName, function() {
			var b = browserify(oData.src, {transform: [reactify], paths: ['./']});
			return	b.bundle()
				.pipe(source(oData.name))
				.pipe(buffer())
				.pipe(header(((oData.afterlogic ? cfg.license : '') || '') + jshintEnd))
				.pipe(eol('\n', true))
				.pipe(gulp.dest(oData.dest));
		});

		if (oData.watch)
		{
			gulp.task('js:' + sName + ':watch', function() {
				var bundler = watchify(browserify(oData.src));
				
				function rebundle() {
					return bundler.bundle()
						.pipe(source(oData.name))
						.pipe(buffer())
						.pipe(header(((oData.afterlogic ? cfg.license : '') || '') + jshintEnd))
						.pipe(eol('\n', true))
						.pipe(gulp.dest(oData.dest));
				}
				
				bundler.on('update', rebundle);

				return rebundle();
			});
		}

		if (oData.min)
		{
			gulp.task('js:' + sName + ':min', function() {
				return gulp.src(oData.dest + oData.name)
					.pipe(rename(oData.min))
					.pipe(uglify(cfg.uglify))
					.pipe(eol('\n', true))
					.pipe(gulp.dest(oData.dest))
					.on('error', gutil.log);
			});
		}
	}
}

gulp.task('lint', function() {
  return gulp.src('./js/**/*.js')
    .pipe(jshint())
    .pipe(jshint.reporter('default'));
});

for (name in cfg.paths.js)
{
	if (cfg.paths.js.hasOwnProperty(name))
	{
		jsTask(name, cfg.paths.js[name]);
	}
}

gulp.task('b', ['js:all']);

gulp.task('m', ['lint', 'js:all:min']);

gulp.task('w', ['js:all:watch']);
