<?php /*

 Composr
 Copyright (c) ocProducts, 2004-2018

 See text/EN/licence.txt for full licensing information.


 NOTE TO PROGRAMMERS:
   Do not edit this file. If you need to make changes, save your changed file to the appropriate *_custom folder
   **** If you ignore this advice, then your website upgrades (e.g. for bug fixes) will likely kill your changes ****

*/

/**
 * @license    http://opensource.org/licenses/cpal_1.0 Common Public Attribution License
 * @copyright  ocProducts Ltd
 * @package    core
 */

/**
 * Find available mime types.
 *
 * @param  boolean $as_admin Whether there are admin privileges, to render dangerous media types (client-side risk only)
 * @return array The MIME types
 */
function get_mime_types($as_admin)
{
    $mime_types = array(
        // Open office
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'odp' => 'application/vnd.oasis.opendocument.presentation',
        'odg' => 'application/vnd.oasis.opendocument.graphics',
        'odi' => 'application/vnd.oasis.opendocument.image',
        'odb' => 'application/vnd.oasis.opendocument.database',
        'odc' => 'application/vnd.oasis.opendocument.chart',

        // Microsoft office
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'dot' => 'application/msword',
        'dotx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'mdb' => 'application/x-msaccess',
        'pub' => 'application/x-mspublisher',
        'vsd' => 'application/vnd.visio',

        // iWork
        'pages' => 'application/x-iwork-pages-sffpages',
        'numbers' => 'application/x-iwork-pages-sffnumbers',
        'keynote' => 'application/x-iwork-pages-sffkey',

        // Simplified document formats
        '1st' => 'text/plain',
        'txt' => 'text/plain',
        'log' => 'text/plain',
        '' => 'text/plain', // No file type implies a plain text file, e.g. README
        'csv' => 'text/csv',

        // Text formats to show as plain text
        'ini' => 'text/plain',
        'diff' => 'text/plain',
        'patch' => 'text/plain',
        'tpl' => 'text/plain',
        'sql' => 'text/plain',
        'eml' => 'text/plain',

        // Documents
        'pdf' => 'application/pdf',
        'rtf' => 'text/rtf',
        'ps' => 'application/postscript',

        // Web documents
        'html' => $as_admin ? 'text/html' : 'application/octet-stream',
        'htm' => $as_admin ? 'text/html' : 'application/octet-stream',
        'js' => $as_admin ? 'application/javascript' : 'application/octet-stream',
        'json' => $as_admin ? 'application/json' : 'application/octet-stream',
        'css' => $as_admin ? 'text/css' : 'application/octet-stream',
        'xsd' => $as_admin ? 'text/xml' : 'application/octet-stream',
        'xsl' => $as_admin ? 'text/xsl' : 'application/octet-stream',
        'xml' => $as_admin ? 'text/xml' : 'application/octet-stream',
        'rss' => $as_admin ? 'application/rss+xml' : 'application/octet-stream',
        'atom' => $as_admin ? 'application/atom+xml' : 'application/octet-stream',

        // Images
        'png' => 'image/png',
        'gif' => 'image/gif',
        'jpg' => 'image/jpeg',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'psd' => 'image/x-photoshop',
        // 'ai' has no known mime type
        'webp' => 'image/webp',
        'svg' => $as_admin ? 'image/svg+xml' : 'application/octet-stream',

        // Non/badly compressed images
        'bmp' => 'image/x-MS-bmp',
        'tga' => 'image/x-targa',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'ico' => 'image/vnd.microsoft.icon',
        'cur' => 'image/x-win-bitmap',

        // Movies
        'avi' => 'video/mpeg', //'video/x-ms-asf' works with the plugin on Windows Firefox but nothing else, //'video/x-msvideo' is correct but does not get recognised by Microsoft Firefox WMV plugin and confuses RealMedia Player if it sees data transferred under that mime type,
        'mp2' => 'video/mpeg',
        'mpv2' => 'video/mpeg',
        'm2v' => 'video/mpeg',
        'mpa' => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'mpe' => 'video/mpeg',
        '3g2' => 'video/3gpp',
        '3gp' => 'video/3gpp',
        '3gp2' => 'video/3gpp',
        '3gpp' => 'video/3gpp',
        '3p' => 'video/3gpp',
        'f4v' => 'video/mp4',
        'mp4' => 'video/mp4',
        'm4v' => 'video/mp4',
        'mov' => 'video/mp4',
        'qt' => 'video/mp4',
        'mpeg' => 'video/mpeg',
        'ogv' => 'video/ogg',
        'webm' => 'video/webm',
        // Proprietary movie formats
        'wmv' => 'video/x-ms-wmv',
        'ram' => 'audio/x-pn-realaudio',
        'rm' => 'audio/x-pn-realaudio',
        'asf' => 'video/x-ms-asf',

        // Audio
        'mp3' => 'audio/mpeg',
        'aac' => 'audio/mpeg',
        'wav' => 'audio/x-wav',
        'mid' => 'audio/midi',
        'aif' => 'audio/x-aiff',
        'aifc' => 'audio/x-aiff',
        'aiff' => 'audio/x-aiff',
        'ogg' => 'audio/ogg',
        'weba' => 'audio/webm',
        // Proprietary audio formats
        'wma' => 'audio/x-ms-wma',
        'ra' => 'audio/x-pn-realaudio-plugin',

        // Fonts
        'ttf' => 'font/ttf',
        'woff' => 'font/woff',
        'otf' => 'font/otf',

        // Archives / Compression
        'rar' => 'application/x-rar-compressed',
        'tar' => 'application/x-tar',
        'zip' => 'application/zip',
        'gz' => 'application/gzip',
        'tgz' => 'application/gzip',
        'bz2' => 'application/x-bzip2',
        '7z' => 'application/x-7z-compressed',

        // Misc
        'torrent' => 'application/x-bittorrent',
        'ics' => 'text/calendar',

        // Misc data
        'dat' => 'application/octet-stream',
        'dmg' => 'application/octet-stream',
        'exe' => 'application/octet-stream',
        'iso' => 'application/octet-stream',
        'php' => 'application/octet-stream',
    );

    return $mime_types;
}

/**
 * Find the mime type for the given file extension. It does not take into account whether the file type has been white-listed or not, and returns a binary download mime type for any unknown extensions.
 *
 * @param  string $extension The file extension (no dot)
 * @param  boolean $as_admin Whether there are admin privileges, to render dangerous media types (client-side risk only)
 * @return string The MIME type
 */
function get_mime_type($extension, $as_admin)
{
    $extension = strtolower($extension);

    $mime_types = get_mime_types($as_admin);

    if (array_key_exists($extension, $mime_types)) {
        return $mime_types[$extension];
    }

    return 'application/octet-stream';
}
