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

$redis = new Redis();
try {
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
    foreach ($hash as $v) {
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
    } while ($i < $len);
};

$pregTools = function($data, $pre, $aft, $strReplace = false, $forceDelHtml = false) use($getRegexp, $initRegexp) {
    $addmyslashes = function($str) {
        $str = addslashes($str);
        $str = addcslashes($str, '/');
        return $str;
    };
    $ret = null;
    preg_match("/" . $addmyslashes($pre) . $getRegexp() . "*?" . $addmyslashes($aft) . "/", $data, $ret);
    if ($strReplace) {
        $ret = str_replace($pre, "", $ret[0]);
        $ret = str_replace($aft, "", $ret);
    } else {
        $ret = strip_tags($ret[0]);
    }
    return $forceDelHtml ? strip_tags($ret) : $ret;
};

$analyzeTool = function($data) use($getRegexp, $initRegexp, $pregTools) {
    /*    $i = 0;
      do {
      $ord = mb_substr($description[0], $i, 1);
      $nu = ord($ord);
      var_dump("{$ord}  -  {$nu}");
      var_dump($i);
      $i++;
      } while($i < mb_strlen($description[0]));
     */
//    $initRegexp($data);
    $ret = [];
    // title
    $ret["title"] = $pregTools($data, "<h1 itemprop=\"name\">", "</h1>");
    // subtitle
    $ret["subtitle"] = $pregTools($data, "</h1>\r\n<h3>", "</h3>", true);
    // description
    $ret["description"] = $pregTools($data, "<span itemprop=\"description\">", "</span>");
    // publisher
    $ret["publisher"] = $pregTools($data, "itemprop=\"publisher\">", "</td></tr>", true, true);
    // author
    $ret["author"] = $pregTools($data, "<b itemprop=\"author\" style=\"display:none;\">", "</b>");
    // isbn
    $ret["isbn"] = $pregTools($data, "<b itemprop=\"isbn\">", "</b>");
    // datePublished
    $ret["datePublished"] = $pregTools($data, "<b itemprop=\"datePublished\">", "</b>");
    // numberOfPages
    $ret["numberOfPages"] = $pregTools($data, "<b itemprop=\"numberOfPages\">", "</b>");
    // inLanguage
    $ret["inLanguage"] = $pregTools($data, "<b itemprop=\"inLanguage\">", "</b>");
    // fileSize
    $ret["fileSize"] = $pregTools($data, "<tr><td>File size:</td><td><b>", "</b>", true);
    // bookFormat
    $ret["bookFormat"] = $pregTools($data, "<b itemprop=\"bookFormat\">", "</b>");
    // downloadUrl
    $ret["downloadUrl"] = $pregTools($data, "<tr><td>Download:</td><td><a href='", "'>", true);
    // buyUrl
    $ret["buyUrl"] = $pregTools($data, "<tr><td>Buy:</td><td><a href=\"", "\"", true);
    return $ret;
};

$postTool = function($data = "{}", $url = "http://localhost:8983/solr/ebooks/schema") {
    $ctx = [
        "http" => [
            "method" => "POST",
            "header" => "Content-type:application/json\r\n\n\n",
            "content" => $data,
            "timeout" => 5,
        ],
    ];
    $stream = stream_context_create($ctx);
    do {
        $ret = file_get_contents($url, false, $stream);
    } while (empty($ret));
    return $ret;
};

