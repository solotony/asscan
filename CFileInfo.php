<?php
/**
 * Created by PhpStorm.
 * User: Anton
 * Date: 19.01.2018
 * Time: 0:02
 */

require_once('CInfo.php');

class CFileInfo extends CInfo
{
    public $filesize;
    public $filetime;

    public function getType()
    {
        return 0;
    }
}