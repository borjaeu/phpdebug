phpDebug
========

Set of libraries to help debugging profiling on php.

Profiling with Xdebug
---------------------

To start profiling:

    \DebugHelper::watch(true);

To finish the profiling 

    \DebugHelper::endWatch();

This will write several files in the temp folder (by default the directory temp/ in the package root) with the timestamp of the profile.

In order to watch the debug information:

    \DebugHelper\Gui::renderLoadsHtml('2015_04_29_12_26_25');
