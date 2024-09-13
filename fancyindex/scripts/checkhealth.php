<?php

require_once 'config.php';
require_once 'util.php';
require_once 'compressed-db.php';

$broken = [];

function ch_getStat(string $file): string {
    if (file_exists($file)) {
        $i = stat($file);
        return "uid: ". $i['uid'] . ' gid: '. $i['gid'] .
            ' mode:' . $i['mode'];
    } else {
        return 'Not Found';
    }
}

// (@mkdir('')) OR (dir(error_get_last()['message']));

function ch_isOK($b): string
{
    if ($b) { return "OK"; } else { return "FAIL"; }
}

function ch_checkNewFile(string $cfile): bool
{
    try {
        $data = (new DateTime()) ->format('H:m:s d-m-Y');
        $ret = true; $status = false;
        echo "Create File [$cfile] ";
        if (file_exists($cfile)) {
            echo "Already Exists!  try to delete... ";
            if (@unlink($cfile)) {
                echo "Deleted.\n";
            } else {
                echo "Cannot Delete! -- [FAIL]\n";
                return false;
            }
        }
        echo "- Write to File: ";
        $ret = @file_put_contents($cfile, $data);
        $has = file_exists($cfile);
        $body = @file_get_contents($cfile);
        $f = $ret && $has && $body === $data;
        $status = ch_isOK($f);
        echo "Created: $has -- [$status]\n";
        @unlink($cfile);
        return $f;
    } catch (\Throwable $th) {
        echo "\nError: " . $th->getMessage(). ' :' . $th->getLine() . "\n";
    }
    return false;
}

function ch_checkMkDir(string $cdir): bool
{
    try {
        echo "Create Dir [$cdir]: ";
        $ret = true; $status = false;
        if (file_exists($cdir)) {
            echo 'Already Exists try to delete - ';
            if (rmdir($cdir)) {
                echo "Deleted.\n";
            } else {
                echo "Cannot Delete! -- [FAIL]\n";
                return false;
            }
        }
        $ret = @mkdir($cdir, 0775, true);
        $f = $ret && file_exists($cdir);
        $status = ch_isOK($f);
        echo "Created: $ret -- [$status]\n";
        @rmdir($cdir);
        return $f;
    } catch (\Throwable $th) {
        echo "\nError: " . $th->getMessage(). ' :' . $th->getLine() . "\n";
    }
    return false;
}

function ch_checkDirPerms($name, $dir) {
    $hasDir = file_exists($dir);
    $writebleDir = is_writable($dir);
    echo sprintf("\n==[%s]==\n%s exists: %s, writable: %s, stat: %s\n",
        $name, $dir, $hasDir, $writebleDir, ch_getStat($dir));

    echo "Check Writable Access:\n";
    $ok1 = ch_checkNewFile($dir . 'check-write-file');
    $ok2 = ch_checkMkDir($dir . 'check-write-dir/');
    echo "Access is " . (($ok1 && $ok2) ? "OK" : "DENY");
}

function critical_msg($msg)
{
    global $broken;
    $broken[] = $msg;
    echo "\n=======================================================================\n";
    echo "[CRITICAL] $msg\n";
    echo "=======================================================================\n";
    echo "[BROKEN]\n\n";
}

function critical_StopOnNotWritable($file, $msg = ""):bool
{
    if (!is_writable($file)) {
        critical_msg($msg . " '$file' is Not Writable!");
        return true;
    }
    return false;
}

function ch_checkCompression($dirname): void
{
    echo "\n==[Compression:CMD]==\n";
    // $executable = strtok(COMPRESS_CMD, " ");
    $executable = explode(' ', trim(COMPRESS_CMD))[0];
    echo "Check executable command [$executable] -- ";

    $output = null; $exitCode = null;  # StdOut & ExitCode
    exec("which $executable", $output, $exitCode);    # run system command to archive directory
    if ($exitCode !== 0) {
        echo " FAIL ExitCode: $exitCode ";
        if ($exitCode == "127") {
            echo "command [$executable] not found\n";
        }
        if ($output && is_array($output)) {
            echo PHP_EOL;
            foreach ($output as $line) {
                echo "$line" . PHP_EOL;
            }
        }
        return;
    } else {
        echo " OK";
    }
    // step 1 create dir with files to compress
    $dir0 = getStoragePath();// FILESERV_ROOT . STORAGE_DIR . '/';
    echo "\nFILESERV_ROOT/STORAGE_DIR: [$dir0] - ";
    if (!file_exists($dir0)) {
        echo "Not Exists\n";
        if (critical_StopOnNotWritable(dirname($dir0), 'Directory')) {
            return;
        }
        echo "Try to create $dir0  -- ";
        if (!mkdir($dir0, 775, true)) {
            echo "[FAIL]\n";
            return;
        }
        echo "Created!\n";
    }
    if (critical_StopOnNotWritable($dir0, 'Directory')) {
        return;
    }
    echo "OK\n";
    $d = $dir0 . $dirname . '/';
    if (!file_exists($d)) {
        if (!mkdir($d, 0775, true)) {
            echo "Cannot create Dir $d\n";
            return;
        }
    }
    $cfile = $d . 'now.txt';
    $date = (new DateTime()) ->format('H:m:s d-m-Y');
    $hasFile = file_put_contents($cfile, $date) > 0;

    $full_path = $d;
    $zipname = DIRNAME_PREFIX . $dirname . "." . ARCHIVE_EXT;
    $tmp_path = TMP_DIR . $zipname;
    echo "Path to compress: $d  Tmp-Path: $tmp_path\n";

    $exitCode = mkCompressDir($full_path, $tmp_path, true);
    echo "ExitCode: $exitCode\n";
    $hasZip = file_exists($tmp_path);
    if ($hasZip) {
        echo exec("file $tmp_path");
        echo "Delete compressed $tmp_path\n";
        unlink($tmp_path);
    }
    if ($hasFile) {
        echo "Delete file $cfile\n";
        unlink($cfile);
    }
    echo "Delete dir $d\n";
    rmdir($d);
    $ok = $hasZip && $exitCode === 0;
    echo "Done. Compression Cmd is " . ($ok ? "OK" : "FAIL") . "\n";
}

function ch_checkCompressionDB() {
    echo "\n==[Compression:DB]==\n";
    $dbfile = compressedDB_getPath();
    echo "Absolute path to db file: '$dbfile'\n";
    if (critical_StopOnNotWritable(dirname($dbfile), 'Directory')) {
        return;
    }
    if (file_exists($dbfile) && critical_StopOnNotWritable($dbfile)) {
        return;
    }
    $dummypath = "/test/path/to/archive/directory.zip";
    $add = compressedDB_add($dummypath, 0);
    $rm = compressedDB_remove($dummypath, 0);
    if (!$add) {
        echo "Error: Cannot add record into db\n";
    }
    if (!$rm) {
        echo "Error: Cannot remove record from db\n";
    }
    echo "Done. CompressionDB is " . (($add && $rm) ? "OK" : "FAIL") . "\n";
}

function ch_showConfigValues() {
    echo "\n==[Config]==\n";
    $constants = get_defined_constants(true)['user'];
    if (!$constants || empty($constants)) {
        echo "CHECK_ENV_VAR: [" . CHECK_ENV_VAR . "]";
    } else {
        foreach ($constants as $key => $value) {
            if (is_array($value)) {
                $value = "[" . join(" ",$value) . "]";
            }
            echo "$key = $value\n";
        }
    }
    echo "-----------------------------------------\n";
    echo "Resolved Full Paths: \n";
    echo "StorageDir: " . getStoragePath() . "\n";
    echo "CompressedDB: " . compressedDB_getPath() . "\n";
}

function ch_checkValidateDirCompressing() {
    echo "\n==[ValidateDirCompressing]==\n";
    echo "Can compress Starts with ALLOWED_DEEP:". ALLOWED_DEEP . "\n";
    //dev test
    $arr = ['/', '/sub1', '/s1//', '/s1/sub2', '/1/2/', '/a/b//', "/s1/s2/s3/"];
    $i = 0;
    foreach ($arr as $dir) {
        $deep = getPathDeep($dir, true);
        $can = $deep >= ALLOWED_DEEP;
        echo "$dir deep:$deep is " . ($can ? "Yes" : "NO") . "\n";
        if ($can) {
            $i++;
        }
    }
    echo "Done. ValidateDirCompressing is " . (($i > 0) ? "OK" : "BROKEN") . "\n";
}

function ch_checkAutoClear() {
    echo "\n==[AutoClear]==\n";
    echo "Automatic deletion of archived directories is -- ";
    if (AUTO_CLEANUP && AUTO_CLEANUP > 0) {
        $readable = "?";
        if (AUTO_CLEANUP < 24) {
            $readable = AUTO_CLEANUP . " hours";
        } elseif (AUTO_CLEANUP < 24 * 7) {
            $readable = (AUTO_CLEANUP / 24) . " days";
        } elseif (AUTO_CLEANUP < 24 * 30) {
            $readable = (AUTO_CLEANUP / (24 * 30)) . " months";
        }
        echo "[ON]\nWill automatically delete all archives whose creation ".
             " date is older than $readable (or " . AUTO_CLEANUP . " hours)\n";
    } else {
        echo "[OFF]\nDir-archives will be stored until they are " .
            "manually deleted. (Should be deleted by yourself)\n";
    }
    // Create Expired and Fresh Files and run AutoClear
    if (AUTO_CLEANUP > 0) {
        echo "Test AutoClear in Action\n";
        if (critical_StopOnNotWritable(getStoragePath(), "Directory")) {
            return;
        }
        echo "Create two files: expired and fresh...\n";
        $file_expired = getStoragePath() . "dummy_expired.". ARCHIVE_EXT;
        $file_fresh = getStoragePath() . "dummy_fresh." . ARCHIVE_EXT;
        $nowtimestamp = (new DateTime())->getTimestamp();
        $expired_time = $nowtimestamp - getExpireSec();
        echo "NowTime: " . date('H:i:s d-m-Y', $nowtimestamp) . " $nowtimestamp\n";
        echo "Expired: " . date('H:i:s d-m-Y', $expired_time) . " $expired_time\n";
        $fn_ok = touch($file_fresh);
        $fn_ok = $fn_ok && compressedDB_add($file_fresh);
        $success = 0;
        if (touch($file_expired, $expired_time)) {
            if (compressedDB_add($file_expired, $expired_time)) {
                $removed = checkAutoClear();
                echo "AutoClear Removed: $removed \n";
                echo "File $file_expired is -- ";
                if (!file_exists($file_expired)) {
                    echo "Deleted [OK]\n"; // expired file must be deleted
                    $success++;
                } else {
                    echo "Not Deleted! [FAIL]\n";
                }
                echo "File $file_fresh is -- ";
                if (file_exists($file_fresh)) {
                    echo "Exists [OK]\n";  // fresh file must NOT be deleted
                    $success++;
                } elseif ($fn_ok) {
                    echo "Was Deleted! [ERROR]\n";
                } else {
                    echo "Not Created [ERROR]\n";
                }

                if (compressedDB_remove($file_fresh)) {
                    $success++;
                } else {
                    echo "Cannot remove record from db-file [WARN]\n";
                }
            } else {
                unlink($file_expired);
            }
        } else {
            echo "Cannot Create $file_expired";
        }
        unlink($file_fresh);
        echo "Done. AutoClear is " . (($success >= 3) ? "OK" : "BROKEN") . "\n";
    }
}

function ch_checkRenderPage() {
    require_once "fancyindex-render.php";
    echo "\n==[RenderPage]==\n";
    $msg = 'MESSAGE';
    $page = renderPage('title', 'head', $msg);
    $ok = str_contains($page, "</html>") && str_contains($page, "</body>") &&
        str_contains($page, $msg);
    if (!$ok) {
        echo "$page\n__DIR__ is '" . __DIR__ . "'\n";
    }
    echo "Done. RenderPage is " . ($ok ? "OK" : "BROKEN") . "\n";
}

function ch_checkPerms() {
    ch_showConfigValues();

    echo "\n==[Permissions]==\n";
    clearstatcache();
    echo "PHP ProcessID: " . getmypid();
    $owner = get_current_user(); $uid = getmyuid(); $gid = getmygid();
    echo " Current script owner: $owner UID: $uid GID: $gid" . PHP_EOL;

    if (function_exists('posix_getpwuid')) {
        try {
            echo "Process-User: ";
            $pu = posix_getpwuid(posix_geteuid());// $processUser
            echo $pu['name'] . ' uid:' . $pu['uid'] . ' gid:'. $pu['gid'] . PHP_EOL;
        } catch (\Throwable $th) {
            echo "Error: " . $th->getMessage() . ' :' . $th->getLine() . "\n";
        }
    }

    try {
        $output = null; $exitcode = null;
        exec('whoami', $output, $exitcode);
        $user0 = ($output && $output[0]) ? $output[0] : '?';
        echo "whoami[$exitcode]: $user0 \n";
    } catch (\Throwable $th) {
        echo "whoami: error: " . $th->getMessage(). ' :' . $th->getLine() . "\n";
    }

    ch_checkDirPerms('Root', FILESERV_ROOT);
    ch_checkDirPerms('Storage', getStoragePath());
    ch_checkDirPerms('TMP', TMP_DIR);

    ch_checkCompression('check-dir');
    ch_checkCompressionDB();
    ch_checkValidateDirCompressing();
    ch_checkAutoClear();
    ch_checkRenderPage();

    // Total
    global $broken;
    echo "\n==[HELTHY]== -- ";
    if (!empty($broken)) {
        echo "[BROKEN] (Cannot work)\nCritical Errors:\n";

        foreach ($broken as $msg) {
            echo $msg . "\n";
        }
    } else {
        echo "[OK] (Ready to Work!)\n";
    }
}
