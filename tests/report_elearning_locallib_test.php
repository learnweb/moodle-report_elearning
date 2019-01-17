<?php
/**
 * Created by PhpStorm.
 * User: robintschudi
 * Date: 14.01.19
 * Time: 12:38
 */

//defined('MOODLE_INTERNAL') || die();
global $CFG;
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/report/elearning/locallib.php'); // Include the code to test

$block_pre = "elearning_report_test";
class report_elearning_locallib_test extends \PHPUnit\Framework\TestCase
{


    public function setUp()
    {
        global $DB, $block_pre;

        $course1data = new stdClass();
        $course1data->shortname = "test1";
        $course1data->fullname="Test1";
        $course1data->category_path="Miscellaneous";
        $course1data->visible = 1;
        $course1data->startdate = "01.12.2018";
        $course1data->enddate= "02.12.2018";
        ///create_course($course1data);



        $standardObject= new stdClass();
        $standardObject -> showinsubcontexts = 0;
        $standardObject -> requiredbytheme = 0;
        $standardObject -> pagetypepattern = "*";
        $standardObject -> defaultregion = "side-pre";
        $standardObject -> defaultweight = 0;
        $standardObject -> timecreated = 1544457773;
        $standardObject -> timemodified = 1544457773;
        $block_1 = new stdClass();
        $block_2 = new stdClass();
        $block_3 = new stdClass();
        $block_4 = new stdClass();
        $block_5 = new stdClass();
        $coursecontext1 = context_course::instance(1)->id;
        $coursecontext2 = context_course::instance(2)->id;

        $block_1->id = -1; $block_1->blockname="elearning_report_test" . "1"; $block_1->parentcontextid=$coursecontext1;
        $block_2->id = -2; $block_2->blockname="elearning_report_test" . "2"; $block_2->parentcontextid=$coursecontext1;
        $block_3->id = -3; $block_3->blockname="elearning_report_test" . "1"; $block_3->parentcontextid=$coursecontext1;
        $block_4->id = -4; $block_4->blockname="elearning_report_test" . "3"; $block_4->parentcontextid=$coursecontext2;
        $block_5->id = -5; $block_5->blockname="elearning_report_test" . "1"; $block_5->parentcontextid=$coursecontext2;

        $blocks = array($block_1, $block_2, $block_3, $block_4, $block_5);

        foreach ($blocks as $block){
            $data = (object)array_merge((array)$block, (array)$standardObject);
            $DB ->insert_record_raw('block_instances', $data);
        }

    }

    public function tearDown()
    {
        global $DB, $block_pre;
        $DB->delete_records_select("block_instances","blockname = '{$block_pre}1' or blockname = '{$block_pre}2' or blockname = '{$block_pre}3'");
    }

    //function block_DB tests

    function same_block_count($a1, $a2){
        global $block_pre;
        foreach ($a1 as $block){
            $blockname = $block->blockname;
            if(strpos($blockname, $block_pre) == -1){
                continue;
            }
            if($a2->$blockname != $block->count){
                return false;
            }
        }
        return true;
    }

    function test_blocks_db_single_course(){
        global $block_pre;
        $blocks = blocks_DB("1");
        $block_count = new stdClass();
        $block_name_1 = $block_pre."1"; $block_name_2 = $block_pre."2";
        $block_count-> $block_name_1 = 2;
        $block_count-> $block_name_2 = 1;
        $this -> assertTrue($this->same_block_count($blocks, $block_count));
    }

    function test_blocks_db_multiple_course(){
        global $block_pre;
        $blocks = blocks_DB(array(1,2));
        $block_count = new stdClass();
        $block_name_1 = $block_pre."1"; $block_name_2 = $block_pre."2"; $block_name_3 = $block_pre."3";
        $block_count-> $block_name_1 = 3;
        $block_count-> $block_name_2 = 1;
        $block_count-> $block_name_3 = 1;
        $this -> assertTrue($this->same_block_count($blocks, $block_count));
    }

    function test_get_all_courses(){
        global $DB;
        $misc_courses = get_all_courses(get_array_for_categories(-1, array()));
        $misc_courses_correct = $DB -> get_records_sql("SELECT id FROM {course} WHERE category = 1");
        $childs = $misc_courses[1]->childs;
        $this->assertEquals(sizeof($misc_courses_correct), sizeof($childs));
        $this->assertContains(2, array('2'));
        foreach($misc_courses_correct as $course){
            $this->assertContains($course->id, $childs);
        }

    }

    function test_get_array_for_categories(){
        global $DB;
        $categories_test = get_array_for_categories(1, array());
        $categories_correct = $DB->get_records_sql("SELECT id FROM {course_categories} WHERE depth <= 1");
        while(sizeof($categories_test) != 0 and sizeof($categories_correct) != 0){
            $this->assertEquals(array_shift($categories_correct)->id, array_shift($categories_test)->id);
        }
        $this->assertEquals( 0,sizeof($categories_test));
        $this->assertEquals( 0,sizeof($categories_correct));

        $categories_test = get_array_for_categories(2, array());
        $categories_correct = $DB->get_records_sql("SELECT id FROM {course_categories} WHERE depth <= 2");
        while(sizeof($categories_test) != 0 and sizeof($categories_correct) != 0){
            $this->assertEquals(array_shift($categories_correct)->id, array_shift($categories_test)->id);
        }
        $this->assertEquals( 0,sizeof($categories_test));
        $this->assertEquals( 0,sizeof($categories_correct));

        $categories_test = get_array_for_categories(0, array());
        $categories_correct = $DB->get_records_sql("SELECT id FROM {course_categories}");
        $this->assertEquals(sizeof($categories_correct), sizeof($categories_test));
    }

}
