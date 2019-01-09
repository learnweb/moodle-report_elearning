<?php

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
 * External Web Service Template
 *
 * @package    localwstemplate
 * @copyright  2011 Moodle Pty Ltd (http://moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/report/elearning/locallib.php');

class report_elearning_external extends external_api {

    public static function prometheus_endpoint_parameters(){
        return new external_function_parameters(
            array('categoryid' => new external_value(PARAM_INT, 'The category by default it is 0', VALUE_DEFAULT, 0),
                  'visibility' => new external_value(PARAM_BOOL, "wether only invisible activities shall be counted", VALUE_DEFAULT, false),
                  'nonews'     => new external_value(PARAM_BOOL,"if set to true news forums won't be counted", VALUE_DEFAULT, false))
        );
    }

    public static function prometheus_endpoint($categoryid= 0, $visibility = false, $nonews = false){
        global $USER;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::prometheus_endpoint_parameters(),
            array('categoryid' => $categoryid,
                  'visibility' => $visibility,
                  'nonews'     => $nonews));

        //Context validation
        //OPTIONAL but in most web service it should present
        $context = get_context_instance(CONTEXT_USER, $USER->id);
        self::validate_context($context);

        //Capability checking
        //OPTIONAL but in most web service it should present
        if (!has_capability('moodle/user:viewdetails', $context)) {
            throw new moodle_exception('cannotviewprofile');
        }

        $config = new stdClass();
        // pass the categoryid to restrict results, nope doesn't do anything anymore :D
        $config -> category = $categoryid;
        $config -> context = null;

        //get data from locallib of the elearning project
        $b = get_data($visibility, $nonews, $config);

        //prometheus data endpoint format
        //format for summary: categorys_total{category="<category>"} <value>
        //format for single datapoint: course_<course>{plugin="<block/mod>"} value
        $plugins = getHeaders();
        //don't need ID and cat/course
        array_shift($plugins);
        array_shift($plugins);

        $return = "";
        $summary = "";

        //O(n * d) !! d= 72 atm so technically O(n) just watch out cause d is the number of blocks and mods
        //however since this is the purpose of the project...
        foreach($b as $cat){
            $name = str_replace(" ", "_" , $cat -> name);
            $tot = 0;
            foreach($plugins as $plugin){
                if(isset($cat->$plugin)) {
                    $num = $cat -> $plugin;
                    $tot += $num;
                }else {
                    $num = 0;
                }
                if(strpos($plugin,"block_") !== false){
                    $pluginname = $plugin;
                }else{
                    $pluginname = "mod_" . $plugin;
                }
                $return .= "category_" . $cat -> id . "{name=\"{$name}\" path=\"{$cat->path}\" ".
               "explicitpath=\"{$cat->readablepath}\" plugin=\"{$pluginname}\"} {$num}" . "\n";
            }
            $summary .= "Category_Overview{id=\"{$cat->id}\" name=\"{$name}\" path=\"{$cat->path}\" ".
                " explicitpath=\"{$cat->readablepath}\"} ". $tot ."\n";
        }

        return $return . $summary;


    }

    public static function prometheus_endpoint_returns(){
        return new external_value(PARAM_TEXT, 'The e-learning report in Prometheus compatible formatting.');
    }


}

