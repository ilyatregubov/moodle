<?php

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

class Login
{
    /* @var IAWebDriver $wd */
    private $wd;

    /* @var string $username */

    public $username;

    /**
     * @param IAWebDriver $wd
     */
    public function __construct(IAWebDriver $wd)
    {
        $this->wd = $wd;
    }

    public function asAdmin()
    {
        $this->perform("UI-TEST-ADMIN", "W1wkEToLQFVTE526P");
    }

    public function asUser()
    {
        $this->perform();
    }

    public function perform($username = null, $password = null)
    {
        $url = "http://localhost/moodle/login/index.php";
        $this->wd->get($url);

        if (!$username && !$password) {
            $username = 'Testuser1';
            $password = 'Testuser1@';
        }

        $this->username = $username;

        $this->wd->findElement(WebDriverBy::linkText("Log in"))->click();

        $this->wd->findElement(WebDriverBy::id("username"))->sendKeys($username);
        $this->wd->findElement(WebDriverBy::id("password"))->sendKeys($password);
        $this->wd->findElement(WebDriverBy::id("loginbtn"))->click();

        $this->wd->executeScript('window.scrollTo(0,document.body.scrollHeight);');

        $my_frame = $this->wd->findElements(WebDriverBy::id("fstopbar"));
        if ($my_frame) {
            $this->wd->switchTo()->frame($my_frame[0]);
        }

        $this->wd->wait(10)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::linkText("Log out")
            )
        );
    }
}
