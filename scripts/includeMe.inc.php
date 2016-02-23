<?php

define("EBOOK_PAGE_URL", "http://it-ebooks.info/book/%s/"); // replace with integer
define("EBOOK_DETAIL_API", ""); // frequency limit (1000 per one day)
define("SINCE_FILE", "hits");
//define("REG_CHAR", '[a-zA-Z0-9\\‘\’\ \“\”\…\­\\‚\—\–\®\’\%\_\!\/\r\n\?\\\ \,\'\"\.\`\*\-\(\)\=\+\[\]\:\;\<\>\$\!\&\~\@\#\^]');
//define("REG_CHAR", '[\x00-\x7f]');

$conn = new mysqli("127.0.0.1:3306", "jiaojie", "jiaojie", "it_ebooks");
if ($conn->connect_error) {
    throw new Exception("Connect Error: {$conn->connect_error}");
}

$redis = new Redis(); try {
    $redis->connect("127.0.0.1", 6379);
} catch (RedisException $e) {
    throw new Exception($e->getMessage(), $e->getCode(), $e);
}

$getPageTool = function($url, $timeLimit = 10) use($redis) {
    //echo $url . "\n";
    $redis->select(1);
    $ctx = [
        "http" => [
            "timeout" => $timeLimit,
        ],
    ];
    if ($data = $redis->get(md5($url))) {
        
    } else {
        do {
            $data = file_get_contents($url, false, stream_context_create($ctx));
            usleep(100000);
        } while (!$data);
        $redis->setex(md5($url), 3600 * 24 * 64, $data);
    }
    return $data;
};

$detect404Tool = function($data) {
    if (strstr($data, "<img src=\"/images/404.png\" alt=\"Page Not Found\" />")) {
        return true;
    } else {
        return false;
    }
};

$resetCache = function($num) use($redis) {
    $redis->select(1);
    return $redis->del(md5(sprintf(EBOOK_PAGE_URL, $num)));
};

$setRegexp = function($char) use($redis) {
    $redis->select(1);
    return $redis->sAdd("RegExp", $char);
};

$getRegexp = function() use($redis) {
    $redis->select(1);
    $hash = $redis->sMembers("RegExp");
    $str = "[";
    foreach($hash as $v) {
        $ret = null;
        preg_match("/[a-zA-Z0-9\r\n\t]+/", $v, $ret);
        if (!empty($ret)) {
        //    var_dump("Hit Normal");
            $str .= "" . $v;
        } else {
        //    var_dump("No Hit Normal");
            $str .= "\\" . $v;
        }
    }
    $str .= "]";
    //echo $str . "\n";
    return $str;
};

$initRegexp = function($data) use($setRegexp) {
    $len = mb_strlen($data);
    $i = 0;
    do {
        $char = mb_substr($data, $i, 1);
        //var_dump($char);
        $setRegexp($char);
        $i++;
    } while($i < $len);
};

$analyzeTool = function($data) use($getRegexp, $initRegexp) {
    $initRegexp($data);
    $ret = [];
    //echo $data . "\n";
    // title
    $title = null;
    preg_match("/<h1 itemprop=\"name\">" . $getRegexp() . "*<\/h1>/", $data, $title);
    //var_dump($title);
    //echo "/<h1 itemprop=\"name\">".REG_CHAR."*<\/h1>/";
    $title = str_replace("<h1 itemprop=\"name\">", "", $title[0]);
    $title = str_replace("</h1>", "", $title);
    $ret["title"] = $title;
    // subtitle
    $subtitle = null;
    preg_match("/<\/h1>\r\n<h3>" . $getRegexp() . "*<\/h3>/", $data, $subtitle);
    $subtitle = str_replace("</h1>\r\n<h3>", "", $subtitle[0]);
    $subtitle = str_replace("</h3>", "", $subtitle);
    $ret["subtitle"] = $subtitle;
    // description
    $description = null;
    preg_match("/<span itemprop=\"description\">" . $getRegexp() . "*<\/span>/", $data, $description);
//    preg_match("/<span itemprop=\"description\">" . REG_CHAR . "*/", $data, $description);
//    echo $description[0] . "\n";
/*    $i = 0;
    do {
        $ord = mb_substr($description[0], $i, 1);
        $nu = ord($ord);
        var_dump("{$ord}  -  {$nu}");
        var_dump($i);
        $i++;
    } while($i < mb_strlen($description[0]));
*/
    $description = str_replace("<span itemprop=\"description\">", "", $description[0]);
    $description = str_replace("</span>", "", $description);
    $ret["description"] = $description;
    return $ret;
};

