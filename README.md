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



Collect errors:

    set_error_handler(array('DebugHelper\Error', 'handler'));


Custom Profiling
----------------

Identifies the start of an event. The first parameter indicates the group for the event.

    \DebugHelper::timer('Group 1', 'Some message');


After the process has finished the timer can be reported through the command

    ./bin/phpdebug timer

That show a report in seconds for each of the groups.

    +-----------+-------+--------+--------+---------------------+---------+
    | Group     | Times | Min    | Max    | Average             | Total   |
    +-----------+-------+--------+--------+---------------------+---------+
    | Group 1   | 142   | 0.4272 | 1.3292 | 0.63779647887324    | 90.5671 |
    | Group 2   | 142   | 0.0001 | 0.0037 | 0.00015633802816901 | 0.0222  |
    +-----------+-------+--------+--------+---------------------+---------+
    Total 138.3751 time taken
