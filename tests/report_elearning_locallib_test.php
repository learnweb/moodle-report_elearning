<?php
/**
 * Created by PhpStorm.
 * User: robintschudi
 * Date: 14.01.19
 * Time: 12:38
 */

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../config.php');
//require_once($CFG->dirroot . '/report/elearning/locallib.php'); // Include the code to test

class report_elearning_locallib_test extends TestCase
{

    public function setUp()
    {
        global $DB;
        $sql = "INSERT".
        " (id, blockname, parentcontextid, showinsubcontexts, requiredbytheme, pagetypepattern, defaultregion, defaultweigt, timecreated, timemodified)".
            " INTO {block_instances} VALUES (?,?,?,?,?,?,?,?,?,?);";
        $irrelevant_data = array(0, 0, "*", "side-pre", 0, 1544457773, 1544457773);
        $blocks = array(
            array(-1, "Test1", -1),
            array(-2, "Test2", -1),
            array(-3, "Test1", -1),
            array(-4, "Test3", -2),
            array(-5, "Test1", -2)
        );

        foreach ($blocks as $block){
            $DB ->execute($sql, array_merge($block, $irrelevant_data));
        }

    }

    function test_blocks_db(){
        $blocks = blocks_DB(-1);
        $block_count = stdClass::class;
        $block_count-> Test1 = 2;
        $block_count-> Test2 = 1;
        foreach ($blocks as $block){
            $blockname = $block->blockname;
            assertEquals($block->count, $block_count -> $blockname);
        }
    }

    public function tearDown()
    {
        global $DB;
        $DB->execute("DELETE FROM {block_instances WHERE id=-1 OR id=-2 OR id=-3 OR id=-4 OR id=-5");
    }

}
