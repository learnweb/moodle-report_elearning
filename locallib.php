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
// the number of courses. That might blow up on deployment
// TODO fix that.
// tried to fix... while you can perfectly make the needed sql statement on pysql and all logic says this has to be possible
// moodle will overwrite your results for a course with the last it finds, leading to you discovering exactly one
// blocktype per course id...
/**Gets all blocks in a given course
 * @param $courseid int the id of the course
 * @return array count of blocktype in that course
 * @throws dml_exception
 */
function blocks_DB($courseid){
    global $DB;
    if(!is_array($courseid)){
        $coursecontext= context_course::instance($courseid);
        $coursecontextid = $coursecontext -> id;
        $sql = "SELECT DISTINCT blockname, count(blockname) AS count
            FROM {block_instances}
            WHERE parentcontextid = $coursecontextid
            GROUP BY blockname";
    }else{
        if(sizeof($courseid)<1){
            return array();
        }
        $coursecontext= context_course::instance(array_shift($courseid));
        $coursecontextid = $coursecontext -> id;
        $sql = "SELECT DISTINCT blockname, count(blockname) AS count
            FROM {block_instances}";
        $sql .= "\n WHERE parentcontextid = " . $coursecontextid;
        foreach ($courseid as $course){
            $coursecontext= context_course::instance($course);
            $coursecontextid = $coursecontext -> id;
            $sql .= " OR parentcontextid = " . $coursecontextid;
        }
        $sql .= "\n GROUP BY blockname";
    }
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

function get_tablesql($category, $onlyvisible=false, $nonews = false, $recursive = false) {
    $pluginarray = get_all_mod_names();
    if($category === 0){
        $sql = "SELECT DISTINCT '' AS mccid, '' AS CATEGORY, '' AS mccpath,";
    }else{
        $categorypath = get_coursecategorypath($category);
        $sql = "SELECT mcc.id AS mccid, mcc.name AS Category, mcc.path AS mccpath,";
    }

    foreach($pluginarray as $plugin){
        if(strpos($plugin, " ")!==false){
            continue;
        }
        $sql .= "(
                    SELECT COUNT(*)
                    FROM {{$plugin}} p
                    JOIN {course} c
                    ON c.id = p.course
                    JOIN {course_categories} cc
                    ON cc.id = c.category";

        //if a category has been given we need to filter for it
        if($category !== 0){
            $sql .= " WHERE (";
            if($recursive) {
                $sql .= "cc.path LIKE CONCAT( '$categorypath/%' ) OR ";
            }
            $sql .= "cc.path LIKE CONCAT( '$categorypath' ))";
        }

        // if we chose to only list visible courses, we don*t wan't invisible ones
        if($onlyvisible){
            $sql .= "   AND ((c.visible != 0) AND (cc.visible != 0))";
        }

        if ($nonews == true and $plugin == "forum") {
            $sql .= "       AND f.type != 'news'";
        }

        // but we still need to select them by a name so...
        $sql .= "   ) AS {$plugin},";
    }
    // kill that trailing comma
    $sql = mb_substr($sql, 0, -1);

    // now let's add the FROM clauses

    if($category === 0){
        $sql .= "FROM {course_categories} mcc";
    }else{
        $sql .= " FROM {course_categories} mcc
                 WHERE mcc.id = " . $category .
            " ORDER BY mcc.sortorder;";
    }
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

function get_all_courses($cats){
    foreach ($cats as $cat){
        $cat -> childs = array();
    }
    global $DB;

    $c = $DB -> get_records_sql("SELECT id, category FROM {course} WHERE NOT category = 0");
    foreach($c as $course){
        array_push($cats[$course->category]->childs, $course->id);
    }

    return $cats;

}

/**
 * @param $elearningvisibility bool wether to count invisible mods or not
 * @param $nonews bool shall news forum be counted?
 * @param $a stdClass std class that mainly provides the category id in case of a selection
 * @return array Data to display, ready to be used by a html table
 * @throws coding_exception
 * @throws dml_exception
 */
function get_data($elearningvisibility, $nonews, $a){
    global $DB;
    // we want to get all categories so set max depth to smth negative
    $rec = get_array_for_categories(-1, array());
    $rec = get_all_courses($rec);
    foreach ($rec as $key => $category) {
        $catid = $category->id;
        $returnobject = $DB->get_records_sql(get_tablesql($catid, $elearningvisibility, $nonews));
        $block_data = blocks_DB($category->childs);
        $tablecontent = merge_block_and_mod($returnobject, $block_data, $catid);
        $category = (object)array_merge((array)$category, (array)$tablecontent); //black magic novice
        $rec[$key] = $category;
    }
    return $rec;
}

const types = array("mod", "block");
function getHeaders($nonrecursive = false, $humanreadable = false){
    $pluginman = core_plugin_manager::instance();

    if($humanreadable){
        $returnarray = array("ID");
    }else {
        $returnarray = array("id");
    }

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

    if($max_depth > 0) {
        foreach ($categorys as $category) {
            $category->subcats = "";
            //never
            if ($category->depth > $max_depth) {
                //add the subcat to the parentcat
                $parentpos = explode("/", $category->path);
                $parent = $parentpos[$max_depth];

                $categorys[$parent]->subcats .= $category->id . ";";
                unset($categorys[$category->id]);
            }
        }
    }

    foreach($categorys as $category){
        //intialize categories with a 0 on every count
        $category -> mccid = $category -> id;
        $category -> mccpath = $category -> path;
        $path = explode("/", $category -> path);
        array_shift($path);
        $category -> readablepath = "";
        foreach ($path as $instance){
            $category -> readablepath .= "/" . $categorys[$instance] -> name;
        }
        unset($category -> depth);
        $category = array_merge((array)$category, (array)$a);
        $categorys[$category["id"]] = (object) $category;
    }
    return $categorys;
}

//internal function therefore not documented
//I highly discourage usage outside of this project
//merges the block and mod list of a course
function merge_block_and_mod($mod, $blocks, $courseid){
    $mod = $mod[$courseid];

    foreach ($blocks as $block){
        $blockname = "block_" . $block->blockname;
        $mod -> $blockname = $block->count;
    }

    return $mod;
}
