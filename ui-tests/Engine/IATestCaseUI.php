<?php

use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\WebDriverBy;

require_once('/var/www/moodle/lib/phpunit/tests/advanced_test.php');
require_once('/var/www/moodle/ui-tests/Engine/IAWebDriver.php');
require_once('/var/www/moodle/ui-tests/Engine/TestData.php');

/**
 * Class IATestCaseUI should be used as a base for all automated web ui tests. It provides a Selenium web
 * driver and database connections.
 */
abstract class IATestCaseUI extends core_phpunit_advanced_testcase
{
    /* @var TestData $test_data */
    private $test_data;

    /* @var IAWebDriver $wd */
    protected static $wd;

    /**
     * setUpBeforeClass() is run once before test case
     */
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        /* only create web driver once per test case, this saves time and allows to preserve
           browser state between tests */
        try {
            self::$wd = new IAWebDriver();
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            self::fail("Failed to create Selenium web driver with the following error: $error_message.");
        }
    }

    /**
     * tearDownAfterClass() is run once after test case
     */
    public static function tearDownAfterClass()
    {
        if (self::$wd) {
            self::$wd->quit();
        }

        parent::tearDownAfterClass();
    }

    /**
     * setUp() is run before each test
     */
    protected function setUp()
    {
        global $DB;

        parent::setUp();

        if (!$this->test_data) {
            $this->test_data = new TestData($DB);
            $this->test_data->create();
        }
    }

    /**
     * tearDown() is run after each test
     */
    protected function tearDown()
    {
        parent::tearDown();
    }

    protected function switchToFrame($username = null)
    {
        if ($username) {
            self::$oldinterface = \Database\DBUtils::GetSqlFields($this->database,
                "select opt_out_date from receiverinfo where userid = '$username'");
        }
        if (self::$oldinterface[0]) {
            self::$wd->switchTo()->defaultContent();
            $my_frame = self::$wd->findElement(WebDriverBy::id("fsmainwindow"));
        } else {
            $my_frame = self::$wd->findElement(WebDriverBy::className("iframePage"));
        }
        self::$wd->switchTo()->frame($my_frame);
    }

    protected function switchTopBar()
    {
        self::$wd->switchTo()->defaultContent();
        if (self::$oldinterface[0]) {
            $my_frame = self::$wd->findElement(WebDriverBy::id("fstopbar"));
            self::$wd->switchTo()->frame($my_frame);
        }
    }
}
