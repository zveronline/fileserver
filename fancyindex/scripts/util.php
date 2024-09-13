<?php

// require_once 'config.php';

function debug_print($msg, $key = "") {
    if (DEBUG === true && DEBUG_LOG_FILE !== "") {
        if ($key !== "") { $key = $key.": "; }
        // fwrite(STDERR, $key.msg."\n");
        if (DEBUG_LOG_FILE === "STDOUT") {
            if (is_array($msg)) {
                echo "[DEBUG] ".$key.":[".PHP_EOL;
                foreach ($msg as $k => $v) {
                    echo "[DEBUG] ".$k.'='.$v.PHP_EOL;
                }
                echo "[DEBUG] ]".PHP_EOL;
            } else {
                echo "[DEBUG] ".$key.$msg.PHP_EOL;
            }
        } else {
            $s = "";
            if (is_array($msg)) {
                $s = $key.": [ ";
                foreach ($msg as $k => $v) {
                    $s = $s.$k.'=>'.$v.", ";
                }
                $s = $s . " ]".PHP_EOL;
            } else {
                $s = $key.$msg.PHP_EOL;
            }
            error_log($s, 3, DEBUG_LOG_FILE);
        }
        // debug_print_backtrace();
    }
}

function send_back_redirect() {
    $ref = $_SERVER['HTTP_REFERER'] ?? '/';
    header("Location: ".$ref);
}

// Extract name of Directory from path
// '/some/deep/path/to/dirname/'  ->  'dirname'
// '/some/deep/path/to/dirname'  ->  'dirname'
function getDirName(string $path): string {
    $arr_dirs = explode('/', $path); # getcwd()
    $index = count($arr_dirs) - 1;   # take parent dir name, latest is "" => -2
    $dirname = "";
    // get latest not empty string - the dir name
    if ($index > -1) {
        $dirname = $arr_dirs[$index];
        if (!$dirname && $index > 0) {
            $dirname = $arr_dirs[$index-1];
        }
    }
    return $dirname;
}

function joinPath(string $path, string $file): string {
    if (!$path && !$file) {
        return '';
    } else {
        $p = str_replace("//", "/", "$path/$file"); // case: has '///'
        return str_replace('//', '/', $p);
    }
}

function normalizePath($path) {
    $path = urldecode($path);                   # Non-ASCII chars to UTF-8
    $path = str_replace("../", "", $path);      # security reasone
    $path = str_replace("//", "/", $path);
    return trim($path);
}

//expected trimed path
function getPathDeep(string $path): int {
    if ($path) {
        $cnt = substr_count($path, "/") - substr_count($path, "//");
        if (str_ends_with($path, "/") && str_starts_with($path, "/")) {
            $cnt--;
        }
        return $cnt;
    }
    return 0;
}

function readableBytes($bytes, $dec = 1): string {
    if (!$bytes || !is_numeric($bytes)) {
        return "$bytes";
    }
    $size   = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    if ($factor == 0) $dec = 0;

    return sprintf("%.{$dec}f %s", $bytes / (1024 ** $factor), $size[$factor]);
}

function getSelfUrl($path) {
    $scheme = $_SERVER['REQUEST_SCHEME'] ?? "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!str_starts_with($path, "/")) {
        $path = "/".$path;
    }
    return $scheme."://".$host.$path;
}

function verbose($f, $value, $key = ""):void
{
    if ($f) {
        if ($key) {
            echo "$key: ";
        }
        if (is_array($value)) {
            foreach ($value as $el) {
                echo $el . PHP_EOL;
            }
        } else {
            echo $value . PHP_EOL;
        }
    }
}

function escapePath(string $path):string {
    if ($path) {
        $path = str_replace(' ', '\ ', $path);
        $path = str_replace('\'', '\\\'', $path);
        $path = str_replace('"', '\"', $path);
        // security
        $path = str_replace('&', '\&', $path);
        $path = str_replace('|', '\|', $path);
        $path = str_replace(';', '\;', $path);
    }
    return $path;
}

/*
 * Return full path to main app storage directory (with / at end)
 * FILESERV_ROOT / STORAGE_DIR /
 */
function getStoragePath():string
{
    static $storage = null; // once
    if ($storage == null) {
        $storage = joinPath(FILESERV_ROOT, STORAGE_DIR . "/");
    }
    return $storage;
}

function getExpireSec() {
    return intval(AUTO_CLEANUP) * 3600;
}

# Generate paths for compressed directory at $path
function getDirZipPaths($path) {
    //TODO check $path
    $dirname = getDirName($path);
    $zipname = DIRNAME_PREFIX.$dirname.".".ARCHIVE_EXT;
    $full_path = joinPath(FILESERV_ROOT, $path . "/");

    return [
        "tmp_path" => joinPath(TMP_DIR, $zipname),
        "full_path" => $full_path,             # full path to the archived dir
        "zipname" => $zipname,
        "new_zipfile" => joinPath(getStoragePath() , $path . '/' . $zipname),
    ];
}

// first step for compress dir
function mkCompressDir($full_path, $tmp_path, $v = false): int
{
    $parentdir = escapePath(dirname($full_path));

    $relative_path = escapePath(substr($full_path, strlen($parentdir) + 1));
    $compress_cmd = str_replace(["%outname", "%path-to-zip"],
        [ escapePath($tmp_path) , $relative_path ], COMPRESS_CMD
    );
    $cmd = "cd $parentdir && $compress_cmd";
    // debug_print($cmd, "Command");
    verbose($v, $cmd, "Command");

    $output = null; $exitCode = null;  # StdOut & ExitCode
    exec($cmd, $output, $exitCode);    # run system command to archive directory
    if ($exitCode !== 0 && !in_array($exitCode, COMPRESS_CMD_IGNORE, true)) {
        // verbose($v, $exitCode, 'ExitCode'); -- On 127: command not found
        // verbose($v, $output);               -- nothing will be output here!
        unlink($tmp_path);
        return $exitCode;
    }
    return 0; //exit code no errors
}

?>
