<?php

require 'config.php';
require 'util.php';
require 'compressed-db.php';

# Form Buttons bind footer.html
define('SELF',              "/fancyindex-logic.php");
define('RENDER',            "/fancyindex-render.php");

define('A_NEW_DIRZIP',      "Create Archive");
define('A_MAKE_DIRZIP',     "CompressDir");
define('A_CLEAR_DIRZIP',    "Delete Archive");
define('A_DELETE_ZIP',      "delete-zip");             // button from dashboard
define('A_DOWNLOAD_DIRZIP', "Download Archive");

# Messages to Nofify Users about different events:
define('CANT_COMPRES_DIR',  "Cannot compress this directory");
define('CANT_MOVE_ZIP',     "Cannot move a tmp-zip-file to destination");
define('CANT_CREATE_DIR',   "Cannot create inner dir for archive");
define('STORAGE_DIR_ERR',   "Cannot Create StorageDir");
define('DIR_NOT_EXISTS',    "Directory Not Exists");
define('NO_PERMS_TO_WRITE', "Directory Not Writable (No Permission)");
define('MSG_NEW_ARCHIVE',
    "Starting compression a directory...".PHP_EOL.
    "This may take some time. Please Wait...".PHP_EOL.
    "When the directory will be compressed,".PHP_EOL.
        "You will be automatically redirected into Index");

# notify on dublicate click compress-button then a compression alredy runs
define('COMPRESSING_IN_PROGERSS',
    "Directory Compression has Already Started!".PHP_EOL.
    "But Not finished yet!".PHP_EOL.
    "After compression is complete, ".PHP_EOL.
    "the archive will appear in the Index (list of files).".PHP_EOL.
        "Check the Index a little later.");

define('DOWNLOAD_MSG',
    "If Archive file are not downloaded automatically, click this ");

# notifier for autodownload
define('ARCHIVE_CREATED',  "The Directory Compressed! " . PHP_EOL . DOWNLOAD_MSG);
define('DOWNLOAD_ARCHIVE', "Starts Downloading " . PHP_EOL . DOWNLOAD_MSG);
define('NO_DIR_ARCHIVE',   "No archive has been created for this directory yet" . PHP_EOL);

# send redirect to a render script
function send_redirect2($title, $message, $backUrl = "", $opt = "") {
    debug_print($message, "msg");
    $msg = urlencode($message);
    $title = urlencode($title);
    // send data to render for generate new page with specified body
    $newlocation = "Location: ".RENDER."?title=$title&msg=$msg";
    if ($backUrl) {
        $newlocation .= "&back=" . urlencode($backUrl);
    }
    if ($opt !== null && $opt !== "") {
        $newlocation = $newlocation.$opt;
    }
    header($newlocation);
    debug_print($newlocation, "Redirect");
    exit();
}


# Extract path to the directory to be compressed (from Referer or GET:dir
function getPath(): string {
    $ref = $_SERVER['HTTP_REFERER'] ?? null;
    if (!$ref) {   # attempt to hack?
        if (DEBUG) {
            send_redirect2("Error", "[DEBUG] No Referer ['".$ref."] " , "/");
        }
        exit();
    }
    $parsed_url = parse_url($ref);
    $path = $parsed_url['path'] ?? '';
    return normalizePath($path);
}


function isOkStorageDir() {
    $dir = getStoragePath();
    if (!file_exists($dir)) {
        return mkdir($dir, 0775);
    } elseif (!is_writable($dir)) {
        send_redirect2("Error", NO_PERMS_TO_WRITE . '<br>' . STORAGE_DIR, $ref);
        return false;
    }
    return true;
}

# exit from script if cannot create archive for given path
function validateDirCompressing(string $path, array $zip_paths) {
    $ref = $_SERVER['HTTP_REFERER'] ?? null;
    $tmp_path = $zip_paths['tmp_path'] ?? null;
    $full_path = $zip_paths['full_path'] ?? null;
    $new_zipfile = $zip_paths['new_zipfile'] ?? null;

    if (!$tmp_path || !$full_path || !$new_zipfile) {
        send_redirect2("Error", "Internal Error", $ref);
    }
    if (!$path || $path === "/" || $path === " ") {
        send_redirect2("Error", CANT_COMPRES_DIR, $ref);
    }
    # Access only from RootDir of FileServer! Ignore an ather paths!
    if (!str_starts_with($full_path, FILESERV_ROOT)) {
        send_redirect2("Error", CANT_COMPRES_DIR, $ref);
    }
    if (ALLOWED_DEEP && getPathDeep($path) < ALLOWED_DEEP) {
        send_redirect2("Error", CANT_COMPRES_DIR . "<br>" .
            "Can Only Compress Directory deeper than " . ALLOWED_DEEP, $ref);
    }
    $arr_dirs = explode('/', $path); # getcwd()
    # Then File Already exists just redirect to self-page
    if (file_exists($new_zipfile)) {
        debug_print("Archive Already exists");
        if ($_GET['autodownload'] === "1") {
            $url = getSelfUrl($path.$zip_paths['zipname']);
            header("Location: ".$url);
            exit();
        }
        send_back_redirect();
        exit();
    }
    if (!isOkStorageDir()) {
        send_redirect2("Error", STORAGE_DIR_ERR, $ref);
    }
    if (!file_exists($full_path)) {
        send_redirect2("Error", DIR_NOT_EXISTS, $ref);
    }
    // Case: put zip into same directory
    if (STORAGE_DIR === '' && !is_writable($full_path)) {
        send_redirect2("Error", NO_PERMS_TO_WRITE . '<br>' . $full_path , $ref);
    }
    # Case then try to make new archive then tmp file already exists
    if (file_exists($tmp_path.".lock") || file_exists($tmp_path)) {
        send_redirect2("Warn", COMPRESSING_IN_PROGERSS, $ref);
    }
}

// TODO determine by runned process not /tmp/dir.zip.lock
function isAlredyRuns($cmd) {
    $output = null;                # StdOut
    $retval = null;                # ExitCode
    exec($cmd, $output, $retval);  # run system command to archive directory
    if ($retval !== 0) {
        send_redirect2("Fail", "Exec Error ExitCode:".$retval, $backUrl);
        exit();
    }
}



function onPreCompressDir() {
    debug_print("CHECK BEFOR START COMPRESSION", "\n\n");
    $path = getPath();
    validateDirCompressing($path, getDirZipPaths($path));
    # Redirect to page with message and auto redirect to self for compressing
    $url = getSelfUrl(SELF);
    $cb = "";
    if (isset($_GET['autodownload'])) {
        $cb = $checkbox."&autodownload=1";
    }
    // setup tha auto redirect after showing page with given message
    $opt="&location=".urlencode($url."?action=".A_MAKE_DIRZIP."&dir=".$path.$cb);
    $ref = $_SERVER['HTTP_REFERER'] ?? "/";
    send_redirect2(A_MAKE_DIRZIP, MSG_NEW_ARCHIVE, $ref, $opt);
}

function sendRedirectToDownload($title, $message, $backUrl, $path, $zip_paths) {
    $durl = getSelfUrl(STORAGE_DIR . $path . $zip_paths['zipname']);
    $opt = "&location=".urlencode($durl);
    $link = '<a href="'.$durl.'">Download</a';
    send_redirect2($title, $message . $link, $backUrl, $opt);
}


function onDoCompressDir() {
    debug_print("START COMPRESSION", "\n\n");
    $path = $_GET['dir'] ?? null; # full path to the directiry to be compressed
    $zip_paths = getDirZipPaths($path);
    validateDirCompressing($path, $zip_paths);

    $backUrl = getSelfUrl($path);
    $tmp_path = $zip_paths['tmp_path'];          # TMP_DIR + zip-name
    $full_path = $zip_paths['full_path'];
    $new_zipfile = $zip_paths['new_zipfile'];
    $tmp_lock = $tmp_path.".lock";

    debug_print($path, "dir(path)");
    debug_print($zip_paths, "zip_paths");
    debug_print($backUrl, "backUrl");

    // sleep(1); clearstatcache();
    if (file_exists($tmp_lock) || file_exists($tmp_path)) {
        send_redirect2("Warn", COMPRESSING_IN_PROGERSS, $backUrl);
        exit();
    }
    // create a new directory path for archive(mirror of original path + prefix)
    $dstdir = dirname($new_zipfile);
    if (!file_exists($dstdir)) {
        if (!mkdir($dstdir, 0775, true)) {
            send_redirect2("Error", CANT_CREATE_DIR . ' ' . $dstdir, $back);
        }
    }
    if (!is_writable($dstdir)) {
        send_redirect2("Error", NO_PERMS_TO_WRITE . '<br>' . $dstdir, $ref);
    }

    checkAutoClear();

    // use this because file_exists($tmp_path) does't work properly as expected!
    touch($tmp_lock); // mark for other php-scripts that starts to compress dir
    $exitCode = mkCompressDir($full_path, $tmp_path, false);
    if ($exitCode > 0) { // has Error
        send_redirect2("Fail", "Exec Error ExitCode:".$exitCode, $backUrl);
        unlink($tmp_lock);             # Code 18: Permission Denied
        exit();
    }
    sleep(1);                      # this does the trick before use rename()
    // move from tmp to dst path
    if (rename($tmp_path, $new_zipfile)) { # can be 'Permission Denied'
        unlink($tmp_lock);
        compressedDB_add($new_zipfile);
        debug_print("Directory successfully compressed! ", $new_zipfile);
        if (isset($_GET['autodownload'])) {
            debug_print("Autodownload!", "Flag");
            // auto redirect after showing page with given message
            sendRedirectToDownload('Done!', ARCHIVE_CREATED, $backUrl,
                $path, $zip_paths);
        }
        header("Location: ".$backUrl);
    } else {
        send_redirect2("Error", CANT_MOVE_ZIP, $back);
    }
}

function onDownloadCompressedDir() {
    $path = getPath();
    $zip_paths = getDirZipPaths($path);
    $new_zipfile = $zip_paths['new_zipfile'] ?? null;
    $backUrl = getSelfUrl($path);

    if ($new_zipfile && file_exists($new_zipfile)) {
        # Block an attempt to download anything outside the FileServ Root Directory
        if (!str_starts_with($new_zipfile, getStoragePath()) ||
            !str_ends_with($new_zipfile, ARCHIVE_EXT)) {
            send_redirect2("Error", "[DEBUG] Bad Archive Name", "/".$path);
        } else {
            $durl = getSelfUrl($path . $zip_paths['zipname']);
            $opt = "&location=" . urlencode($durl);
            $link = '<a href="' . $durl . '">Download</a';
            sendRedirectToDownload('Downloading', DOWNLOAD_ARCHIVE, $backUrl,
                $path, $zip_paths);
        }
    } else {
        send_redirect2("Warn", NO_DIR_ARCHIVE, $backUrl);
    }
}

/** Allow delete only from StorageDir and only files with configured extension
 * check to protect against deletion of the content of the file server itself
 * Block an attempt to delete anything outside the FileServ Root Directory
 */
function isValidPathToDelete($path): bool {
    return $path && str_starts_with($path, getStoragePath()) &&
        str_ends_with($path, ARCHIVE_EXT);
}
/**
 * Delete Archived Dir from disk and db
 */
function deleteValidArchivedDir($path) {
    if (isValidPathToDelete($path)) {
        debug_print($path, "Delete File");
        unlink($path);                                   # Delete archive-file
        compressedDB_remove($path);
        return true;
    }
    return false;
}

// button from autoindex zipname taked by ref $_SERVER['HTTP_REFERER']
function onDeleteCompressedDir() {
    debug_print("DELETE COMPRESSED DIR [REFERER]", "\n\n");
    $path = getPath();
    $zip_paths = getDirZipPaths($path);
    $new_zipfile = $zip_paths['new_zipfile'] ?? null;

    if ($new_zipfile && file_exists($new_zipfile)) {
        if (!deleteValidArchivedDir($new_zipfile)) {
            $back = "/$path";
            send_redirect2("Error", "Bad Archive Name: '$path'", $back);
        }
    }
    send_back_redirect();
}

// button from dashboard: direct path from GET[path]
function onDeleteZip() {
    debug_print("DELETE COMPRESSED DIR [DIRECT]", "\n\n");
    $path = $_GET["path"] ?? '';
    if (!$path) {
        send_back_redirect();
    }
    $path = normalizePath($path);
    $zipfile = joinPath(FILESERV_ROOT, $path);

    if (file_exists($zipfile)) {
        debug_print($zipfile, "File Exists");
        if (!deleteValidArchivedDir($zipfile)) {
            $back = $_SERVER['HTTP_REFERER'] ?? '/';
            send_redirect2("Error", "Bad Archive Name: '$path'", $back);
        }
    }
    send_back_redirect();
}

function onDashboard(): void {
    header("Location: ".RENDER."?q=dashboard");
    exit();
}

function doAutoClear(): void {
    checkAutoClear();
    header("Location: ".RENDER."?q=dashboard");
    exit();
}

function onCheckHealth() {
    header('Content-Type:text/plain');
    require_once "checkhealth.php";
    ch_checkPerms();
    die();
}


$action = $_GET['action'] ?? null;
// triggered by button from a form
if ($action === A_NEW_DIRZIP) {
    onPreCompressDir();
}
// triggered automatically after render page with "Compressing... Wait..."
elseif ($action === A_MAKE_DIRZIP) {
    onDoCompressDir();
}
elseif ($action === A_DOWNLOAD_DIRZIP) {
    onDownloadCompressedDir();
}
// triggered by button from a form
elseif ($action === A_CLEAR_DIRZIP) {
    onDeleteCompressedDir();
}
elseif ($action === A_DELETE_ZIP) {
    onDeleteZip();
}
elseif ($action === 'autoclear') {
    doAutoClear();
}
elseif ($action === "dashboard") {
    onDashboard();
}
elseif ($action === "checkhealth") {
    onCheckHealth();
}
elseif (DEBUG && $action) {
    send_redirect2("Uknown", $action, "/");
}
# Ignore all other actions
else {
    send_back_redirect();
}

# chmod($uploadedFile, 0755);
# clearstatcache();
