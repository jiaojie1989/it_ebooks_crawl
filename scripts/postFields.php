<?php

/*
 * Copyright (C) 2016 SINA Corporation
 *  
 *  
 * 
 * This script is firstly created at 2016-02-26.
 * 
 * To see more infomation,
 *    visit our official website http://finance.sina.com.cn/.
 */

error_reporting(E_ERROR);
require "includeMe.inc.php";

$field4Post = [
    "add-field" => [
        "name" => "",
        "type" => "text_general",
        "stored" => true,
    ],
];

$fieldsArr = [
    "title",
    "subtitle",
    "description",
    "publisher",
    "author",
    "isbn",
    "datePublished",
    "numberOfPages",
    "inLanguage",
    "fileSize",
    "bookFormat",
    "downloadUrl",
    "buyUrl",
];

foreach($fieldsArr as $v) {
    $field4Post["add-field"]["name"] = $v;
    var_dump($postTool(json_encode($field4Post)));
}