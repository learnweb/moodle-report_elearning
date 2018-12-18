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


const types = array("mod", "block");
function getHeaders($nonrecursive = false, $humanreadable = false){
    $pluginman = core_plugin_manager::instance();

    $returnarray = array("ID");

    if(!$nonrecursive){
        array_push($returnarray, "category");
    }else{
        array_push($returnarray, "course");
    }

    foreach(types as $type) {
        $pluginarray = $pluginman->get_plugins_of_type($type);
        foreach ($pluginarray as $pluigin) {
            if($type == "mod"){
                $pluginname = $pluigin -> name;
            }else{
                $pluginname = $type . "_" . $pluigin -> name;
            }
            if (!$humanreadable) {
                array_push($returnarray, $pluginname);
            } else {
                array_push($returnarray, get_string("pluginname", $pluginname) . " ($type)");
            }
        }
    }

    array_push($returnarray, "Sum");
    array_push($returnarray,"Sum without files and folders");


    return $returnarray;
}


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
        // Write a table with 24 columns.
        $table = new html_table();
        $data1 = array();
        $data2 = array();
        // Added up courses in this category, recursive.
        $totalheaderrow = new html_table_row();
        $totalheadercell = new html_table_cell(get_string('categorytotal', 'report_elearning'));
        $totalheadercell->header = true;
        $totalheadertitles = getHeaders();
        $totalheadercell->colspan = count($totalheadertitles);
        $totalheadercell->attributes['class'] = 'c0';
        $totalheaderrow->cells = array($totalheadercell);
        $data1[] = $totalheaderrow;

        $headerrow = new html_table_row();
        $totalheadercells = array();
        //first table
        $totalheadertitlesNice = getHeaders(false,true);
        foreach ($totalheadertitlesNice as $totalheadertitle) {
            $cell = new html_table_cell($totalheadertitle);
            $cell->header = true;
            $totalheadercells[] = $cell;
        }
        $headerrow->cells = $totalheadercells;
        $data1[] = $headerrow;
        $rec = get_array_for_categories(1, $totalheadertitles);

        // Single courses in this category, non-recursive.
        // ok so this seems a little bit like unnecessary work, it might be better to just include this as an option
        // however from my understanding the guy I cloned this project from is a major contributer and I just started
        // so I'll just assume that he's right, I'm wrong and this is in fact useful.
        $detailheaderrow = new html_table_row();
        $detailheadercell = new html_table_cell(get_string('justcategory', 'report_elearning'));
        $detailheadercell->header = true;
        $headertitles = getHeaders(true, true);
        $detailheadercell->colspan = count($headertitles);
        $detailheaderrow->cells = array($detailheadercell);
        $data2[] = $detailheaderrow;
        $courseheaderrow = new html_table_row();
        $headercells = array();
        // second table
        foreach ($headertitles as $headertitle) {
            $cell = new html_table_cell($headertitle);
            $cell->header = true;
            $headercells[] = $cell;
        }
        $courseheaderrow->cells = $headercells;
        $data2[] = $courseheaderrow;

        if ($a->category == 0) {
            // All courses.
            if ($elearningvisibility == true) {
                $coursesincategorysql = "SELECT id, category"
                        . "                FROM {course}"
                        . "               WHERE visible <> 0"
                        . "                 AND id > 1"
                        . "            ORDER BY sortorder";
            } else {
                $coursesincategorysql = "SELECT id, category"
                        . "                FROM {course}"
                        . "               WHERE id > 1"
                        . "            ORDER BY sortorder";
            }
            $coursesincategory = $DB->get_records_sql($coursesincategorysql, array($a->category));
        } else {
            if ($elearningvisibility == true) {
                $coursesincategorysql = "SELECT id, category"
                        . "                FROM {course}"
                        . "               WHERE category = ?"
                        . "                 AND visible <> 0"
                        . "            ORDER BY sortorder";
            } else {
                $coursesincategorysql = "SELECT id, category"
                        . "                FROM {course}"
                        . "               WHERE category = ?"
                        . "            ORDER BY sortorder";
            }
            $coursesincategory = $DB->get_records_sql($coursesincategorysql, array($a->category));
        }
        foreach ($coursesincategory as $courserec) {
            $courseid = $courserec -> id;
            $coursecat = $courserec -> category;
            $headerarray = getHeaders(true,false);
            // performing double shift so ID and course don't count.
            array_shift($headerarray);
            array_shift($headerarray);
            // same goes for the end with those "sums" trailing there
            array_pop($headerarray);
            array_pop($headerarray);
            $returnobject = $DB->get_records_sql(get_coursetablecontent($courseid, $elearningvisibility, $nonews), array($courseid));
            $tablecontent = merge_block_and_mod($returnobject, blocks_DB($courseid), $courseid);
            $returnarray = array("<a href=\"$CFG->wwwroot/course/view.php?id=" . $tablecontent->id . "\" target=\"_blank\">"
                . $tablecontent->id . "</a>",
                "<a href=\"$CFG->wwwroot/course/view.php?id="
                . $tablecontent->id . "\" target=\"_blank\">" . $tablecontent->fullname . "</a>");
            $total = 0;
            $totalnfnd = 0;
            foreach($headerarray as $plugin) {
                if (property_exists($tablecontent, $plugin)) {
                    if ($plugin != "resource" and $plugin != "folder") {
                        $totalnfnd += $tablecontent->$plugin;
                    }
                    $total += $tablecontent->$plugin;
                    array_push($returnarray, $tablecontent->$plugin);
                    if(array_key_exists($coursecat, $rec)){
                        //hooray that's the easy way
                        $rec[$coursecat] -> $plugin += $tablecontent->$plugin;
                    }else{
                        //hrmpf
                        foreach($rec as $cat){
                            if(strpos($cat->subcats, $coursecat) !== false){
                                $cat -> $plugin += $tablecontent->$plugin;
                                break;
                            }
                        }
                    }
                }else{
                    array_push($returnarray, 0);
                }
            }
            array_push($returnarray, $total, $totalnfnd);

            $data2[] = $returnarray;
        }

        foreach ($rec as $records) {
            $tablearray = array();
            $total = 0;
            $totalnfnd = 0;
            foreach ($totalheadertitles as $category){
                if($category == "ID") {
                    array_push($tablearray, "<a href=\"$CFG->wwwroot/course/index.php?categoryid=" . $records->mccid .
                        "\" target=\"_blank\">" . $records->mccid . "</a>");
                }else if($category == "category"){
                    array_push($tablearray,  "<a href=\"$CFG->wwwroot/course/index.php?categoryid=" . $records -> mccid .
                        "\" target=\"_blank\">" . get_stringpath($records->mccpath) . "</a><!--(" . $records->mccpath . ")-->" );
                }else if($category == "Sum") {
                    array_push($tablearray, $total);
                }else if($category == "Sum without files and folders"){
                    array_push($tablearray, $totalnfnd);
                }else{
                    $total += $records -> $category;
                    if($category != "folder" and $category != "resource"){
                        $totalnfnd += $records -> $category;
                    }
                    array_push($tablearray, $records -> $category);
                }
            }
            $data1[] = $tablearray;
        }

        $table -> data = array_merge($data1, $data2);

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

function merge_block_and_mod($mod, $blocks, $courseid){
    $mod = $mod[$courseid];

    foreach ($blocks as $block){
        $blockname = "block_" . $block->blockname;
        $mod -> $blockname = $block->count;
    }

    return $mod;
}

function get_array_for_categories($max_depth, $columns){
    global $DB;

    // performing double shift so ID and course don't count.
    array_shift($columns);
    array_shift($columns);
    // same goes for the end with those "sums" trailing there
    array_pop($columns);
    array_pop($columns);

    $categorys = $DB -> get_records_sql("
    SELECT id, name, path, depth FROM {course_categories};");
    $a = new stdClass();
    foreach ($columns as $column){
        $a -> $column = "0";
    }

    foreach($categorys as $category){
        $category -> subcats = "";
        if($category -> depth > $max_depth){
            $parentpos = explode("/", $category -> path);
            $parent = $parentpos[$max_depth];

            $categorys[$parent] -> subcats .= $category->id . ";";
            unset($categorys[$category->id]);
        }
    }

    foreach($categorys as $category){
        $category -> mccid = $category -> id;
        $category -> mccpath = $category -> path;
        unset($category -> depth);
        $category = array_merge((array) $category, (array) $a);
        $categorys[$category["id"]] = (object) $category;
    }

    return $categorys;
}

echo $OUTPUT->footer();
// The end.
