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
 * Course breakdown.
 *
 * @package    report_coursesize
 * @copyright  2017 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$courseid = required_param('id', PARAM_INT);

admin_externalpage_setup('reportcoursesize');

$course = $DB->get_record('course', array('id' => $courseid));
$context = context_course::instance($course->id);
$contextcheck = $context->path . '%';
$sizesql = "SELECT a.component, SUM(a.filesize) as filesize
              FROM (SELECT DISTINCT f.contenthash, f.component, f.filesize
                    FROM {files} f
                    JOIN {context} ctx ON f.contextid = ctx.id
                    WHERE ".$DB->sql_concat('ctx.path', "'/'")." LIKE ?
                       AND f.filename != '.') a
             GROUP BY a.component";
$cxsizes = $DB->get_recordset_sql($sizesql, array($contextcheck));
$coursetable = new html_table();
$coursetable->align = array('right', 'right');
$coursetable->head = array(get_string('plugin'),
    get_string('size'));
$coursetable->data = array();
$sizemb = ' ' . get_string('sizemb');
foreach ($cxsizes as $cxdata) {
    $row = array();
    $row[] = $cxdata->component;
    $row[] = round(ceil($cxdata->filesize / 1048576)) . $sizemb;

    $coursetable->data[] = $row;
}
$cxsizes->close();
// Listing file name with size which are shared with other courses.
$sizesql = "SELECT  table1.contenthash, table1.path, table1.filename, table1.filesize, table1.contextlevel
FROM
(
    SELECT  t.contenthash, t.filename,
     t.filesize,   t.component, t.id, t.path, t.contextlevel
    FROM
    (
        SELECT cx.id, cx.contextlevel, cx.instanceid,
                 TRIM(CONCAT('/', cx.id) FROM cx.path) as path,
               cx.depth, size.filesize, size.contenthash,
               size.filename, size.component
        FROM {context} cx
            INNER JOIN
            (
                SELECT f.contextid, f.contenthash, f.filename,
                f.filesize, f.component
                FROM {files} f where f.filename<>'.'
                GROUP BY f.contextid
            ) size ON cx.id = size.contextid
) t
) as table1 ,
(
    SELECT t.contenthash, t.filename, t.filesize,
                    t.component, t.id, t.path, t.contextlevel
    FROM
    (
        SELECT cx.id, cx.contextlevel, cx.instanceid,
               TRIM(CONCAT('/', cx.id) FROM cx.path) as path,
               cx.depth, size.filesize, size.contenthash,
               size.filename, size.component
        FROM {context} cx
            INNER JOIN
            (
                SELECT f.contextid, f.contenthash, f.filename,
                f.filesize, f.component
                FROM {files} f where f.filename<>'.'
                GROUP BY f.contextid
            ) size ON cx.id = size.contextid
) t
)table2
WHERE table1.contenthash = table2.contenthash and table1.contextlevel=table2.contextlevel
      and table1.path <> table2.path
      and table1.path like ?
group by table1.contenthash
";
$sharedfilessize = $DB->get_recordset_sql($sizesql, array($contextcheck));
$sharedfiletable = new html_table();
$sharedfiletable->align = array('right', 'right');
$sharedfiletable->head = array(get_string('sharedfilesname', 'report_coursesize'),
    get_string('size'));
$sharedfiletable->data = array();
foreach ($sharedfilessize as $sharedfile) {
    $row = array();
    $row[] = $sharedfile->filename;
    $row[] = round(ceil($sharedfile->filesize / 1048576)) . "MB";
    $sharedfiletable->data[] = $row;
}
// Calculate filesize shared with other courses.
$sizesql = "SELECT SUM(final.filesize)
FROM
(
    SELECT table1.contenthash, table1.path,
    table1.filename, table1.filesize, table1.contextlevel
    FROM
    (
        SELECT  t.contenthash, t.filename, t.filesize,
        t.component, t.id, t.path, t.contextlevel
        FROM
        (
            SELECT cx.id, cx.contextlevel, cx.instanceid,
            TRIM(CONCAT('/', cx.id) FROM cx.path) as path,
            cx.depth, size.filesize, size.contenthash,
            size.filename, size.component
            FROM {context} cx
                INNER JOIN
                (
                    SELECT f.contextid, f.contenthash,
                    f.filename, f.filesize, f.component
                    FROM {files} f
                    GROUP BY f.contextid
                ) size ON cx.id = size.contextid
        ) as t
    ) as table1,
    (
        SELECT
            t.contenthash, t.filename, t.filesize, t.component, t.id, t.path, t.contextlevel
        FROM
        (
            SELECT cx.id, cx.contextlevel, cx.instanceid,
                   TRIM(CONCAT('/', cx.id) FROM cx.path) as path,
                   cx.depth, size.filesize, size.contenthash,
                   size.filename, size.component
            FROM {context} as cx
                INNER JOIN
                (
                    SELECT f.contextid, f.contenthash,
    f.filename, f.filesize, f.component
                    FROM {files} f
                    GROUP BY f.contextid
                ) size ON cx.id = size.contextid
        ) as t
    ) as table2
    WHERE table1.contenthash = table2.contenthash and table1.contextlevel=table2.contextlevel
          and table1.path <> table2.path
          and table1.path like '$contextcheck'
    group by table1.contenthash
) final";

$size = $DB->get_field_sql($sizesql);
if (!empty($size)) {
    $size = round(ceil($size / 1048576)) . $sizemb;
}
// Now add the total shared size to the bottom of the table.
$sharedfiletable->data[] = array(); // Add empty row before total.
$row = array();
$row[] = get_string('total');
$row[] = round(ceil($size)) . $sizemb;
$sharedfiletable->data[] = $row;
// All the processing done, the rest is just output stuff.

print $OUTPUT->header();
print $OUTPUT->heading(get_string('coursesize', 'report_coursesize'). " - ". format_string($course->fullname));
print $OUTPUT->box(get_string('coursereport', 'report_coursesize'));
if (!empty($size)) {
    print $OUTPUT->box(get_string('sharedusagecourse', 'report_coursesize', $size));
    print $OUTPUT->box(get_string('recover', 'report_coursesize', $size));
}
print html_writer::table($coursetable);
// Displaying shared file list table when debug mode is on.

$debugdisplay = get_config('core', 'debugdisplay');
$debugstringids = get_config('core', 'debugstringids');
$debugvalidators = get_config('core', 'debugvalidators');
$debuginfo = get_config('core', 'debuginfo');
if ($debugdisplay == 1 || $debugstringids == 1 || $debugvalidators == 1 || $debuginfo == 1) {
    if (!empty($sharedfile->filesize)) {
        echo '<h2>Files list shared with other courses</h2>';
        print html_writer::table($sharedfiletable);
    }
}
print $OUTPUT->footer();
