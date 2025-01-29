<?
/* Copyright 2024, Lime Technology
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

$paths['exec'] = "/plugins/unraid.patch/include/exec.php";
$paths['tmp'] = "/tmp/unraid.patch";
$paths['installedUpdates'] = "{$paths['tmp']}/installedUpdates.json";
$paths['flash'] = "/boot/config/plugins/unraid.patch/";
$paths['version'] = "/etc/unraid-version";
$paths['github'] = "https://releases.unraid.net/dl/stable";
$paths['override'] = "{$paths['flash']}patch-url-override.txt";
$paths['overridePatch'] = "{$paths['flash']}overridePatch.json";
$paths['accepted'] = "/boot/config/plugins/unraid.patch/accepted";
$paths['bannerNotify'] = "/tmp/unraid.patch/patchesAvailable";
?>