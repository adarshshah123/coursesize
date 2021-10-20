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
$contextcheck = $context->path . '/%';
$sizesql = "SELECT DISTINCT a.contenthash, a.component, SUM(a.filesize) as filesize
              FROM (SELECT  f.contenthash, f.component, f.filesize
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
foreach ($cxsizes as $cxdata) {
    $row = array();
    $row[] = $cxdata->component;
    $row[] = number_format($cxdata->filesize / 1000000, 2) . "MB";
    $coursetable->data[] = $row;
}
$cxsizes->close();
// Natural join between context table and files table.
$subsql = "SELECT cx.path, f.contextid, cx.contextlevel,f.filearea, f.filesize, f.contenthash, f.filename
                FROM {files} f, {context} cx
                WHERE (cx.id = f.contextid AND f.filename!=  '.')
                AND f.filearea != 'draft' GROUP BY cx.id";

// Self join of subsql table on context_id and retrieving info from the table.
$sql = "SELECT t1.contenthash,t1.contextlevel, t1.path, t1.filesize, t1.filename as filename, t1.contextid
        FROM ($subsql) as t1,
        ($subsql) as t2 WHERE (t1.contenthash=t2.contenthash AND t1.contextid!=t2.contextid) AND
        ".$DB->sql_concat('t1.path', "'/'")." LIKE ? GROUP BY t1.contextid";

// Listing all shared file.
$sharedfilessize = $DB->get_recordset_sql($sql, array($contextcheck));
$sharedfiletable = new html_table();
$sharedfiletable->align = array('right', 'right');
$sharedfiletable->head = array(get_string('sharedfilesname', 'report_coursesize'),
    get_string('size'));
$sharedfiletable->data = array();
foreach ($sharedfilessize as $sharedfile) {
    $row = array();
    $row[] = $sharedfile->filename;
    $row[] = number_format($sharedfile->filesize / 1000000, 2) . "MB";
    $sharedfiletable->data[] = $row;
}

// Calculating total size of shared files.
$totalsharedfilessize = "SELECT SUM(t.filesize) as filesize FROM ($sql) as t";
$totalsharedfilessize = $DB->get_records_sql($totalsharedfilessize, array($contextcheck));

// Creating table for total shared file size.
foreach ($totalsharedfilessize as $sharedfile) {
    $sharedfiletable->data[] = array();// Add empty row before total.
    $row = array();
    $row[] = get_string('total_shared_files_size', 'report_coursesize');
    $row[] = number_format($sharedfile->filesize / 1000000, 2) . "MB";
    $sharedfiletable->data[] = $row;
}
// Calculate filesize shared with other courses.
$sizesql = "SELECT SUM(filesize) FROM (SELECT DISTINCT a.contenthash, a.component, SUM(a.filesize) as filesize
            FROM (SELECT  f.contenthash, f.component, f.filesize
                FROM {files} f
                JOIN {context} ctx ON f.contextid = ctx.id
                WHERE ".$DB->sql_concat('ctx.path', "'/'")." LIKE ?
                    AND f.filename != '.') a
            GROUP BY a.component) b";
$size = $DB->get_field_sql($sizesql, array($contextcheck, $contextcheck));
if (!empty($size)) {
    $size = number_format($size / 1000000, 2) . "MB";
}
// Now add the totals to the bottom of the shared file table .
$coursetable->data[] = array(); // Add empty row before total.
$row = array();
$row[] = get_string('total_course_size', 'report_coursesize');
$row[] = $size;
$coursetable->data[] = $row;

// All the processing done, the rest is just output stuff.
print $OUTPUT->header();

print $OUTPUT->heading(get_string('coursesize', 'report_coursesize'). " - ". format_string($course->fullname));
print $OUTPUT->box(get_string('coursereport', 'report_coursesize'));

print html_writer::table($coursetable);
$filesize = "0";
if (!empty($sharedfile->filesize)) {
    print $OUTPUT->box(get_string('sharedusagecourse', 'report_coursesize', number_format($sharedfile->filesize / 1000000, 2)));
} else {
    print $OUTPUT->box(get_string('sharedusagecourse', 'report_coursesize', $filesize));
}
if (!empty($sharedfile->filesize)) {
    echo '  <h2>Shared files size</h2>';
    print html_writer::table($sharedfiletable);
}
print $OUTPUT->footer();
