<?php

require_once 'config.php';
require_once 'util.php';
require_once 'compressed-db.php';

define("DELETE_URL",
'<a href="/fancyindex-logic.php?action=delete-zip&path={% path %}">Delete</a>');

function p_createCompressedDB(): array
{
    $title = "Compressed Archives";
    // override fancyindex css-rule for td a display:block (urls in column)
    $head ="<style>td a {display: inline;}</style>";

    $b = "<div> <center>
    <table>" . mkFileTableHead() . "\n<tbody>";
    $records = compressedDB_fetchAll();
    if ($records) {
        foreach ($records as $fullpath => $parts) {
            if ($fullpath) {
                $path = str_replace(FILESERV_ROOT, "", $fullpath);
                $path = str_replace("//", "/", $path);
                if (!str_starts_with($path, "/")) {
                    $path = "/" . $path;
                }
                $date = "?";
                $timestamp = $parts[1] ?? "";
                $size = readableBytes($parts[2] ?? 0);
                if (is_numeric($timestamp)) {
                    $date = date('d-m-Y', $timestamp); // 'H:i:s d-m-Y'
                }
                $btns = str_replace("{% path %}", urlencode($path), DELETE_URL);
                $basename = basename($path);
                $path_url =  '<a href="' . $path.'">' . $basename . '</a>';
                $dir = dirname($path);
                if ($dir) {
                    $dir0 = $dir;
                    if (STORAGE_DIR) {
                        $dir0 = str_replace(STORAGE_DIR, '', $dir);
                        if (!str_starts_with($dir0, "/")) {
                            $dir0 = "/" . $dir0;
                        }
                        $dir0 = str_replace("//", "/", $dir0);
                    }
                    $durl = '<a href="' . $dir0 .'">' . $dir0 . '</a>';
                    $path_url = $durl . '/' . $path_url;
                }

                $b .= mkFileRow($path_url, $size, $date, $btns);
            }
        }
    } else {
        $b .= mkFileRow('empty', '', '', ''); // empty
    }
    $b .= '<tbody><table>
    <br><a href="/">FileServ Root</a>
    </div>';
    return ['title' => $title, 'head' => $head, 'body' => $b];
}

function mkFileTableHead() {
    return
        '<thead><tr>' .
            '<th colspan="2">File Name</th>'.
            '<th>Size</th>'.
            '<th>Date</th>'.
            '<th>Actions</th>'.
        '</tr></thead>';
}

function mkFileRow($path_url, $size, $date, $btns) {
    return
        "<tr>" .
            "<td colspan=\"2\" class=\"link\">$path_url</td>".
            "<td class=\"size\">$size</td>".
            "<td class=\"date\">$date</td>
            <td class=\"btns\">$btns</td>" .
        "</tr>\n";
}
