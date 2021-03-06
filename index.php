<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Version information
 *
 * @package    report_coursesize
 * @copyright  2014 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/csvlib.class.php');

admin_externalpage_setup('reportcoursesize');

// Dirty hack to filter by coursecategory - not very efficient.
$coursecategory = optional_param('category', '', PARAM_INT);
$download = optional_param('download', '', PARAM_INT);

// If we should show or hide empty courses.
if (!defined('REPORT_COURSESIZE_SHOWEMPTYCOURSES')) {
    define('REPORT_COURSESIZE_SHOWEMPTYCOURSES', false);
}
// How many users should we show in the User list.
if (!defined('REPORT_COURSESIZE_NUMBEROFUSERS')) {
    define('REPORT_COURSESIZE_NUMBEROFUSERS', 10);
}
// How often should we update the total sitedata usage.
if (!defined('REPORT_COURSESIZE_UPDATETOTAL')) {
    define('REPORT_COURSESIZE_UPDATETOTAL', 1 * DAYSECS);
}

$reportconfig = get_config('report_coursesize');
if (!empty($reportconfig->filessize) && !empty($reportconfig->filessizeupdated)
    && ($reportconfig->filessizeupdated > time() - REPORT_COURSESIZE_UPDATETOTAL)) {
    // Total files usage has been recently calculated, and stored by another process - use that.
    $totalusage = $reportconfig->filessize;
    $totaldate = date("Y-m-d H:i", $reportconfig->filessizeupdated);
} else {
    // Check if the path ends with a "/" otherwise an exception will be thrown.
    $sitedatadir = $CFG->dataroot;
    if (is_dir($sitedatadir)) {
        // Only append a "/" if it doesn't already end with one.
        if (substr($sitedatadir, -1) !== '/') {
            $sitedatadir .= '/';
        }
    }

    // Total files usage either hasn't been stored, or is out of date.
    $totaldate = date("Y-m-d H:i", time());
    $totalusage = get_directory_size($sitedatadir);
    set_config('filessize', $totalusage, 'report_coursesize');
    set_config('filessizeupdated', time(), 'report_coursesize');
}

$sizemb = ' ' . get_string('sizemb');
$totalusagereadable = ceil($totalusage / 1048576) . $sizemb;

// TODO: display the sizes of directories (other than filedir) in dataroot
// eg old 1.9 course dirs, temp, sessions etc.

// Generate a full list of context sitedata usage stats.

$subinsql = 'SELECT cx.id, cx.contextlevel, cx.instanceid,
            TRIM(rtrim(CONCAT("/", SUBSTRING_INDEX(path, "/", -1))) FROM cx.path) as path,
            cx.depth, size.filessize, size.contenthash, size.filename, size.component' .
        ' FROM {context} cx';
$subsql = 'SELECT f.contextid, f.contenthash, f.filename, sum(f.filesize) as filessize, f.component' .
' FROM {files} f';
$wherebackup = ' WHERE component like \'backup\' AND referencefileid IS NULL';
$groupby = ' GROUP BY f.contextid';
$sizesql = "SELECT t.id,t.contextlevel, t.instanceid, t.path, t.depth, t.filessize as filessize,
            backupsize.filessize as backupsize, t.contenthash, t.filename, t.component
            FROM
            (   $subinsql
                INNER JOIN
                ( $subsql WHERE f.filename <> '.' $groupby ) size ON cx.id = size.contextid
            ) as t
            LEFT JOIN
            (
                SELECT f.contextid, sum(f.filesize) as filessize
                FROM {files} f
                $wherebackup $groupby
            ) backupsize ON t.id = backupsize.contextid
            group by  t.path, t.contenthash
            order by t.depth ASC, t.path ASC";
$cxsizes = $DB->get_recordset_sql($sizesql);
$coursesizes = array(); // To track a mapping of courseid to filessize.
$coursebackupsizes = array(); // To track a mapping of courseid to backup filessize.
$usersizes = array(); // To track a mapping of users to filesize.
$systemsize = $systembackupsize = 0;

// This seems like an in-efficient method to filter by course categories as we are not excluding them from the main list.
$coursesql = 'SELECT cx.id, c.id as courseid ' .
    'FROM {course} c ' .
    ' INNER JOIN {context} cx ON cx.instanceid=c.id AND cx.contextlevel = ' . CONTEXT_COURSE;
$params = array();
$courseparams = array();
$extracoursesql = '';
if (!empty($coursecategory)) {
    $context = context_coursecat::instance($coursecategory);
    $coursecat = core_course_category::get($coursecategory);
    $courses = $coursecat->get_courses(array('recursive' => true, 'idonly' => true));
    if (!empty($courses)) {
        list($insql, $courseparams) = $DB->get_in_or_equal($courses, SQL_PARAMS_NAMED);
        $extracoursesql = ' WHERE c.id ' . $insql;
    } else {
        // Don't show any courses if category is selected but category has no courses.
        // This stuff really needs a rewrite!
        $extracoursesql = ' WHERE c.id is null';
    }
}
$coursesql .= $extracoursesql;
$params = array_merge($params, $courseparams);
$courselookup = $DB->get_records_sql($coursesql, $params);
foreach ($cxsizes as $cxdata) {
    $contextlevel = $cxdata->contextlevel;
    $instanceid = $cxdata->instanceid;
    $contextsize = $cxdata->filessize;
    $contextbackupsize = (empty($cxdata->backupsize) ? 0 : $cxdata->backupsize);
    if ($contextlevel == CONTEXT_USER) {
        $usersizes[$instanceid] = $contextsize;
        $userbackupsizes[$instanceid] = $contextbackupsize;
        continue;
    }
    if ($contextlevel == CONTEXT_COURSE) {
        $coursesizes[$instanceid] = $contextsize;
        $coursebackupsizes[$instanceid] = $contextbackupsize;
        continue;
    }
    if (($contextlevel == CONTEXT_SYSTEM) || ($contextlevel == CONTEXT_COURSECAT)) {
        $systemsize = $contextsize;
        $systembackupsize = $contextbackupsize;
        continue;
    }
    // Not a course, user, system, category, see it it's something that should be listed under a course
    // Modules & Blocks mostly.
    $path = explode('/', $cxdata->path);
    array_shift($path); // Get rid of the leading (empty) array item.
    $success = false; // Course not yet found.
    // Look up through the parent contexts of this item until a course is found.
    while (count($path)) {
        $contextid = array_pop($path);
        if (isset($courselookup[$contextid])) {
            $success = true; // Course found.
            // Record the files for the current context against the course.
            $courseid = $courselookup[$contextid]->courseid;
            if (!empty($coursesizes[$courseid])) {
                $coursesizes[$courseid] += $contextsize;
                $coursebackupsizes[$courseid] += $contextbackupsize;
            } else {
                $coursesizes[$courseid] = $contextsize;
                $coursebackupsizes[$courseid] = $contextbackupsize;
            }
            break;
        }
    }
    if (!$success) {
        // Didn't find a course
        // A module or block not under a course?
        $systemsize += $contextsize;
        $systembackupsize += $contextbackupsize;
    }
}
$cxsizes->close();
$sql = "SELECT c.id, c.shortname, c.category, ca.name FROM {course} c "
       ."JOIN {course_categories} ca on c.category = ca.id".$extracoursesql;
$courses = $DB->get_records_sql($sql, $courseparams);
$coursetable = new html_table();
$coursetable->align = array('center', 'center', 'center', 'center');
$coursetable->head = array(get_string('course'),
                           get_string('category'),
                           get_string('backupsize', 'report_coursesize'),
                           get_string('diskusage', 'report_coursesize'));
$coursetable->data = array();
arsort($coursesizes);
$totalsize = 0;
$totalbackupsize = 0;
$downloaddata = array();
$downloaddata[] = array(get_string('course'),
                           get_string('category'),
                           get_string('backupsize', 'report_coursesize'),
                           get_string('diskusage', 'report_coursesize'));
foreach ($coursesizes as $courseid => $size) {
    if (empty($courses[$courseid])) {
        continue;
    }
    $backupsize = $coursebackupsizes[$courseid];
    $backupsize = ceil($backupsize / 1048576);
    $totalsize = $totalsize + ceil($size / 1048576);
    $totalbackupsize  = $totalbackupsize + $backupsize;
    $course = $courses[$courseid];
    $coursecontext = context_course::instance($course->id);
    $contextcheck = $coursecontext->path . '%';
    $course->shortname = format_string($course->shortname, true, ['context' => $coursecontext]);
    $course->name = format_string($course->name, true, ['context' => $coursecontext]);
    $row = array();
    $readablesize = ceil($size / 1048576) . $sizemb;
    $row[] = '<a href = "'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">' . $course->shortname . '</a>';
    $row[] = '<a href = "'.$CFG->wwwroot.'/course/index.php?categoryid='.$course->category.'">' . $course->name . '</a>';
    $a = new stdClass;
    $a->bytes = $size;
    $a->shortname = $course->shortname;
    $a->backupbytes = $backupsize;
    $bytesused = get_string('coursebytes', 'report_coursesize', $a);
    $backupbytesused = get_string('coursebackupbytes', 'report_coursesize', $a);
    $summarylink = new moodle_url('/report/coursesize/course.php', array('id' => $course->id));
    $summary = html_writer::link($summarylink, ' '.get_string('coursesummary', 'report_coursesize'));
    $row[] = "<span id=\"backupsize_".$course->shortname."\" title=\"$backupbytesused\">" . $backupsize. "$sizemb</span>";
    $row[] = "<span id=\"coursesize_".$course->shortname."\" title=\"$bytesused\">$readablesize</span>".$summary;
    $coursetable->data[] = $row;
    $downloaddata[] = array($course->shortname, $course->name,
                    str_replace(',', '', $backupsize . "$sizemb"), str_replace(',', '', $readablesize));
    unset($courses[$courseid]);
}
// Now add the courses that had no sitedata into the table.
if (REPORT_COURSESIZE_SHOWEMPTYCOURSES) {
    $a = new stdClass;
    $a->bytes = 0;
    $a->backupbytes = 0;
    foreach ($courses as $cid => $course) {
        $course->shortname = format_string($course->shortname, true, context_course::instance($course->id));
        $a->shortname = $course->shortname;
        $bytesused = get_string('coursebytes', 'report_coursesize', $a);
        $bytesused = get_string('coursebackupbytes', 'report_coursesize', $a);
        $row = array();
        $row[] = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$course->id.'">' . $course->shortname . '</a>';
        $row[] = "<span title=\"$bytesused\">0$sizemb</span>";
        $row[] = "<span title=\"$bytesused\">0$sizemb</span>";
        $coursetable->data[] = $row;
    }
}
// Now add the totals to the bottom of the table.
$coursetable->data[] = array(); // Add empty row before total.
$downloaddata[] = array();
$row = array();
$row[] = get_string('total');
$row[] = '';
$row[] = $totalbackupsize  . $sizemb;
$row[] = $totalsize  . $sizemb;
$coursetable->data[] = $row;
$downloaddata[] = array(get_string('total'), '', str_replace(',', '', $totalbackupsize . $sizemb),
                  str_replace(',', '', $totalsize) . $sizemb);
unset($courses);
if (!empty($usersizes)) {
    arsort($usersizes);
    $usertable = new html_table();
    $usertable->align = array('right', 'right');
    $usertable->head = array(get_string('user'), get_string('diskusage', 'report_coursesize'));
    $usertable->data = array();
    $usercount = 0;
    foreach ($usersizes as $userid => $size) {
        $usercount++;
        $user = $DB->get_record('user', array('id' => $userid));
        $row = array();
        $row[] = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$userid.'">' . fullname($user) . '</a>';
        $row[] = ceil($size / 1048576) . $sizemb;
        $usertable->data[] = $row;
        if ($usercount >= REPORT_COURSESIZE_NUMBEROFUSERS) {
            break;
        }
    }
    unset($users);
}
$systemsizereadable = ceil($systemsize / 1048576) . $sizemb;
$systembackupreadable = ceil($systembackupsize / 1048576) . $sizemb;

// Add in Course Cat including dropdown to filter.
$url = '';
$catlookup = $DB->get_records_sql('select id,name from {course_categories}');
$options = array('0' => 'All Courses' );
foreach ($catlookup as $cat) {
    $options[$cat->id] = format_string($cat->name, true, context_system::instance());
}
$option = core_course_category::make_categories_list('moodle/course:changecategory');
$options = array_replace($options, $option);
// Add in download option. Exports CSV.

if ($download == 1) {
    $downloadfilename = clean_filename ( "export_csv" );
    $csvexport = new csv_export_writer ( 'commer' );
    $csvexport->set_filename ( $downloadfilename );
    foreach ($downloaddata as $data) {
        $csvexport->add_data ($data);
    }
    $csvexport->download_file ();
}
// All the processing done, the rest is just output stuff.

print $OUTPUT->header();
if (empty($coursecat)) {
    print $OUTPUT->heading(get_string("sitefilesusage", 'report_coursesize'));
    print '<strong>' . get_string("totalsitedata", 'report_coursesize', $totalusagereadable) . '</strong> ';
    print get_string("sizerecorded", "report_coursesize", $totaldate) . "<br/><br/>\n";
    print get_string('catsystemuse', 'report_coursesize', $systemsizereadable) . "<br/>";
    print get_string('catsystembackupuse', 'report_coursesize', $systembackupreadable) . "<br/>";
    if (!empty($CFG->filessizelimit)) {
        print get_string("sizepermitted", 'report_coursesize', ceil($CFG->filessizelimit)) . "<br/>\n";
    }
}
$heading = get_string('coursesize', 'report_coursesize');
if (!empty($coursecat)) {
    $heading .= " - ".$coursecat->name;
}
print $OUTPUT->heading($heading);

$desc = get_string('coursesize_desc', 'report_coursesize');

if (!REPORT_COURSESIZE_SHOWEMPTYCOURSES) {
    $desc .= ' '. get_string('emptycourseshidden', 'report_coursesize');
}
print $OUTPUT->box($desc);

$filter = $OUTPUT->single_select($url, 'category', $options);
$filter .= $OUTPUT->single_button(new moodle_url('index.php', array('download' => 1, 'category' => $coursecategory )),
                                  get_string('exportcsv', 'report_coursesize'), 'post', ['class' => 'coursesizedownload']);

print $OUTPUT->box($filter)."<br/>";
print html_writer::table($coursetable);
if (empty($coursecat)) {
    print $OUTPUT->heading(get_string('userstopnum', 'report_coursesize', REPORT_COURSESIZE_NUMBEROFUSERS));

    if (!isset($usertable)) {
        print get_string('nouserfiles', 'report_coursesize');
    } else {
        print html_writer::table($usertable);
    }
}
print $OUTPUT->footer();
