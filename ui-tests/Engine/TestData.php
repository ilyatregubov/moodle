<?php

/**
 * Class TestData manages the test data. TODO: extract test data into stand-alone sql file.
 */
class TestData
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    public function create()
    {
        $user = $this->database->get_records('user', ['username' => 'testuser1']);

//        if (!$user) {
//            $sql = "insert into customers (custcode, name, address, cityandstate, zipcode,
//                    email, custactive, managedby) VALUES ('TST1', 'AUTOTEST1',
//                    '2000 Martin Place 26', 'Sydney NSW', 2000, 'test@test.com', 't', 'GA')";
//            pg_query($this->database, $sql);
//        }

    }
}
