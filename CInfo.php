<?php
/**
 * Created by PhpStorm.
 * User: Anton
 * Date: 19.01.2018
 * Time: 0:01
 */


abstract class CInfo
{
    public $filename;
    public $filepath;

    abstract public function getType();
}