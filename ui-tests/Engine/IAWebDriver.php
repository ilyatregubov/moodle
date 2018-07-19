<?php

use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\DriverCommand;
use Facebook\WebDriver\Remote\HttpCommandExecutor;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\WebDriverCommand;
use Facebook\WebDriver\WebDriverDimension;

/**
 * Class IAWebDriver extends Facebook RemoteWebDriver and encapsulates the connection specifics.
 * Custom extension to web driver functionality will also go here.
 */
class IAWebDriver extends RemoteWebDriver
{
    private $selenium_server_url = 'http://localhost:4444/wd/hub';
    private $http_proxy = null;
    private $http_proxy_port = null;

    public function __construct()
    {
        $desired_capabilities = DesiredCapabilities::chrome();
//        $desired_capabilities = DesiredCapabilities::firefox();

        $executor = new HttpCommandExecutor(
            $this->selenium_server_url,
            $this->http_proxy,
            $this->http_proxy_port
        );

        $command = new WebDriverCommand(
            null,
            DriverCommand::NEW_SESSION,
            ['desiredCapabilities' => $desired_capabilities->toArray()]
        );

        $executor->setConnectionTimeout(60000);
        $executor->setRequestTimeout(60000);

        $response = $executor->execute($command);
        $returned_capabilities = new DesiredCapabilities($response->getValue());

        parent::__construct($executor, $response->getSessionID(), $returned_capabilities);

        $this->manage()->window()->setSize(new WebDriverDimension(2200, 1200));
    }
}
