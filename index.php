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
 * Displays e-learning statistics data selection form and results.
 * @package    report_elearning
 * @copyright  2015 BFH-TI, Luca Bösch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Moodle E-Learning-Strategie Report
 * 
 * Main file for report
 * 
 * @see doc/html/ for documentation
 * 
 */
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->dirroot . '/lib/statslib.php');
require_once($CFG->dirroot . '/report/elearning/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');

global $CFG, $PAGE, $OUTPUT;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/report/elearning/index.php'));
$output = $PAGE->get_renderer('report_elearning');

$mform = new report_elearning_form(new moodle_url('/report/elearning/'));

if (($mform->is_submitted() && $mform->is_validated()) || (isset($_POST['download']))) {
    // Processing of the submitted form.
    $data = $mform->get_data();
    if (isset($data->elearningvisibility) AND ($data->elearningvisibility == 1)) {
        $elearningvisibility = true;
    } else if (isset($_POST['elearningvisibility']) && $_POST['elearningvisibility'] == 1) {
        $elearningvisibility = true;
    } else {
        $elearningvisibility = false;
    }
    if (isset($data->nonews) && ($data->nonews == 1)) {
        $nonews = true;
    } else if (isset($_POST['nonews']) && $_POST['nonews'] == 1) {
        $nonews = true;
    } else {
        $nonews = false;
    }
    if (isset($_POST['download']) && $_POST['download'] == 1) {
        $download = true;
    } else {
        $download = false;
    }

    $a = new stdClass();
    if (isset($_POST['elearningcategory'])) {
        $a->category = $_POST['elearningcategory'];
        $a->context = get_instancecontext($_POST['elearningcategory']);
        $resultstring = get_string('recap', 'report_elearning', $a);
        $visiblecount = get_coursecategorycoursecount(get_coursecategorypath($_POST['elearningcategory']), true);
        $invisiblecount = get_coursecategorycoursecount(get_coursecategorypath($_POST['elearningcategory']), false);
    } else {
        $a->category = $data->elearningcategory;
        $a->context = get_instancecontext($data->elearningcategory);
        $resultstring = get_string('recap', 'report_elearning', $a);
        $visiblecount = get_coursecategorycoursecount(get_coursecategorypath($data->elearningcategory), true);
        $invisiblecount = get_coursecategorycoursecount(get_coursecategorypath($data->elearningcategory), false);
    }

    if ($elearningvisibility == true) {
        $coursecount = $visiblecount;
    } else {
        $coursecount = $invisiblecount;
        $coursecount += $visiblecount;
    }

    if ($coursecount > 0) {
        // There are results.
        if ($coursecount == 1) {
            $a->count = 1;
            if ($elearningvisibility == true) {
                $a->visibility = get_string('shown', 'report_elearning');
            } else {
                $a->visibility = get_string('hiddenandshown', 'report_elearning');
            }
            $resultstring .= get_string('courseincategorycount', 'report_elearning', $a);
        } else {
            $a->count = $coursecount;
            if ($elearningvisibility == true) {
                $a->visibility = get_string('shownplural', 'report_elearning');
            } else {
                $a->visibility = get_string('hiddenandshownplural', 'report_elearning');
            }
            $resultstring .= get_string('courseincategorycountplural', 'report_elearning', $a);
        }
        $resultstring .= "<br />&#160;<br />\n";
        // Write a table with 23 columns.
        $table = new html_table();
        // Added up courses in this category, recursive.
        $totalheaderrow = new html_table_row();
        $totalheadercell = new html_table_cell(get_string('categorytotal', 'report_elearning'));
        $totalheadercell->header = true;
        $totalheadercell->colspan = 23;
        $totalheadercell->attributes['class'] = 'c0';
        $totalheaderrow->cells = array($totalheadercell);
        $table->data[] = $totalheaderrow;

        $headerrow = new html_table_row();
        $totalheadercells = array();
        $totalheadertitles = array(get_string('id', 'report_elearning'),
            get_string('category', 'report_elearning'),
            get_string('file', 'report_elearning'),
            get_string('directory', 'report_elearning'),
            get_string('page', 'report_elearning'),
            get_string('label', 'report_elearning'),
            get_string('url', 'report_elearning'),
            get_string('assessment', 'report_elearning'),
            get_string('forum', 'report_elearning'),
            get_string('feedback', 'report_elearning'),
            get_string('quiz', 'report_elearning'),
            get_string('scheduler', 'report_elearning'),
            get_string('survey', 'report_elearning'),
            get_string('database', 'report_elearning'),
            get_string('glossary', 'report_elearning'),
            get_string('journal', 'report_elearning'),
            get_string('wiki', 'report_elearning'),
            get_string('choice', 'report_elearning'),
            get_string('choicegroup', 'report_elearning'),
            get_string('chat', 'report_elearning'),
            get_string('workshop', 'report_elearning'),
            get_string('sumwithoutfiles', 'report_elearning'),
            get_string('sum', 'report_elearning')
            );
        foreach ($totalheadertitles as $totalheadertitle) {
            $cell = new html_table_cell($totalheadertitle);
            $cell->header = true;
            $totalheadercells[] = $cell;
        }
        $headerrow->cells = $totalheadercells;
        $table->data[] = $headerrow;

        $rec = $DB->get_records_sql(get_tablesql($a->category, $elearningvisibility, $nonews));
        foreach ($rec as $records) {
            $id = $records->mccid;
            $scheduledatet = $records->category;
            $files = $records->files;
            $directories = $records->directories;
            $pages = $records->pages;
            $labels = $records->labels;
            $links = $records->links;
            $assigns = $records->assigns;
            $forums = $records->forums;
            $feedbacks = $records->feedbacks;
            $quizzes = $records->quizs;
            $schedulers = $records->schedulers;
            $surveys = $records->surveys;
            $dbs = $records->dbs;
            $glossaries = $records->glossaries;
            $journals = $records->journals;
            $wikis = $records->wikis;
            $choices = $records->choices;
            $choicesgroup = $records->choicesgroup;
            $chats = $records->chats;
            $workshops = $records->workshops;
            $table->data[] = array("<a href=\"$CFG->wwwroot/course/index.php?categoryid=" . $id .
                "\" target=\"_blank\">" . $id . "</a>", "<a href=\"$CFG->wwwroot/course/index.php?categoryid=" . $id .
                "\" target=\"_blank\">" . get_stringpath($records->mccpath) . "</a><!--(" . $records->mccpath . ")-->",
                $files,
                $directories,
                $pages,
                $labels,
                $links,
                $assigns,
                $forums,
                $feedbacks,
                $quizzes,
                $schedulers,
                $surveys,
                $dbs,
                $glossaries,
                $journals,
                $wikis,
                $choices,
                $choicesgroup,
                $chats,
                $workshops,
                (
                        $pages +
                        $labels +
                        $links +
                        $assigns +
                        $forums +
                        $feedbacks +
                        $quizzes +
                        $schedulers +
                        $surveys +
                        $dbs +
                        $glossaries +
                        $journals +
                        $wikis +
                        $choices +
                        $choicesgroup +
                        $chats +
                        $workshops
                        ),
                (
                        $files +
                        $directories +
                        $pages +
                        $labels +
                        $links +
                        $assigns +
                        $forums +
                        $feedbacks +
                        $quizzes +
                        $schedulers +
                        $surveys +
                        $dbs +
                        $glossaries +
                        $journals +
                        $wikis +
                        $choices +
                        $choicesgroup +
                        $chats +
                        $workshops
                        )
                );
        }

        // Single courses in this category, non-recursive.
        $detailheaderrow = new html_table_row();
        $detailheadercell = new html_table_cell(get_string('justcategory', 'report_elearning'));
        $detailheadercell->header = true;
        $detailheadercell->colspan = 23;
        $detailheaderrow->cells = array($detailheadercell);
        $table->data[] = $detailheaderrow;
        $courseheaderrow = new html_table_row();
        $headercells = array();
        $headertitles = array(
            get_string('id', 'report_elearning'),
            get_string('course', 'report_elearning'),
            get_string('file', 'report_elearning'),
            get_string('directory', 'report_elearning'),
            get_string('page', 'report_elearning'),
            get_string('label', 'report_elearning'),
            get_string('url', 'report_elearning'),
            get_string('assessment', 'report_elearning'),
            get_string('forum', 'report_elearning'),
            get_string('feedback', 'report_elearning'),
            get_string('quiz', 'report_elearning'),
            get_string('scheduler', 'report_elearning'),
            get_string('survey', 'report_elearning'),
            get_string('database', 'report_elearning'),
            get_string('glossary', 'report_elearning'),
            get_string('journal', 'report_elearning'),
            get_string('wiki', 'report_elearning'),
            get_string('choice', 'report_elearning'),
            get_string('choicegroup', 'report_elearning'),
            get_string('chat', 'report_elearning'),
            get_string('workshop', 'report_elearning'),
            get_string('sumwithoutfiles', 'report_elearning'),
            get_string('sum', 'report_elearning'));
        foreach ($headertitles as $headertitle) {
            $cell = new html_table_cell($headertitle);
            $cell->header = true;
            $headercells[] = $cell;
        }
        $courseheaderrow->cells = $headercells;
        $table->data[] = $courseheaderrow;

        if ($a->category == 0) {
            // All courses.
            if ($elearningvisibility == true) {
                $coursesincategorysql = "SELECT id"
                        . "                FROM {course}"
                        . "               WHERE visible <> 0"
                        . "                 AND id > 1"
                        . "            ORDER BY sortorder";
            } else {
                $coursesincategorysql = "SELECT id"
                        . "                FROM {course}"
                        . "               WHERE id > 1"
                        . "            ORDER BY sortorder";
            }
            $coursesincategory = $DB->get_fieldset_sql($coursesincategorysql, array($a->category));
        } else {
            if ($elearningvisibility == true) {
                $coursesincategorysql = "SELECT id"
                        . "                FROM {course}"
                        . "               WHERE category = ?"
                        . "                 AND visible <> 0"
                        . "            ORDER BY sortorder";
            } else {
                $coursesincategorysql = "SELECT id"
                        . "                FROM {course}"
                        . "               WHERE category = ?"
                        . "            ORDER BY sortorder";
            }
            $coursesincategory = $DB->get_fieldset_sql($coursesincategorysql, array($a->category));
        }
        foreach ($coursesincategory as $courseid) {
            $table->data[] = get_coursetablecontent($courseid, $elearningvisibility, $nonews);
        }
        if ($download == true) {
            $filename = "Export-E-Learning-" . date("Y-m-d-H-i-s") . ".xls";
            header("Content-type: application/x-msexcel");
            header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
            echo html_writer::table($table);
            die();
        } else {
            // Display the processed page.
            $PAGE->set_pagelayout('admin');
            $PAGE->set_heading($SITE->fullname);
            $PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'report_elearning'));
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('pluginname', 'report_elearning'));
            echo $OUTPUT->box(get_string('reportelearningdesc', 'report_elearning') . "<br />&#160;", 'generalbox mdl-align');
            $mform->display();
            echo $resultstring;
            echo html_writer::table($table);
            echo $OUTPUT->single_button(new moodle_url($PAGE->url, array('download' => 1,
            'elearningcategory' => $data->elearningcategory, 'elearningvisibility' => $elearningvisibility, 'nonews' => $nonews)),
                get_string('download', 'admin'));
        }
    } else {
        // There are no results.
        $PAGE->set_pagelayout('admin');
        $PAGE->set_heading($SITE->fullname);
        $PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'report_elearning'));
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('pluginname', 'report_elearning'));
        echo $OUTPUT->box(get_string('reportelearningdesc', 'report_elearning') . "<br />&#160;", 'generalbox mdl-align');
        $mform->display();
        echo get_string('nocourseincategory', 'report_elearning', $a);
    }
} else {
    // Form was not submitted yet.
    $PAGE->set_pagelayout('admin');
    $PAGE->set_heading($SITE->fullname);
    $PAGE->set_title($SITE->fullname . ': ' . get_string('pluginname', 'report_elearning'));
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pluginname', 'report_elearning'));
    echo $OUTPUT->box(get_string('reportelearningdesc', 'report_elearning') . "<br />&#160;", 'generalbox mdl-align');
    $mform->display();
}

/*
 * Debug flag -- if set to TRUE, debug output will be generated.
 */
$debug = true;

if ($debug) {
    ini_set('display_errors', 'On');
    error_reporting(E_ALL);
}

/**
 * This function prints a debug entry
 *
 * @param int $line Line on which function has been called
 * @param array $param Parameters (array) with data to print
 * @param bool $mustdie If true, execution stops
 */
function dbg($line, $param = null, $mustdie = false) {
    global $debug;
    if ($debug) {
        echo "<p>On line $line</p>";
        if ($param) {
            echo ""; // Vormals print_object($param).
        }
        if ($mustdie == true) {
            die();
        }
    }
}

echo $OUTPUT->footer();
// The end.