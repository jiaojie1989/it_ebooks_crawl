<?php
error_reporting(E_ERROR);
require "includeMe.inc.php";

$toString = function($origin, $target = 5, $char = " ") {
    $strlen = strlen($origin);
    $less = $target - $strlen;
    if ($less > 0) {
        for($i = 0; $i < $less; $i++) {
            $origin = strval($char) . strval($origin);
        }
    }
    return $origin;
};

for($num = 1; $num <= 7000; $num++) {
//$num = 3;
    $data = $getPageTool(sprintf(EBOOK_PAGE_URL, $num));
    $num = $toString($num);
    if (!$detect404Tool($data)) {
        $info = $analyzeTool($data);
        if (empty($info["title"]) || !isset($info["subtitle"])) {
            echo "[\033[31mErr \033[0m] [{$num}] contains no data\n";
            //var_dump($num);
            $resetCache(intval($num--));
        } else {
            echo "[\033[36mInfo\033[0m] [{$num}] [\033[32m{$info["title"]}\033[0m]\n";
            echo $info["buyUrl"] . "\n";
            echo $info["downloadUrl"] . "\n";
            if (empty($info["description"]) || empty($info["buyUrl"])) {
                $initRegexp($data);
                $resetCache(intval($num));
                echo "[\033[31mWarn\033[0m] [{$num}] no description\n";
                $num--;
            }
        }
    } else {
//        $resetCache(intval($num));
        echo "[\033[35mWarn\033[0m] [{$num}] 404 inside\n";
    }
}
