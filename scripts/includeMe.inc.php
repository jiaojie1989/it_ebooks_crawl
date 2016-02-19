<?php
define("EBOOK_PAGE_URL", "http://it-ebooks.info/book/%s/"); // replace with integer
define("EBOOK_DETAIL_API", ""); // frequency limit (1000 per one day)
define("SINCE_FILE", "hits");
define("REG_CHAR", '[a-zA-Z0-9\/\?\\\ \,\'\"\.\`\*\-\(\)\=\+\[\]\:\;\<\>\$\!\&\~\@\#\^]');

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
        } while(!$data);
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

$analyzeTool = function($data) {
    $ret = [];
    //echo $data . "\n";
    // title
    $title = null;
    preg_match("/<h1 itemprop=\"name\">".REG_CHAR."*<\/h1>/", $data, $title);
    //var_dump($title);
    //echo "/<h1 itemprop=\"name\">".REG_CHAR."*<\/h1>/";
    $title = str_replace("<h1 itemprop=\"name\">", "", $title[0]);
    $title = str_replace("</h1>", "", $title);
    $ret["title"] = $title;
    // subtitle
    $subtitle = null;
    preg_match("/<\/h1>\r\n<h3>".REG_CHAR."*<\/h3>/", $data, $subtitle);
    $subtitle = str_replace("</h1>\r\n<h3>", "", $subtitle[0]);
    $subtitle = str_replace("</h3>", "", $subtitle);
    $ret["subtitle"] = $subtitle;
    return $ret;
};

$resetCache = function($num) use($redis) {
    $redis->select(1);
    return $redis->del(md5(sprintf(EBOOK_PAGE_URL, $num)));
};
