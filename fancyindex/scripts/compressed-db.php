<?php

require_once 'config.php';
require_once 'util.php';


// db-file
function compressedDB_getPath(): string {
    if (!COMPRESSED_DB) {
        return ''; // disabled
    } elseif (str_starts_with(COMPRESSED_DB, "/")) {
        return COMPRESSED_DB;
    } else {
        return getStoragePath() . COMPRESSED_DB;
    }
}

function compressedDB_canWrite(string $dbfile): bool {
    if ($dbfile) {
        if (!file_exists($dbfile)) {
            $dir = dirname($dbfile);
            if (!file_exists($dir)) {
                if (!mkdir($dir, 775, true)) {
                    return false;
                }
            }
        }
        // TODO  https://www.php.net/manual/en/function.flock.php
        return true;
    }
}

// just add to end of the file without dups checking
function compressedDB_add($path, int $timestamp = 0, int $size = 0): bool
{
    $dbfile = compressedDB_getPath();
    if ($path && $dbfile) { //is_writable(dirname($dbfile))) {
        if ($timestamp <= 0) {
            $timestamp = (new DateTime())->getTimestamp();
        }
        if ($size <= 0) {
            $size = file_exists($path) ? filesize($path) : 0;
        }
        $record = "$path:$timestamp:$size\n";
        if (compressedDB_canWrite($dbfile)) {
            if (file_put_contents($dbfile, $record, FILE_APPEND | LOCK_EX)) {
                return true;
            }
        }
    }
    return false;
}

# private
# Fully rewrite content of the db-file
# TODO fix possible issue: multiple access to this db-file
# https://www.php.net/manual/en/function.flock.php
function compressedDB_write(array $records): bool {
    $dbfile = compressedDB_getPath();
    if ($records !== null && $dbfile) {
        $date = (new DateTime()) ->format('H:m:s d-m-Y');
        $content = "# Updated $date\n";
        foreach ($records as $record) {
            $line = join(':', $record);
            $content .= $line . "\n";
        }
        if (compressedDB_canWrite($dbfile)) {
            return file_put_contents($dbfile, $content, LOCK_EX) > 0;
        }
    }
    return false;
}

function compressedDB_remove($path): bool {
    $dbfile = compressedDB_getPath();
    if ($path && $dbfile && file_exists($dbfile)) {
        $records = compressedDB_fetchAll();
        if ($records && count($records) > 0) {
            unset($records[$path]);
        }
        return compressedDB_write($records);
    }
    return false;
}

/*
 * Return All records in associative array where:
 * - keys - is the path to zipfile
 * - value - array of lines separated by : path:timestamp:...
 */
function compressedDB_fetchAll(): ?array {
    $dbfile = compressedDB_getPath();
    if ($dbfile && file_exists($dbfile) && is_readable($dbfile)) {
        $txt = file_get_contents($dbfile);
        $lines = explode("\n", $txt);
        $data = [];
        foreach ($lines as $line) {
            if (!$line || str_starts_with($line, "#")) {
                continue;
            }
            $parts = explode(":", $line);
            if ($parts && count($parts) > 1) {
                $path = $parts[0];
                $data[$path] = $parts;
            }
        }
        return $data;
    }
    return null;
}

/*
 * Fetch All records whose creation time is older than given (in sec)
 */
function compressedDB_fetchOlderThan($nowtimestamp, $sec): ?array {
    $old = [];
    if (is_numeric($nowtimestamp) && is_numeric($sec) && $sec > 0) {
        $records = compressedDB_fetchAll();
        if ($records) {
            foreach ($records as $path => $parts) {
                if ($path && $parts && count($parts) > 1) {
                    $timestamp = intval($parts[1]);
                    if ($nowtimestamp - $timestamp >= $sec) {
                        $old[$path] = $parts;
                    }
                }
            }
        }
    }
    return $old;
}

function compressedDB_removeAll($records): int {
    if ($records && !empty($records)) {
        $old = compressedDB_fetchAll();
        if ($old) {
            $cnt = count($old);
            foreach($records as $path => $parts) {
                unset($old[$path]);
            }
            $removed = ($cnt - count($old));
            if ($removed > 0) {
               compressedDB_write($old);
            }
            return $removed;
        }
    }
    return 0;
}



// ----------------------------------------------------------------------------


function checkAutoClear():int {
    if (AUTO_CLEANUP && AUTO_CLEANUP > 0) {
        $expire_sec = getExpireSec();
        $nowtimestamp = (new DateTime())->getTimestamp();
        $records = compressedDB_fetchOlderThan($nowtimestamp, $expire_sec);
        $deleted = 0;
        if ($records && !empty($records)) {
            // deleteExpited Archives from disk
            foreach ($records as $path => $parts) {
                try {
                    if ($path && file_exists($path)) {
                        debug_print($path, "Delete");
                        unlink($path);
                        $deleted++;
                    }
                } catch (\Throwable $th) {
                    debug_print($th);
                }
            }
            compressedDB_removeAll($records);
        }
        return $deleted;
    }
    return -1;
}

?>
