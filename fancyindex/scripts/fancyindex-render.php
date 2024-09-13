<?php

require_once 'config.php';
require_once 'util.php';

define('PAGE_KEYS', ['{% title %}', '{% css %}', '{% head %}', '{% body %}']);


function getAudoRedirect($url, $delay = 0) {
    $url = $url ? $url : "/";
    $delay = $delay ? $delay : "0";
    return '<meta http-equiv="refresh" content="'.$delay.'; url='.$url.'" />';
}

function renderPage($title, $head, $body, $templatefile = '_page.html'):string
{
    $tpl = file_get_contents(__DIR__ . '/'. $templatefile);
    if ($tpl) {
        $title = $title ? $title : "";
        $head = $head ? $head : "";
        $body = $body ? $body : "";
        return str_replace(PAGE_KEYS, [$title, CSS_STYLE, $head, $body], $tpl);
    } else {
        header('Content-Type:text/plain');
        return "$title \n $body\n\n" .
            "[WARNING] Not Found TemplateFile: $templatefile\n";
    }
}


$title = $_GET['title'] ?? null;
$body = $_GET['body'] ?? null;
$head = $_GET['head'] ?? null;
$msg = $_GET['msg'] ?? null;
$q = $_GET['q'] ?? null;
$location = $_GET['location'] ?? null;
// var_dump($_GET);

if ($q === "dashboard") {
    require_once(__DIR__ . '/pages.php');
    $res = p_createCompressedDB();
    echo renderPage($res['title'], $res['head'], $res['body']);
}
// build page for message
else if ($msg) {
    $title = $title ? urldecode($title) : "";
    $msg = urldecode($msg);
    $backUrl = $_GET['back'] ?? null;
    $cssdir = dirname(CSS_STYLE);
    if ($cssdir) {
        $csspath = joinPath('/' . $cssdir, 'msgbox.css');
        $head .= '<link href="' . $csspath . '" rel="stylesheet">';
    }

    $content = "<p>" . str_replace("\n", "</p><p>", $msg) ."</p>";
    // build body from message
    $body = '<div class="msgbox">';
    if ($title) {
        $body .= '<h2 class="txt_center">' . $title . "</h2>\n";
    }
    $body .= '<hr><div class="msgbody">' . $content . "</div><br>";
    if ($backUrl) {
        $body .= '<p class="txt_center"><a href="'.$backUrl.'">Back</a></p>';
    }
    $body .= '</div>';

    if ($location) {
        $head = $head.getAudoRedirect($location).PHP_EOL;
    }
    echo renderPage($title, $head, $body);
}
// generate page from given message
else if ($body) {
    $title = $title ? urldecode($title) : "";
    $head = $head ? urldecode($head) : "";
    $body = $body ? urldecode($body) : "";
    $body = str_replace(PHP_EOL, "</br>", $body);
    if ($location) {
        $head = $head.getAudoRedirect($location).PHP_EOL;
    }
    echo renderPage($title, $head, $body);
}
// only redirect with out body
else if ($location) {
    $location = urldecode($location);
    header("Location: ".$location);
}


?>
