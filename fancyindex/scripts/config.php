<?php

##########################     SETTINGS      ##################################

define0('FILESERV_ROOT', "/srv/fileserv/");   # root of file-server
define0('STORAGE_DIR',   ".archived_dirs");   # dirname for archived dirs
define0('TMP_DIR',       "/tmp/fileserv/");        # temp file for archiving directories

define0('CSS_STYLE',     "/.nginxy/css/style.css");  # default fancyindex css

# prefix for an archive file name. (Can be empty)
define0('DIRNAME_PREFIX', '_');

# nesting depth from FILESERV_ROOT with which directory archives are allowed
# for all directories that have a nesting depth less than the specified value,
# archives cannot be created.
# Value 0 - alloy to create archives for any directories, except FILESERV_ROOT
define0('ALLOWED_DEEP',   2);

# Automatic cleaning of old directory archives
# If the value is greater than zero, automatically delete all archives whose
# creation time is greater than the specified number
define0('AUTO_CLEANUP',     4 * 24);  # expire time in hours

# File containing a list of paths to all created archives
# if the file name starst with '/' then treat as an absolute path
# otherwise the directory FILESERV_ROOT/STORAGE_DIR/ will be used as parent
define0('COMPRESSED_DB',  "compressed.db"); # list of all archives

define0('ARCHIVE_EXT',    "zip");
define0('COMPRESS_CMD',   "zip -rq %outname %path-to-zip");
define0('COMPRESS_CMD_IGNORE', [  # Ignored exits code to continue work
    # When you try to compress directory with some count of not readable files
    # Useful then you want to confinue compression and skip not readable files.
    18 # man zip:  Warning: "zip could not open a specified file to read"
]);

# Known issue with 7z:
# broken a non-ASCII filenames. To fix it you need to extract a archive via cmd:
# LC_ALL=C 7z x $archive
# ^^^^^^^^

# define0('ARCHIVE_EXT',    "7z");
# define0('COMPRESS_CMD',   "7z a %outname %path-to-zip";

# define0('ARCHIVE_EXT',    "tar.gz");
# define0('COMPRESS_CMD',   "tar -czf %outname %path-to-zip";

define0('DEBUG',          false);
define0('DEBUG_LOG_FILE', "/tmp/nginx-fi.log");

define0('CHECK_ENV_VAR',   "Default-From-Config");       # for check via EnvVar

###############################################################################

// define('__ROOT__', dirname(dirname(__FILE__)));  #  site root

$constnames_list = [];

# define the constant with name $key and defaultValue
# if EnvVar with $key name exists take value from it
function define0($key, $defaultValue): void {
    if ($key) {
        global $constnames_list;
        $constnames_list[] = $key;
        $override = getenv($key);
        if ($override) {
            $defaultValue = $override;
            if ($key === 'DEBUG') {
                $defaultValue = boolval($override);
            }
        }
        define($key, $defaultValue);
    }
}
?>
