<?php

defined('MOODLE_INTERNAL') || die();

use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverSelect;

require_once('/var/www/moodle/ui-tests/Engine/IATestCaseUI.php');
require_once('/var/www/moodle/ui-tests/Engine/Sequences/Login.php');

class ReportsTest extends IATestCaseUI
{
    /**
     * Test reports
     *
     */
    public function testDashboardPage() {
        $login = new Login(self::$wd);
        $login->asUser();

        $actual = self::$wd->findElement(WebDriverBy::cssSelector(".usertext.mr-1"))->getText();
        $expected = "Test Test1";
        $this->assertContains($expected, $actual);
    }

    /**
     * @depends testDashboardPage
     */
    public function testProfilePage() {

        self::$wd->findElement(WebDriverBy::linkText("Test Test1"))->click();
        self::$wd->findElement(WebDriverBy::cssSelector("[aria-label='Profile']"))->click();

//        $screenshot = "./chrome.png";
//        self::$wd->takeScreenshot($screenshot);

        self::$wd->wait(30)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::linkText("Edit profile")
            )
        );

        $actual = self::$wd->findElement(WebDriverBy::linkText("test+++test@catalyst-au.net"))->getText();
        $expected = "test+++test@catalyst-au.net";
        $this->assertEquals($expected, $actual);

    }

}