Copy and Synchronize Attempts Report
==================================================

Authored by Kyle Temkin, working for Binghamton University <http://www.binghamton.edu>

Requirements
---------------

Requires the Quiz Synchronization plugin, which can be found on [GitHub](http://source.bumoodle.com/local_quizsync).


Description
---------------

This report allows quiz attempts to be copied from one user to another; it works particularly well for group and partner projects. When used with the Partner question type, the report can be used to synchronize attempts between student-selected partners.

Future versions will likely include the ability to synchronize attempts between members of a group or grouping.

Installation
-----------------

To install Moodle 2.1+ using git, execute the following commands in the root of your Moodle install:

    git clone git://github.com/bumoodle/moodle-quiz_copyattempt.git mod/quiz/report/copyattempt
    echo '/mod/quiz/report/copyattempt' >> .git/info/exclude
    
Or, extract the following zip in your_moodle_root/mod/quiz/report/copyattempt:

    https://github.com/bumoodle/moodle-quiz_copyattempt/zipball/master
