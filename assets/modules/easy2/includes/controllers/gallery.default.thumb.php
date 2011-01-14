<?php

// harden it
if (!@require_once('../../../../../manager/includes/protect.inc.php'))
    die('Go away!');

// initialize the variables prior to grabbing the config file
$database_type = "";
$database_server = "";
$database_user = "";
$database_password = "";
$dbase = "";
$table_prefix = "";
$base_url = "";
$base_path = "";

// MODx config
if (!@require_once '../../../../../manager/includes/config.inc.php')
    die('Unable to include the MODx\'s config file');

mysql_connect($database_server, $database_user, $database_password) or die('MySQL connect error');
mysql_select_db(str_replace('`', '', $dbase));
@mysql_query("{$database_connection_method} {$database_connection_charset}");

// e2g's configs
$q = mysql_query('SELECT * FROM ' . $table_prefix . 'easy2_configs');
if (!$q)
    die(__FILE__ . ': MySQL query error for configs');
else {
    while ($row = mysql_fetch_array($q)) {
        $e2g[$row['cfg_key']] = $row['cfg_val'];
    }
}

// initiate a new document parser
include ('../../../../../manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;
$modx->getSettings();

// Easy 2 Gallery module path
define('E2G_MODULE_PATH', MODX_BASE_PATH . 'assets/modules/easy2/');
// Easy 2 Gallery module URL
define('E2G_MODULE_URL', MODX_SITE_URL . '../../');

require_once E2G_MODULE_PATH . 'includes/utf8/utf8.php';

// initiate e2g's public module
include ('../configs/params.module.easy2gallery.php');
include ('../models/e2g.public.class.php'); //extending
include ('../models/e2g.module.class.php');

// LANGUAGE
if (file_exists(realpath('../langs/' . $modx->config['manager_language'] . '.inc.php'))) {
    include '../langs/' . $modx->config['manager_language'] . '.inc.php';

    // if there is a blank language parameter, english will fill it as the default.
    foreach ($e2g_lang[$modx->config['manager_language']] as $olk => $olv) {
        $oldLangKey[$olk] = $olk; // other languages
        $oldLangVal[$olk] = $olv;
    }

    include '../langs/english.inc.php';
    foreach ($e2g_lang['english'] as $enk => $env) {
        if (!isset($oldLangKey[$enk])) {
            $e2g_lang[$modx->config['manager_language']][$enk] = $env;
        }
    }

    $lng = $e2g_lang[$modx->config['manager_language']];
} else {
    include '../langs/english.inc.php';
    $lng = $e2g_lang['english'];
}

$e2gMod = new E2gMod($modx, $e2gModCfg, $e2g, $lng);

$getRequests = $e2gMod->sanitizedGets($_GET);
if (empty($getRequests)) {
    die('Request is empty');
}

$index = $e2gModCfg['index'];
$index = str_replace('assets/modules/easy2/includes/controllers/', '', $index);

$rootDir = '../../../../../' . $e2g['dir'];
$pidPath = $e2gMod->getPath($getRequests['pid']);
$gdir = $e2g['dir'] . $getRequests['path'];

if ($getRequests['path'] == $pidPath) {
    ####################################################################
    ####                      MySQL Dir list                        ####
    ####################################################################
    $selectDirs = 'SELECT * FROM ' . $modx->db->config['table_prefix'] . 'easy2_dirs' . ' '
            . 'WHERE parent_id = ' . $getRequests['pid'] . ' '
            . 'ORDER BY cat_name ASC'
    ;
    $querySelectDirs = mysql_query($selectDirs);
    if (!$querySelectDirs) {
        $msg = __LINE__ . ' : #' . mysql_errno() . ' ' . mysql_error() . '<br />' . $selectDirs;
        die($msg);
    }

    $rows = array(); // for return
    $mdirs = array();
    while ($l = mysql_fetch_array($querySelectDirs, MYSQL_ASSOC)) {
        $mdirs[$l['cat_name']] = $l;
    }
    mysql_free_result($querySelectDirs);


    ####################################################################
    ####                      MySQL File list                       ####
    ####################################################################
    $selectFiles = 'SELECT * FROM ' . $modx->db->config['table_prefix'] . 'easy2_files '
            . 'WHERE dir_id = ' . $getRequests['pid'];
    $querySelectFiles = mysql_query($selectFiles);
    if (!$querySelectFiles) {
        $msg = __LINE__ . ' : #' . mysql_errno() . ' ' . mysql_error() . '<br />' . $selectFiles;
        die($msg);
    }
    $mfiles = array();
    while ($l = mysql_fetch_array($querySelectFiles, MYSQL_ASSOC)) {
        $mfiles[$l['filename']] = $l;
    }
    mysql_free_result($querySelectFiles);
}

$rowClass = array(' class="gridAltItem"', ' class="gridItem"');
$rowNum = 0;

//******************************************************************/
//***************** FOLDERS/DIRECTORIES/GALLERIES ******************/
//******************************************************************/
$scanDirs = @glob('../../../../../' . $e2gMod->e2gDecode($gdir) . '*');
if (FALSE !== $scanDirs) {
    if (is_array($scanDirs))
        natsort($scanDirs);

    foreach ($scanDirs as $scanPath) {
        ob_start();
        if ($e2gMod->validFolder($scanPath)) {
            $dirName = $e2gMod->basenameSafe($scanPath);
            $dirName = $e2gMod->e2gEncode($dirName);
            $dirName = urldecode($dirName);
            if ($dirName == '_thumbnails')
                continue;

            $dirStyledName = $dirName; // will be overridden for styling below
            $dirNameUrlDecodeDirname = urldecode($dirName);
            $dirPathRawUrlEncoded = str_replace('%2F', '/', rawurlencode($gdir . $dirName));
            $dirCountFiles = $e2gMod->countFiles($scanPath);

            #################### Template placeholders #####################

            $dirAlias = '';
            $dirTag = '';
            $dirTagLinks = '';
            $dirCheckBox = '';
            $dirAttributes = '';
            $dirAttributeIcons = '';
            $dirHref = '';
            $dirIcon = '
                <img src="' . E2G_MODULE_URL . 'includes/tpl/icons/folder.png"
                    width="16" height="16" border="0" alt="folder" title="' . $lng['dir'] . '" />
                ';
            if (!empty($mdirs[$dirName]['cat_redirect_link'])) {
                $dirIcon .= '
                <img src="' . E2G_MODULE_URL . 'includes/tpl/icons/link.png" width="16"
                    height="16" alt="link" title="' . $lng['redirect_link'] . ': ' . $mdirs[$dirName]['cat_redirect_link'] . '" border="0" />
                        ';
            }
            $dirButtons = '';

            if (isset($mdirs[$dirName])) {
                $dirId = $mdirs[$dirName]['cat_id'];
                $dirAlias = $mdirs[$dirName]['cat_alias'];
                $dirTag = $mdirs[$dirName]['cat_tag'];
                $dirTagLinks = $e2gMod->createTagLinks($dirTag);
                $dirTime = $e2gMod->getTime($mdirs[$dirName]['date_added'], $mdirs[$dirName]['last_modified'], $scanPath);

                if (!isset($getRequests['getpath'])) {
                    // Checkbox
                    $dirCheckBox = '
                <input name="dir[' . $dirId . ']" value="' . $dirPathRawUrlEncoded . '" type="checkbox" style="border:0;padding:0" />
                ';
                }
                if ($mdirs[$dirName]['cat_visible'] == '1') {
                    $dirStyledName = '<b>' . $dirName . '</b>';
                    $dirHref = $index . '&amp;pid=' . $mdirs[$dirName]['cat_id'];
                    $dirButtons = $e2gMod->actionIcon('hide_dir', array(
                                'act' => 'hide_dir'
                                , 'dir_id' => $dirId
                                , 'pid' => $getRequests['pid']
                                    ), null, $index);
                } else {
                    $dirStyledName = '<i>' . $dirName . '</i>';
                    $dirAttributes = '<i>(' . $lng['hidden'] . ')</i>';
                    $dirAttributeIcons = '
                <a href="' . $index . '&amp;act=show_dir&amp;dir_id=' . $dirId . '&amp;name=' . $dirName . '&amp;pid=' . $getRequests['pid'] . '">
                    <img src="' . E2G_MODULE_URL . 'includes/tpl/icons/eye_closed.png" width="16"
                        height="16" alt="' . $lng['hidden'] . '" title="' . $lng['hidden'] . '" border="0" />
                </a>
                ';
                    $dirHref = $index . '&amp;pid=' . $mdirs[$dirName]['cat_id'];
                    $dirButtons = $e2gMod->actionIcon('show_dir', array(
                                'act' => 'show_dir'
                                , 'dir_id' => $dirId
                                , 'pid' => $getRequests['pid']
                                    ), null, $index);
                }
                // edit dir
                $dirButtons .= $e2gMod->actionIcon('edit_dir', array(
                            'page' => 'edit_dir'
                            , 'dir_id' => $dirId
                            , 'pid' => $getRequests['pid']
                                ), null, $index);
                // unset this to leave the deleted dirs from file system.
                unset($mdirs[$dirName]);
            } // if (isset($mdirs[$dirName]))
            else {
                /**
                 * Existing dir in file system, but has not yet inserted into database
                 */
                if (isset($getRequests['getpath'])) {
                    // Checkbox
                    $dirCheckBox = '
                    <input name="dir[d' . $rowNum . ']" value="' . $dirPathRawUrlEncoded . '" type="checkbox" style="border:0;padding:0" />
                    ';
                }
                if (isset($getRequests['getpath']) && isset($getRequests['pid']) || $getRequests['pid'] === 1) {
                    // Checkbox
                    $dirCheckBox = '
                    <input name="dir[d' . $rowNum . ']" value="' . $dirPathRawUrlEncoded . '" type="checkbox" style="border:0;padding:0" />
                    ';
                    // add dir
                    $dirButtons .= $e2gMod->actionIcon('add_dir', array(
                                'act' => 'add_dir'
                                , 'dir_path' => $dirPathRawUrlEncoded
                                , 'pid' => $getRequests['pid']
                                    ), null, $index);
                }
                $dirTime = date($e2gMod->e2g['mod_date_format'], filemtime($scanPath));
                clearstatcache();
                $dirStyledName = '<b style="color:gray">' . $dirName . '</b>';
                $dirAttributes = '<i>(' . $lng['new'] . ')</i>';
                $dirHref = $index . '&amp;path=' . (!empty($getRequests['getpath']) ? $getRequests['getpath'] : '') . $dirName;
                $dirId = NULL;
                $dirIcon = '
                <img src="' . E2G_MODULE_URL . 'includes/tpl/icons/folder_add.png" width="16"
                    height="16" alt="' . $lng['add_to_db'] . '" border="0" />
                    ';
            }

            if (!empty($dirId)) {
                $dirButtons .= $e2gMod->actionIcon('delete_dir', array(
                            'act' => 'delete_dir'
                            , 'dir_path' => $dirPathRawUrlEncoded
                            , 'dir_id' => $dirId
                                ), 'onclick="return confirmDeleteFolder();"', $index);
            } else {
                $dirButtons .= $e2gMod->actionIcon('delete_dir', array(
                            'act' => 'delete_dir'
                            , 'dir_path' => $dirPathRawUrlEncoded
                                ), 'onclick="return confirmDeleteFolder();"', $index);
            }

            $dirPhRow['thumb.rowNum'] = $rowNum;
            $dirPhRow['thumb.rowClass'] = $rowClass[$rowNum % 2];
            $dirPhRow['thumb.checkBox'] = $dirCheckBox;
            $dirPhRow['thumb.id'] = $dirId;
            $dirPhRow['thumb.gid'] = empty($dirId) ? '' : '[id: ' . $dirId . ']';
            $dirPhRow['thumb.name'] = $dirName;
            $dirPhRow['thumb.styledName'] = $dirStyledName;
            $dirPhRow['thumb.path'] = $scanPath;
            $dirPhRow['thumb.pathRawUrlEncoded'] = $dirPathRawUrlEncoded;
            $dirPhRow['thumb.alias'] = $dirAlias;
            $dirPhRow['thumb.title'] = ( trim($dirAlias) != '' ? $dirAlias : $dirName);
            $dirPhRow['thumb.tagLinks'] = $dirTagLinks;
            $dirPhRow['thumb.time'] = $dirTime;
            $dirPhRow['thumb.count'] = $dirCountFiles;
            $dirPhRow['thumb.attributes'] = $dirAttributes;
            $dirPhRow['thumb.attributeIcons'] = $dirAttributeIcons;
            $dirPhRow['thumb.href'] = $dirHref;
            $dirPhRow['thumb.buttons'] = $dirButtons;
            $dirPhRow['thumb.icon'] = $dirIcon;
            $dirPhRow['thumb.size'] = '---';
            $dirPhRow['thumb.w'] = '---';
            $dirPhRow['thumb.h'] = '---';
            $dirPhRow['thumb.mod_w'] = $e2g['mod_w'];
            $dirPhRow['thumb.mod_h'] = $e2g['mod_h'];
            $dirPhRow['thumb.mod_thq'] = $e2g['mod_thq'];

            ###################################################################
            $dirPhRow['thumb.src'] = '';
            $dirPhRow['thumb.thumb'] = '';
            if (!empty($dirPhRow['thumb.id'])) {
                // search image for subdir
                $folderImgInfos = $e2gMod->folderImg($dirPhRow['thumb.id'], $rootDir);

                // if there is an empty folder, or invalid content
                if ($folderImgInfos === FALSE) {
                    $imgPreview = E2G_MODULE_URL . 'preview.easy2gallery.php?path='
                            . $dirPhRow['thumb.pathRawUrlEncoded']
                            . '&amp;mod_w=' . $dirPhRow['thumb.mod_w']
                            . '&amp;mod_h=' . $dirPhRow['thumb.mod_h']
                            . '&amp;text=' . $lng['empty']
                    ;
                    $dirPhRow['thumb.thumb'] = '
            <a href="' . $dirPhRow['thumb.href'] . '">
                <img src="' . $imgPreview
                            . '" alt="' . $dirPhRow['thumb.path'] . $dirPhRow['thumb.name']
                            . '" title="' . $dirPhRow['thumb.title']
                            . '" width="' . $dirPhRow['thumb.mod_w']
                            . '" height="' . $dirPhRow['thumb.mod_h']
                            . '" />
            </a>
';
                } else {
                    // path to subdir's thumbnail
                    $pathToImg = $e2gMod->getPath($folderImgInfos['dir_id']);
                    $imgShaper = $e2gMod->imgShaper($rootDir
                                    , $pathToImg . $folderImgInfos['filename']
                                    , $dirPhRow['thumb.mod_w']
                                    , $dirPhRow['thumb.mod_w']
                                    , $dirPhRow['thumb.mod_thq']);
                    if ($imgShaper === FALSE) {
                        // folder has been deleted
                        $imgPreview = E2G_MODULE_URL . 'preview.easy2gallery.php?path='
                                . $dirPhRow['thumb.pathRawUrlEncoded']
                                . '&amp;mod_w=' . $dirPhRow['thumb.mod_w']
                                . '&amp;mod_h=' . $dirPhRow['thumb.mod_h']
                                . '&amp;text=' . $lng['deleted']
                        ;
                        $imgSrc = E2G_MODULE_URL . 'preview.easy2gallery.php?path='
                                . $dirPhRow['thumb.pathRawUrlEncoded']
                                . '&amp;mod_w=300'
                                . '&amp;mod_h=100'
                                . '&amp;text=' . $lng['deleted']
                                . '&amp;th=5';
                        $dirPhRow['thumb.thumb'] = '
            <a href="' . $imgSrc
                                . '" class="highslide" onclick="return hs.expand(this)"'
                                . ' title="' . $dirPhRow['thumb.name'] . ' ' . $dirPhRow['thumb.gid'] . ' ' . $dirPhRow['thumb.attributes']
                                . '">
                <img src="' . $imgPreview
                                . '" alt="' . $dirPhRow['thumb.path'] . $dirPhRow['thumb.name']
                                . '" title="' . $dirPhRow['thumb.title']
                                . '" width="' . $dirPhRow['thumb.mod_w']
                                . '" height="' . $dirPhRow['thumb.mod_h']
                                . '" />
            </a>
';
                        unset($imgPreview);
                    } else {
                        /**
                         * $imgShaper returns the URL to the image
                         */
                        $dirPhRow['thumb.src'] = $imgShaper;

                        /**
                         * @todo: AJAX call to the image
                         */
                        $dirPhRow['thumb.thumb'] = '
            <a href="' . $dirPhRow['thumb.href'] . '">
                <img src="' . '../' . str_replace('../', '', $imgShaper)
                                . '" alt="' . $dirPhRow['thumb.name']
                                . '" title="' . $dirPhRow['thumb.title']
                                . '" width="' . $dirPhRow['thumb.mod_w']
                                . '" height="' . $dirPhRow['thumb.mod_h']
                                . '" class="thumb-dir" />
                <span class="preloader" id="thumbDir_' . $dirPhRow['thumb.rowNum'] . '">
                    <script type="text/javascript">
                        thumbDir(\'' . '../' . str_replace('../', '', $imgShaper) . '\','
                                . $dirPhRow['thumb.rowNum'] . ');
                    </script>
                </span>
            </a>
';
                        unset($imgShaper);
                    }
                }
            } else {
                $imgPreview = E2G_MODULE_URL . 'preview.easy2gallery.php?path='
                        . $dirPhRow['thumb.pathRawUrlEncoded']
                        . '&amp;mod_w=' . $dirPhRow['thumb.mod_w']
                        . '&amp;mod_h=' . $dirPhRow['thumb.mod_h']
                        . '&amp;text=' . $lng['new'];
                $dirPhRow['thumb.thumb'] = '
            <a href="' . $dirPhRow['thumb.href'] . '">
                <img src="' . $imgPreview
                        . '" alt="' . $dirPhRow['thumb.name']
                        . '" title="' . $dirPhRow['thumb.title']
                        . '" width="' . $dirPhRow['thumb.mod_w']
                        . '" height="' . $dirPhRow['thumb.mod_h']
                        . '" />
            </a>
';
                unset($imgPreview);
            }

            echo $e2gMod->filler($e2gMod->getTpl('file_thumb_dir_tpl'), $dirPhRow);
        } // if ($e2gMod->validFolder($scanPath))
        ############################# DIR LIST ENDS ############################
        //******************************************************************/
        //************* FILE content for the current directory *************/
        //******************************************************************/

        if ($e2gMod->validFile($scanPath)) {

// TODO: Clean up this UTF-8 mess when adding file
            $filename = $e2gMod->basenameSafe($scanPath);
            $filename = $e2gMod->e2gEncode($filename);
            $fileStyledName = $filename; // will be overridden for styling below
            $fileNameUrlDecodeFilename = urldecode($filename);
            $filePathRawUrlEncoded = str_replace('%2F', '/', rawurlencode($gdir . $filename));
            #################### Template placeholders #####################

            $fileAlias = '';
            $fileTag = '';
            $fileTagLinks = '';
            $fileCheckBox = '';
            $fileAttributes = '';
            $fileAttributeIcons = '';
            $fileIcon = '
                <img src="' . E2G_MODULE_URL . 'includes/tpl/icons/picture.png" width="16" height="16" border="0" alt="" />
                ';
            if (!empty($mfiles[$filename]['redirect_link'])) {
                $fileIcon .= '
                <img src="' . E2G_MODULE_URL . 'includes/tpl/icons/link.png" width="16"
                    height="16" alt="link" title="' . $lng['redirect_link'] . ': ' . $mfiles[$filename]['redirect_link'] . '" border="0" />
                        ';
            }
            $fileButtons = '';

            if (isset($mfiles[$filename])) {
                $fileId = $mfiles[$filename]['id'];
                $fileAlias = $mfiles[$filename]['alias'];
                $fileTagLinks = $e2gMod->createTagLinks($mfiles[$filename]['tag']);
                if (!isset($getRequests['getpath'])) {
                    // Checkbox
                    $fileCheckBox = '
                <input name="im[' . $fileId . ']" value="' . $filePathRawUrlEncoded . '" type="checkbox" style="border:0;padding:0" />
                ';
                }
                $tag = $mfiles[$filename]['tag'];
                $fileSize = round($mfiles[$filename]['size'] / 1024);
                $width = $mfiles[$filename]['width'];
                $height = $mfiles[$filename]['height'];
                $fileTime = $e2gMod->getTime($mfiles[$filename]['date_added'], $mfiles[$filename]['last_modified'], $scanPath);

                if ($mfiles[$filename]['status'] == '1') {
                    $fileButtons = $e2gMod->actionIcon('hide_file', array(
                                'act' => 'hide_file'
                                , 'file_id' => $fileId
                                , 'pid' => $getRequests['pid']
                                    ), null, $index);
                } else {
                    $fileStyledName = '<i>' . $filename . '</i>';
                    $fileAttributes = '<i>(' . $lng['hidden'] . ')</i>';
                    $fileAttributeIcons = $e2gMod->actionIcon('show_file', array(
                                'act' => 'show_file'
                                , 'file_id' => $fileId
                                , 'pid' => $getRequests['pid']
                                    ), null, $index);
                    $fileButtons = $e2gMod->actionIcon('show_file', array(
                                'act' => 'show_file'
                                , 'file_id' => $fileId
                                , 'pid' => $getRequests['pid']
                                    ), null, $index);
                }
                $fileButtons .= $e2gMod->actionIcon('comments', array(
                            'page' => 'comments'
                            , 'file_id' => $fileId
                            , 'pid' => $getRequests['pid']
                                ), null, $index);

                $fileButtons .= $e2gMod->actionIcon('edit_file', array(
                            'page' => 'edit_file'
                            , 'file_id' => $fileId
                            , 'pid' => $getRequests['pid']
                                ), null, $index);

                unset($mfiles[$filename]);
            } else {
                /**
                 * Existed files in file system, but not yet inserted into database
                 */
                if (!isset($getRequests['getpath'])) {
                    // Checkbox
                    $fileCheckBox = '
                <input name="im[f' . $rowNum . ']" value="im[f' . $rowNum . ']" type="checkbox" style="border:0;padding:0" />
                ';
                }
                $fileTime = date($e2gMod->e2g['mod_date_format'], filemtime($scanPath));
                $fileStyledName = '<span style="color:gray"><b>' . $filename . '</b></span>';
                $fileAttributes = '<i>(' . $lng['new'] . ')</i>';
                $fileId = NULL;
                $fileIcon = '
                <img src="' . E2G_MODULE_URL . 'includes/tpl/icons/picture_add.png" width="16" height="16" border="0" alt="" />
                ';
                $fileAttributeIcons = '';
                if (empty($path['string'])) {
                    // add file
                    $fileButtons .= $e2gMod->actionIcon('add_file', array(
                                'act' => 'add_file'
                                , 'file_path' => $filePathRawUrlEncoded
                                , 'pid' => $getRequests['pid']
                                    ), null, $index);
                } else {
                    $fileButtons = '';
                }
                $fileSize = round(filesize($scanPath) / 1024);
                list($width, $height) = @getimagesize($scanPath);
            }

            $fileButtons .= $e2gMod->actionIcon('delete_file', array(
                        'act' => 'delete_file'
                        , 'pid' => $getRequests['pid']
                        , 'file_id' => $fileId
                        , 'file_path' => $filePathRawUrlEncoded
                            ), 'onclick="return confirmDelete();"', $index);

            $filePhRow['thumb.rowNum'] = $rowNum;
            $filePhRow['thumb.rowClass'] = $rowClass[$rowNum % 2];
            $filePhRow['thumb.checkBox'] = $fileCheckBox;
            $filePhRow['thumb.dirId'] = $getRequests['pid'];
            $filePhRow['thumb.id'] = $fileId;
            $filePhRow['thumb.fid'] = empty($fileId) ? '' : '[id:' . $fileId . ']';
            $filePhRow['thumb.name'] = $filename;
            $filePhRow['thumb.styledName'] = $fileStyledName;
            $filePhRow['thumb.alias'] = $fileAlias;
            $filePhRow['thumb.title'] = ( trim($fileAlias) != '' ? $fileAlias : $filename);
            $filePhRow['thumb.tagLinks'] = $fileTagLinks;
//                $filePhRow['thumb.path'] = '../' . $gdir;
            $filePhRow['thumb.path'] = $rootDir;
            $filePhRow['thumb.pathRawUrlEncoded'] = $filePathRawUrlEncoded;
            $filePhRow['thumb.time'] = $fileTime;
            $filePhRow['thumb.attributes'] = $fileAttributes;
            $filePhRow['thumb.attributeIcons'] = $fileAttributeIcons;
            $filePhRow['thumb.buttons'] = $fileButtons;
            $filePhRow['thumb.icon'] = $fileIcon;
            $filePhRow['thumb.size'] = $fileSize;
            $filePhRow['thumb.w'] = $width;
            $filePhRow['thumb.h'] = $height;
            $filePhRow['thumb.mod_w'] = $e2g['mod_w'];
            $filePhRow['thumb.mod_h'] = $e2g['mod_h'];
            $filePhRow['thumb.mod_thq'] = $e2g['mod_thq'];

            ####################################################################
            $filePhRow['thumb.link'] = '';

            $filePhRow['thumb.src'] = '';
            $filePhRow['thumb.thumb'] = '';
            if (!empty($filePhRow['thumb.id'])) {
                // path to subdir's thumbnail
                $pathToImg = $e2gMod->getPath($filePhRow['thumb.dirId']);
                $imgShaper = $e2gMod->imgShaper($rootDir
                                , $pathToImg . $filePhRow['thumb.name']
                                , $filePhRow['thumb.mod_w']
                                , $filePhRow['thumb.mod_w']
                                , $filePhRow['thumb.mod_thq']
                );

                // if there is an invalid content
                if ($imgShaper === FALSE) {
                    $imgPreview = E2G_MODULE_URL . 'preview.easy2gallery.php?path='
                            . '&amp;mod_w=' . $filePhRow['thumb.mod_w']
                            . '&amp;mod_h=' . $filePhRow['thumb.mod_h']
                            . '&amp;text=' . __LINE__ . '-FALSE'
                    ;
                    $filePhRow['thumb.thumb'] = '
                <a href="' . $imgPreview
                            . '" class="highslide" onclick="return hs.expand(this)"'
                            . ' title="' . $filePhRow['thumb.name'] . ' ' . $filePhRow['thumb.fid'] . ' ' . $filePhRow['thumb.attributes']
                            . '">
                    <img src="' . $imgPreview
                            . '" alt="' . $filePhRow['thumb.path'] . $filePhRow['thumb.name']
                            . '" title="' . $filePhRow['thumb.title']
                            . '" width="' . $filePhRow['thumb.mod_w']
                            . '" height="' . $filePhRow['thumb.mod_h']
                            . '" />
                </a>
    ';
                } else {
                    $filePhRow['thumb.src'] = $imgShaper;
                    $filePhRow['thumb.thumb'] = '
            <a href="../' . $filePhRow['thumb.pathRawUrlEncoded']
                            . '" class="highslide" onclick="return hs.expand(this, { objectType: \'ajax\'})" '
                            . 'title="' . $filePhRow['thumb.name'] . ' ' . $filePhRow['thumb.fid'] . ' ' . $filePhRow['thumb.attributes']
                            . '">
                <img src="' . '../' . str_replace('../', '', $imgShaper)
                            . '" alt="' . $filePhRow['thumb.pathRawUrlEncoded'] . $filePhRow['thumb.name']
                            . '" title="' . $filePhRow['thumb.title']
                            . '" width="' . $filePhRow['thumb.mod_w']
                            . '" height="' . $filePhRow['thumb.mod_h']
                            . '" class="thumb-file" />
            </a>
';
                }
                unset($imgShaper);
            } else {
                // new image
                $imgPreview = E2G_MODULE_URL . 'preview.easy2gallery.php?path='
                        . $filePhRow['thumb.pathRawUrlEncoded']
                        . '&amp;mod_w=' . $filePhRow['thumb.mod_w']
                        . '&amp;mod_h=' . $filePhRow['thumb.mod_h']
                        . '&amp;text=' . __LINE__ . '-'
                ;
                $filePhRow['thumb.thumb'] = '
            <a href="' . $filePhRow['thumb.path'] . $filePhRow['thumb.name']
                        . '" class="highslide" onclick="return hs.expand(this)"'
                        . ' title="' . $filePhRow['thumb.name'] . ' ' . $filePhRow['thumb.fid'] . ' ' . $filePhRow['thumb.attributes']
                        . '">
                <img src="' . $imgPreview
                        . '" alt="' . $filePhRow['thumb.path'] . $filePhRow['thumb.name']
                        . '" title="' . $filePhRow['thumb.title']
                        . '" width="' . $filePhRow['thumb.mod_w']
                        . '" height="' . $filePhRow['thumb.mod_h']
                        . '" />
            </a>
';
                unset($imgPreview);
            }

            echo $e2gMod->filler($e2gMod->getTpl('file_thumb_file_tpl'), $filePhRow);
        } // if ($e2gMod->validFile($scanPath))

        ob_flush();
        /**
         * to deal with thousands of pictures, this will make the script
         * sleeps for 10 ms
         */
        usleep(10);

        $rowNum++;
    } // foreach ($dirs as $scanPath)
    ob_end_flush();

    ############################################################################
    ############################################################################
    ############################################################################
    ############################################################################

    /**
     * Deleted dirs from file system, but still exists in database,
     * which have been left from the above unsetting.
     */
    if (isset($mdirs) && count($mdirs) > 0) {
        foreach ($mdirs as $v) {
            $dirPhRow['thumb.rowNum'] = $rowNum;
            $dirPhRow['thumb.rowClass'] = $rowClass[$rowNum % 2];
            $dirPhRow['thumb.checkBox'] = '
                    <input name="dir[' . $v['cat_id'] . ']" value="dir[' . $v['cat_id'] . ']" type="checkbox" style="border:0;padding:0" />
                        ';
            $dirPhRow['thumb.id'] = $v['cat_id'];
            $dirPhRow['thumb.gid'] = '[id: ' . $v['cat_id'] . ']';
            $dirPhRow['thumb.name'] = $v['cat_name'];
            $dirPhRow['thumb.styledName'] = '<b style="color:red;"><u>' . $v['cat_name'] . '</u></b>';
            $dirPhRow['thumb.path'] = '';
            $dirPhRow['thumb.alias'] = $v['cat_alias'];
            $dirPhRow['thumb.title'] = ( trim($v['cat_alias']) != '' ? $v['cat_alias'] : $v['cat_name']);
            $dirPhRow['thumb.tagLinks'] = $e2gMod->createTagLinks($v['cat_tag']);
            $dirPhRow['thumb.time'] = $e2gMod->getTime($v['date_added'], $v['last_modified'], '');
            $dirPhRow['thumb.count'] = intval("0");
            $dirPhRow['thumb.link'] = '<b style="color:red;"><u>' . $v['cat_name'] . '</u></b>';
            $dirPhRow['thumb.attributes'] = '<i>(' . $lng['deleted'] . ')</i>';
            $dirPhRow['thumb.attributeIcons'] = '';

            $dirPhRow['thumb.href'] = '';

            $dirPhRow['thumb.buttons'] = $e2gMod->actionIcon('delete_dir', array(
                        'act' => 'delete_dir'
                        , 'dir_id' => $v['cat_id']
                        , 'pid' => $getRequests['pid']
                            ), 'onclick="return confirmDeleteFolder();"', $index);
            $deletedDirIcon = '
                    <img src="' . E2G_MODULE_URL . 'includes/tpl/icons/folder_delete.png"
                        width="16" height="16" border="0" alt="folder_delete.png" title="' . $lng['deleted'] . '" />
                    ';
            if (!empty($v['cat_redirect_link'])) {
                $deletedDirIcon .= '
                    <img src="' . E2G_MODULE_URL . 'includes/tpl/icons/link.png" width="16"
                        height="16" alt="link" title="' . $lng['redirect_link'] . ': ' . $mdirs[$dirName]['cat_redirect_link'] . '" border="0" />
                            ';
            }
            $dirPhRow['thumb.icon'] = $deletedDirIcon;

            $dirPhRow['thumb.mod_w'] = $e2g['mod_w'];
            $dirPhRow['thumb.mod_h'] = $e2g['mod_h'];
            $dirPhRow['thumb.mod_thq'] = $e2g['mod_thq'];

            ###################################################################
            $imgPreview = E2G_MODULE_URL . 'preview.easy2gallery.php?path='
                    . $dirPhRow['thumb.pathRawUrlEncoded']
                    . '&amp;mod_w=' . $dirPhRow['thumb.mod_w']
                    . '&amp;mod_h=' . $dirPhRow['thumb.mod_h']
                    . '&amp;text=' . $lng['deleted'];
            $imgSrc = E2G_MODULE_URL . 'preview.easy2gallery.php?path='
                    . $dirPhRow['thumb.pathRawUrlEncoded']
                    . '&amp;mod_w=300'
                    . '&amp;mod_h=100'
                    . '&amp;text=' . $lng['deleted']
                    . '&amp;th=5';
            $dirPhRow['thumb.thumb'] = '
            <a href="' . $imgSrc
                    . '" class="highslide" onclick="return hs.expand(this)"'
                    . ' title="' . $dirPhRow['thumb.name'] . ' ' . $dirPhRow['thumb.gid'] . ' ' . $dirPhRow['thumb.attributes']
                    . '">
                <img src="' . $imgPreview
                    . '" alt="' . $dirPhRow['thumb.path']
                    . $dirPhRow['thumb.name']
                    . '" title="' . $dirPhRow['thumb.title']
                    . '" width="' . $dirPhRow['thumb.mod_w']
                    . '" height="' . $dirPhRow['thumb.mod_h']
                    . '" />
            </a>
';

            unset($imgPreview);
            echo $e2gMod->filler($e2gMod->getTpl('file_thumb_dir_tpl'), $dirPhRow);

            $rowNum++;
        } // foreach ($mdirs as $k => $v)
    } // if (isset($mdirs) && count($mdirs) > 0)


    /**
     * Deleted files from file system, but still exists in database
     */
    if (isset($mfiles) && count($mfiles) > 0) {
        foreach ($mfiles as $k => $v) {
            $filePhRow['thumb.rowNum'] = $rowNum;
            $filePhRow['thumb.rowClass'] = $rowClass[$rowNum % 2];
            $filePhRow['thumb.checkBox'] = '
                <input name="im[' . $v['id'] . ']" value="' . $v['id'] . '" type="checkbox" style="border:0;padding:0" />
                ';
            $filePhRow['thumb.dirId'] = $getRequests['pid'];
            $filePhRow['thumb.id'] = $v['id'];
            $filePhRow['thumb.fid'] = '[id:' . $v['id'] . ']';
            $filePhRow['thumb.name'] = $v['filename'];
            $filePhRow['thumb.styledName'] = '<b style="color:red;"><u>' . $v['filename'] . '</u></b>';
            $filePhRow['thumb.alias'] = $v['alias'];
            $filePhRow['thumb.title'] = ( trim($v['alias']) != '' ? $v['alias'] : $v['filename']);
            $filePhRow['thumb.tagLinks'] = $e2gMod->createTagLinks($v['tag']);
            $filePhRow['thumb.path'] = $gdir;
            $filePhRow['thumb.pathRawUrlEncoded'] = str_replace('%2F', '/', rawurlencode($gdir . $v['filename']));
            $filePhRow['thumb.time'] = $e2gMod->getTime($v['date_added'], $v['last_modified'], '');
            $filePhRow['thumb.attributes'] = '<i>(' . $lng['deleted'] . ')</i>';
            $filePhRow['thumb.attributeIcons'] = '';

            $filePhRow['thumb.buttons'] = $e2gMod->actionIcon('delete_file', array(
                        'act' => 'delete_file'
                        , 'file_id' => $v['id']
                        , 'pid' => $getRequests['pid']
                            ), 'onclick="return confirmDelete();"', $index);
            $filePhRow['thumb.icon'] = '
                <img src="' . E2G_MODULE_URL . 'includes/tpl/icons/picture_delete.png" width="16" height="16" border="0" alt="" />
                ';
            $filePhRow['thumb.size'] = round($v['size'] / 1024);
            $filePhRow['thumb.w'] = $v['width'];
            $filePhRow['thumb.h'] = $v['height'];
            $filePhRow['thumb.mod_w'] = $e2g['mod_w'];
            $filePhRow['thumb.mod_h'] = $e2g['mod_h'];
            $filePhRow['thumb.mod_thq'] = $e2g['mod_thq'];

            $filePhRow['thumb.thumb'] = '';

            $imgPreview = E2G_MODULE_URL . 'preview.easy2gallery.php?path='
                    . $filePhRow['thumb.pathRawUrlEncoded']
                    . '&amp;mod_w=' . $filePhRow['thumb.mod_w']
                    . '&amp;mod_h=' . $filePhRow['thumb.mod_h']
                    . '&amp;text=' . $lng['deleted'];
            $imgSrc = E2G_MODULE_URL . 'preview.easy2gallery.php?path='
                    . $filePhRow['thumb.pathRawUrlEncoded']
                    . '&amp;mod_w=300'
                    . '&amp;mod_h=100'
                    . '&amp;text=' . $lng['deleted']
                    . '&amp;th=5';
            $filePhRow['thumb.thumb'] = '
            <a href="' . $imgSrc
                    . '" class="highslide" onclick="return hs.expand(this)"'
                    . ' title="' . $filePhRow['thumb.title'] . ' ' . $filePhRow['thumb.fid']
                    . '">
                <img src="' . $imgPreview
                    . '" alt="' . $filePhRow['thumb.path'] . $filePhRow['thumb.name']
                    . '" title="' . $filePhRow['thumb.title']
                    . '" width="' . $filePhRow['thumb.mod_w']
                    . '" height="' . $filePhRow['thumb.mod_h']
                    . '" />
            </a>
';
            unset($imgPreview);
            echo $e2gMod->filler($e2gMod->getTpl('file_thumb_file_tpl'), $filePhRow);

            $rowNum++;
        } // foreach ($mfiles as $k => $v)
    } // if (isset($mfiles) && count($mfiles) > 0)
} // if (FALSE !== $scanDirs)


exit();