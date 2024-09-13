// for footer.html
function humanFileSize(size) {
  const arr0 = ['B', 'kB', 'MB', 'GB', 'TB'];
  var i = size == 0 ? 0 : Math.floor(Math.log(size) / Math.log(1024));
  return (size / Math.pow(1024, i)).toFixed(2) * 1 + ' ' + arr0[i];
}
$(document).ready(function () {
  const DIRNAME_PREFIX = '_'
  const ARCHIVE_EXT = 'zip'
  const STORAGE_DIR = '.archived_dirs'
  const ALLOWED_DEEP = 2;
  const $output = $('#archive_link')
  const l = window.location;
  const arr = l.pathname.split('/'); // "/" -> ["",""];  "/dir/" -> ["","dir",""]
  if (arr.length - 1 >= ALLOWED_DEEP) {
    $output.text('update...')
    const filename = DIRNAME_PREFIX + arr[arr.length - 2] + "." + ARCHIVE_EXT;
    const fileUrl = l.protocol + '//' + l.host + '/' + STORAGE_DIR + l.pathname
      + filename;
    // console.log(fileUrl);
    $.ajax({
        type: 'HEAD',
        url: fileUrl,
        success: function(data, textStatus, jqXHR){
          fileSize = jqXHR.getResponseHeader('Content-Length');
          // console.log(fileUrl + ' [FileSize]: ' + fileSize);
          $output.attr("href", fileUrl);
          $output.text(decodeURI(filename) + '  ' + humanFileSize(fileSize));
          $("#df_new_cb").css("display","none");
          $("#df_new").css("display","none");
        },
        error:function (xhr, ajaxOptions, thrownError){
          $output.text('')
          $("#df_del").css("display","none");
          $("#df_get").css("display","none");
        }
    });
  } else {
    $("#zipdir_form").css("display","none");
  }
})
