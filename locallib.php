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
 * Reports implementation
 *
 * @package    report_elearning
 * @copyright  2015 BFH-TI, Luca Bösch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

/**
 * Settings form for the elearning report.
 *
 * @copyright  2015 BFH-TI, Luca Bösch
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * works for now
 */
class report_elearning_form extends moodleform {

    /**
     * Define the form.
     */
    protected function definition() {
        global $DB;
        $mform = $this->_form;
        $all = array();

        // All needs to be on very first place.
        $allcount = '';
        $visiblecount = get_coursecategorycoursecount(get_coursecategorypath(0), true);
        $invisiblecount = get_coursecategorycoursecount(get_coursecategorypath(0), false);

        $allcount .= " (" . $visiblecount . " " . get_string('shownplural', 'report_elearning') . ", " . $invisiblecount .
                    " " . get_string('hiddenplural', 'report_elearning') . ", " . get_string('total', 'report_elearning') .
                    " " . ($invisiblecount + $visiblecount) . ")";
        $all[0] = get_string('all', 'report_elearning') . $allcount;

        $coursecat = $DB->get_records("course_categories", array(), "sortorder ASC", "id,name,path");
        foreach ($coursecat as $id => $cat) {
            $components = preg_split('/\//', $cat->path);
            array_shift($components);
            $fullname = '';
            foreach ($components as $component) {
                $fullname .= ' / ' . format_string($coursecat[$component]->name);
            }
            $visiblecount = get_coursecategorycoursecount(get_coursecategorypath($cat->id), true);
            $invisiblecount = get_coursecategorycoursecount(get_coursecategorypath($cat->id), false);

            $fullname .= " (" . $visiblecount . " " . get_string('shownplural', 'report_elearning') . ", " . $invisiblecount .
                    " " . get_string('hiddenplural', 'report_elearning') . ", " . get_string('total', 'report_elearning') .
                    " " . ($invisiblecount + $visiblecount) . ")";
            $all[$id] = substr($fullname, 3);
        }

        if (count($all) == 2) {
            // I.e., the case for (all) plus only 1 entry, making (all) redundant ...
            unset($all[0]);
        }

        $mform->addElement('select', 'elearningcategory', get_string('category', 'report_elearning'), $all);

        $mform->addElement('checkbox', 'elearningvisibility', get_string('onlyshown', 'report_elearning'),
                $mform->getSubmitValue('elearningvisibility'));
        $mform->addElement('checkbox', 'nonews', get_string('nonewsforum', 'report_elearning'),
                $mform->getSubmitValue('nonews'));

        $mform->addElement('submit', 'submitbutton', get_string('choose', 'report_elearning'));
    }

}


// get all blocks in that course
// possible problem the fact that we now load all blocknames/coursenames dynamically adds O(n) complexity where n is
// the number of courses. That will blow up on deployment
// TODO fix that.
function blocks_DB($courseid){
    global $DB;
    $coursecontext= context_course::instance($courseid);
    $coursecontextid = $coursecontext -> id;
    /*$sql = "SELECT blockinstanceid, contextid, pagetype, visible FROM {block_positions}
            WHERE contextid = $coursecontextid;";*/
    // I don't have enough ḱnowledge about contexts yet to evaluate wether I'll need to look for subcontexts inside
    // of a course
    // Todo find out
    $sql = "SELECT DISTINCT blockname, count(blockname) AS count
            FROM {block_instances}
            WHERE parentcontextid = $coursecontextid
            GROUP BY blockname";
    $blocks = $DB -> get_records_sql($sql);
    return $blocks;
}

/**
 * This function limits the length of a string, cutting in the middle
 *
 * @see http://www.php.net/manual/en/function.substr.php#84775
 *
 * @param string $value Input string
 * @param int $length Admissible length of string
 * @return string String with reduced length
 */
function limitstringlength($value, $length = MAX_STRING_LEN) {
    if (strlen($value) >= $length) {
        $lengthmax = ($length / 2) - 3;
        $start = strlen($value) - $lengthmax;
        $limited = substr($value, 0, $lengthmax);
        $limited .= " ... ";
        $limited .= substr($value, $start, $lengthmax);
    } else {
        $limited = $value;
    }
    // Take care to badly-escaped strings ...
    return htmlentities($limited, ENT_QUOTES, 'UTF-8');
}

/**
 * Returns the amount of courses in a certain category and its subcategories.
 *
 * @param string $path The category path (e.g. /5/6).
 * @param boolean $onlyvisible Whether only visible courses should count.
 * @uses array $DB: database object
 * @return int $sql The report table creation SQL.
 * @throws dml_exception
 */
function get_coursecategorycoursecount($path, $onlyvisible=false) {
    global $DB;
    $sql = "  SELECT c.id, cc.path
                FROM {course} c
                JOIN {course_categories} cc
                  ON cc.id = c.category
               WHERE (cc.path LIKE CONCAT( '$path/%' )
                  OR cc.path LIKE CONCAT( '$path' ))";
    // Omit hidden courses and categories.
    if ($onlyvisible == true) {
        $sql .= "AND ((c.visible != 0) AND (cc.visible != 0))";
    } else {
        $sql .= "AND ((c.visible = 0) OR (cc.visible = 0))";
    }
    return(count($DB->get_records_sql($sql)));
}

function get_all_mod_names(){
    $pluginman = core_plugin_manager::instance();
    $returnarray = array();
    foreach (array("mod") as $type) {
        $pluginarray = $pluginman->get_plugins_of_type($type);
        foreach ($pluginarray as $pluigin) {
            array_push($returnarray, $pluigin->name);
        }
    }

    return $returnarray;
}

/**
 * Returns the array of an e-learning report table course row.
 *
 * @param int $courseid The course id.
 * @param boolean $onlyvisible Whether only visible courses should count.
 * @param boolean $nonews Whether news should be excluded from count.
 * @uses array $CFG: system configuration
 * @uses array $DB: database object
 * @return string sql query
 */
function get_coursetablecontent($courseid, $onlyvisible=false, $nonews=false){
    $sql = "SELECT mc.id, mc.fullname,";
    $pluginarray = get_all_mod_names();
    foreach ($pluginarray as $plugin){
        $sql .= "(
                      SELECT COUNT( * )
                        FROM {{$plugin}} r
                        JOIN {course} c
                          ON c.id = r.course
                        JOIN {course_categories} cc
                          ON cc.id = c.category
                       WHERE c.id = mc.id";

        if ($onlyvisible == true) {
            $sql .= "        AND ((c.visible != 0) AND (cc.visible != 0))";
        }
        if ($nonews == true and $plugin == "forum") {
            $sql .= "       AND f.type != 'news'";
        }
        $sql .= "     ) AS $plugin,";
    }
    // kill that trailing comma
    $sql = mb_substr($sql, 0, -1);

    $sql .= " FROM {course} mc
              WHERE mc.id = ?
              ORDER BY mc.sortorder";
    return $sql;
}

/**
 * Returns a formulated (fullname / fullname) category / sub-category path.
 *
 * @param string $intpath A path with the ids and slashes (e.g. /2/8/10).
 * @return string $stringpath A formulated path.
 */
function get_stringpath($intpath) {
    global $DB;
    $components = preg_split('/\//', $intpath);
    array_shift($components);
    $fullname = '';
    foreach ($components as $component) {
        $fullname .= ' / ' . format_string($DB->get_field('course_categories', 'name', array('id' => $component)));
    }
    return substr($fullname, 3);
}

/**
 * Return a instance id (course category) when you know the context.
 * @param int $id A context id.
 * @return int The according context id.
 */
function get_instancecontext($id) {
    global $DB;
    if ($id == 0) {
        return 0;
    } else {
        $instances = ($DB->get_records_sql("SELECT id"
                . "                           FROM {context}"
                . "                          WHERE instanceid = " . $id
                . "                            AND contextlevel = 40"));
        foreach ($instances as $instance) {
            $returnvalue = $instance->id;
        }
        return $returnvalue;
    }
}

/**
 * Return a course category path with a given course category id.
 * @param int $id A course category id.
 * @return string The according course category path.
 */
function get_coursecategorypath($id) {
    global $DB;
    if ($id == 0) {
        return "";
    } else {
        $categorypath = $DB->get_field('course_categories', 'path', array('id' => $id));
        return $categorypath;
    }
}
function get_data($elearningvisibility, $nonews, $a){
    global $DB, $CFG;
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
    return array($data1, $data2);
}

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

function merge_block_and_mod($mod, $blocks, $courseid){
    $mod = $mod[$courseid];

    foreach ($blocks as $block){
        $blockname = "block_" . $block->blockname;
        $mod -> $blockname = $block->count;
    }

    return $mod;
}
