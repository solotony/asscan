<?php
/**
 * Created by PhpStorm.
 * User: Anton
 * Date: 18.01.2018
 * Time: 23:56
 */

require_once('CInfo.php');

class CDirInfo extends CInfo
{
    public $records = array();

    public function getType()
    {
        return 1;
    }
}