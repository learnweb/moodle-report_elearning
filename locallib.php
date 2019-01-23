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
 * This function returns an array with all plugin names of a given plugin type currently installed
 * @param $types array plugin types
 * @return array of plugin names
 */
function get_all_plugin_names(array $types){
    $pluginman = core_plugin_manager::instance();
    $returnarray = array();
    foreach ($types as $type) {
        // get all plugins of a given type
        $pluginarray = $pluginman->get_plugins_of_type($type);
        // plugins will be returned as objects so get the name of each and push it into a new array
        foreach ($pluginarray as $pluigin) {
            array_push($returnarray, $pluigin->name);
        }
    }
    return $returnarray;
}

/**
 * @return array of mappings from a contectid to their course id
 * @throws dml_exception
 */
/// I honestly dislike this function since it takes up alot of memory.
/// Moodle has a function to convert a course id to a context id, but I havent found one for the other way around yet
/// since this caches all course contexts there is a lot of memory lost.

function context_id_to_course_id_table(){
    global $DB;
    //get ALL course ids.
    $courseid = $DB->get_records_sql("SELECT id FROM {course} GROUP BY id");
    $table = array();
    foreach($courseid as $id){
        //now get the contextid of this courseid and map it to the courseid
        $table[context_course::instance($id->id)->id] = $id->id;
    }
    return $table;
}

/**
 * @return array category mapped to an array that lists the usage of all "blocks" in that course
 * @throws dml_exception
 */

function get_block_data(){
    global $DB;
    /// In moodle the first parameter needs to be unique.
    /// Since all block instances are in one database we can do 1 of 3 things:
    /// 1 we query for one block at a time,
    /// 2 we query for one plugin at a time,
    /// 3 we find a unique combination of keys since no key other than "id" is unique in this table.
    /// Since we have 2 sets, a unique identifier has to be the cartesian product of those sets.
    /// in our case we have the set of courses (parentcontextid) and the set of blocks (blockname).
    /// Therefore we will select the tuple of those as our primary key and group the count by that.
    $data = $DB->get_records_sql("SELECT (parentcontextid, blockname) AS tupel, count(blockname) FROM {block_instances}
                  GROUP BY tupel");
    /// The data we receive is already sufficent, however it is really badly structured.
    /// Therefore we want to refine our data to be made out of arrays (arrays outperform stdClasses 3 times in terms of speed)!
    $refineddata = array();
    /// Since we only got the coursecontext but need the courseid we'll fetch the table matching those.
    /// Das is übrigens ne bijektion.
    $tablecontent = context_id_to_course_id_table();
    /// In the end we want to have the data of each CATEGORY not COURSE. So far we only got data per course.
    /// So we'll fetch the table matching each course to their category.
    /// Das ist leider keine bijektion. Übrigens auch keine in- oder surjektion.
    $map = get_child_map();
    foreach ($data as $record){
        /// The tuples come as (contextid,blockname) so we strip the brackets and extract contextid and blockname.
        $record->tupel = explode(",", str_replace(")", "", str_replace("(", "", $record->tupel)));
        $contextid = $record->tupel[0];
        $blockname = $record->tupel[1];
        /// There are blocks that exist outside of a course. In example the Dashboard has alot of blocks that
        /// ... don't belong to any course. We don't want these non-course blocks.
        /// So we check if the contextid is in the table of contextids that refer to a courseid.
        if(array_key_exists((integer)$contextid, $tablecontent)){
            /// if it is indeed a course-context we'll get that courseid and from there retrive the categoryid.
            $categoryid = $map[$tablecontent[(integer)$contextid]];
        } else{
            /// Doesn't belong to a course. Therefore it's dismissed.
            continue;
        }
        /// if there hasn't been another course with this block yet, we'll need to initialize the array.
        if(!isset($refineddata[$blockname])){
            $refineddata[$blockname] = array();
        }
        /// If there hasn't been another course of that category with this block yet, we'll need to initially set the value.
        /// However if there already was we don't want to overwrite the count but rather increase it.
        if(!isset($refineddata[$blockname][$categoryid])) {
            $refineddata[$blockname][$categoryid] = $record->count;
        }else{
            $refineddata[$blockname][$categoryid] = $record->count;
        }
    }

    return $refineddata;
}

/**
 * @return array category mapped to an array that lists the usage of all "mods" in that course
 * @throws dml_exception
 */

/// Alot of the functionality is the same as "get_block_data"
/// ...sometimes it's worth taking a look over there, if something is unclear.

function get_plugin_data(){
    global $DB;
    /// In the end we want to have the data of each CATEGORY not COURSE. So far we only got data per course.
    /// So we'll fetch the table matching each course to their category.
    $map = get_child_map();
    /// Unlike blocks that are all in one database, every plugin from the "mod" directory maintains its own table.
    /// Those tables are named sqlprefix pluginname, so we'll need all the pluginnames to find them.
    $plugins = get_all_plugin_names(array("mod"));
    foreach ($plugins as $plugin) {
        /// Some plugins are weird and create content for course 0.
        /// There is no course 0. Therefore we'll use a WHERE clause.
        $data[$plugin] = $DB->get_records_sql("SELECT course, count(course) FROM {{$plugin}} WHERE course <> 0 GROUP BY course ");
    }
    /// Again we already fetched sufficient data, but again wanna refine it.
    $refineddata = array();
    foreach($data as $plugin => $plugindata){
        /// Fetch all datasets for a plugin.
        foreach ($plugindata as $coursedata){
            /// Now operate on one set of data at a time.
            /// First we want to retrieve the categoryid of the provided course.
            $catid = $map[$coursedata->course];
            /// if this is the first time we're operating on this plugin we need to initialize an array for its data.
            if(!isset($refineddata[$plugin])){
                $refineddata[$plugin] = array();
            }
            /// If there hasn't been a course of this category with this plugin yet, we'll want to set the initial value.
            /// If ther has however we don't want to overwrite the previous value but rather add to it.
            if(!isset($refineddata[$plugin][$catid])){
                /// cast to (int) for uniformity. $coursedata->count is a String.
                $refineddata[$plugin][$catid] = (int)$coursedata->count;
            }else{
                $refineddata[$plugin][$catid] += $coursedata->count;
            }
        }
    }
    $data = null;
    return $refineddata;
}


/**
 * @param $elearningvisibility bool wether to count invisible mods or not
 * @param $nonews bool shall news forum be counted?
 * @param $a stdClass std class that mainly provides the category id in case of a selection
 * @return array An array, that maches each categoryid to an array.
 * This array holds pluginnames as key and matches them to their use by the category.
 * @throws coding_exception
 * @throws dml_exception
 */
function get_data($elearningvisibility, $nonews, $a){
    /// I mean this used to be REALLY long.
    $plugin_data = get_plugin_data();
    $block_data = get_block_data();
    return array_merge($plugin_data, $block_data);
}

/**
 * @return array Maps each courseid to its categoryid
 * @throws dml_exception
 */
function get_child_map(){
    global $DB;
    $map = array();
    /// Get ALL courses
    $courses = $DB -> get_records_sql("SELECT id, category FROM {course}");
    foreach ($courses as $course){
        /// Map the courseid to the categoryid
        $map[(int) $course->id] = $course->category;
    }
    return $map;
}

function get_array_for_categories(int $max_depth){
    global $DB;
    /// get all those categorys.
    $categorys = $DB -> get_records_sql("SELECT id, name, path, depth FROM {course_categories};");

    /// Currently this branch isn't used since the max_depth is -1.
    /// This branch will delete all categorys whos dept is above max_depth
    /// It will then find the first category above the original category that is at the correct depth.
    /// Useage of this branch is discouraged if you decide to do so anyway, you'll need to add the logic
    /// ... for counting the values of the subcategories since that is currently NOT supported by endpoint or index.
    if($max_depth > 0) {
        foreach ($categorys as $category) {
            $category->subcats = "";
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
        /// We want to add a path that consists of the categorys names instead of of their ids.
        /// So first we'll split them up so we have all the ids.
        $path = explode("/", $category -> path);
        /// The path is preceeded by a / so $path[] is currently "" we don't want nor need that so kill it.
        array_shift($path);
        /// initialize
        $category -> readablepath = "";
        foreach ($path as $instance){
            /// For each categoryid we'll now fetch that categorys name and append it to the "readablepath" that way
            /// ...we have the decoded path in the end.
            $category -> readablepath .= "/" . $categorys[$instance] -> name;
        }
        /// we'll never need the depth again so goodbye.
        unset($category -> depth);
        /// now just rematch the category to the $categorys array. I really wonder wether a pointer would've been better.
        $categorys[$category->id] = $category;
    }
    return $categorys;
}


