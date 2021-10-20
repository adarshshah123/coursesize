#Course Size Report 
 [![Build Status](https://travis-ci.org/catalyst/moodle-report_coursesize.svg?branch=master)](https://travis-ci.org/catalyst/moodle-report_coursesize)

Copyright 2014 Catalyst IT http://www.catalyst.net.nz

This plugin provides approximate disk usage by Moodle courses.

There are 2 known shortcomings with this plugin
* If the same file is used multiple times within a course, the report will report an inflated disk usage figure as the files
  will be counted each time even though Moodle only stores one copy of the file on disk.
* If the same file is used within multiple courses it will be counted in each course and there is no indicator within the
  report to inform the user if they delete the course or files within the course they will not free that amount from disk.

Imporvements in plugin
* If the same files are shared with atleast two courses it will list all the files name, files size and calculate the sum
  of all shared files size which are shared to other courses.
* Now the user will able to know that amount of disk(total shared files size shared with other courses) will not free if
  they delete the course or files with in the course.
* Now the user will able to know that amount of disk(total coursedata shared throughout the site).
* For small size files it will count size as zero. 
  

It should be possible to improve the report to address these issues - we'd greatly appreciate any patches to improve the plugin!
 