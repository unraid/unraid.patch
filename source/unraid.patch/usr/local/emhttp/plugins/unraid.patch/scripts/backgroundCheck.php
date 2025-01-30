#!/usr/bin/php
<?
/* Copyright 2025, Lime Technology
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: "/usr/local/emhttp";

require_once "/usr/local/emhttp/plugins/unraid.patch/include/paths.php";

$unraidVersion = parse_ini_file($paths['version']);

$patches = exec("/usr/bin/php $docroot".$paths['exec']." check background");

if (trim($patches) == "No patches found" || trim($patches) == "none" || ! is_dir($paths['flash'].$unraidVersion['version']) ) {
  @unlink($paths['bannerNotify']);
  exit();
}

touch($paths['bannerNotify']);
exec("/usr/local/emhttp/plugins/dynamix/scripts/notify -e 'Critical Update' -s 'Critical Update Available' -d 'Critical Updates Are Available For Your Unraid Server' -i 'alert' -m 'New Criticial Updates are available for your server.  You should visit Tools / Unraid Patch to install' -l '/Tools/unraidPatch'");

?>