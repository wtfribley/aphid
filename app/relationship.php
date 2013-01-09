<?php defined("PRIVATE") or die("Permission Denied. Cannot Access Directly.");

class Relationship extends Query {

    /**
     *  The relationship type (i.e. one-to-many or many-to-many)
     */
    public $type;
}