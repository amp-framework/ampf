# ampf - ampf MVC PHP framework

ampf is a PHP MVC framework designed to be simple and stupid. The core of ampf is a dependency-injenction container which does *no magic* and can be configured to your needs. The idea behind ampf is that this DI container handles all your PHP objects, regardless how they act and what they do. You configure it the way *you* want and it will handle everything.

This approach has some big advantages (e.g. ampf won't open a database connection unless you really are going to use it, it won't open up a PHP session unless you really are going to use it, etc), as the objects are created on-demand when someone asks the DI container to give him a specific instance.

You configure the ampf framework through normal .php files which then return an array with configuration values. 

ampf is still under heavy development and hence its API may change heavily in future.

## How to build a webapp based on ampf

When you want to build a new webapp based on ampf, you'd have to do the folllowing:

```
mkdir new-project
cd new-project
git init
git submodule add git@github.com:rpasing/ampf.git
cd ampf
git checkout master
git pull
cd ..
mkdir config
cp ampf/config/default.php config/
cp ampf/config/http.php config/
cp ampf/config/local.php config/
mkdir public
cp ampf/public/index.php public/
mkdir src
mkdir -p views/http
```

Now check the file public/index.php and make appropriate changes to the paths, more specifically you would have at least to change '../src' to '../ampf/src'. A sample public/index.php might look the following:

```
[...]
$config = \ampf\ApplicationContext::boot(
	array(
		(realpath((realpath(__DIR__) . '/../src/')) . '/'),
		(realpath((realpath(__DIR__) . '/../ampf/src/')) . '/'),
	),
	array(
		(realpath(__DIR__) . '/../config/default.php'),
		(realpath(__DIR__) . '/../config/http.php'),
		(realpath(__DIR__) . '/../config/local.php'),
	)
);
[...]
```

With these changes, the DI container of ampf will now look into the paths src/ and ampf/src/ to look for sourcecode files. So when you e.g. request an instance of a class \namespace\dir1\dir2\Class, ampf will search in the following files for that class:
src/namespace/dir1/dir2/Class.php
ampf/src/namespace/dir1/dir2/Class.php

Please also take a look at the files in the config/ directory. These files should be pretty much self-explaining. You e.g. configure the DI services in the config/default.php file, the http routes in the config/http.php file and your database connection string in the config/local.php file.

