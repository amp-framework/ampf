# ampf - ampf MVC PHP framework

ampf is a PHP MVC framework designed to be simple and stupid. The core of ampf is a dependency-injenction container which does *no magic* and can be configured to your needs. The idea behind ampf is that this DI container handles all your PHP objects, regardless how they act and what they do. You configure it the way *you* want and it will handle everything.

This approach has some big advantages (e.g. ampf won't open a database connection unless you really are going to use it, it won't open up a PHP session unless you really are going to use it, etc), as the objects are created on-demand when someone asks the DI container to give him a specific instance.

You configure the ampf framework through normal .php files which then return an array with configuration values. 

ampf is still under heavy development and hence its API may change heavily in future.

## How to build a webapp based on ampf

When you want to build a new webapp based on ampf, you'd have to do the folllowing:

*TO DO*
