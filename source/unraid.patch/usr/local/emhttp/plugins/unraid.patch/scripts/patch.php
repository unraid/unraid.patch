#!/usr/bin/php
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

require_once "/usr/local/emhttp/plugins/unraid.patch/include/paths.php";

function download_url($url, $path = "", $bg = false, $timeout = 45) {
  $vars = parse_ini_file("/var/local/emhttp/var.ini");
  $keyfile = empty($vars['regFILE']) ? false : @base64_encode(@file_get_contents($vars['regFILE']??""));

  $ch = curl_init();
  curl_setopt($ch,CURLOPT_URL,$url);
  curl_setopt($ch,CURLOPT_FRESH_CONNECT,true);
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
  curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
  curl_setopt($ch,CURLOPT_TIMEOUT,$timeout);
  curl_setopt($ch,CURLOPT_ENCODING,"");
  curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
  curl_setopt($ch,CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch,CURLOPT_FAILONERROR,true);
  curl_setopt($ch,CURLOPT_HTTPHEADER,["X-Unraid-Keyfile:$keyfile"]);

  if ( !getenv("http_proxy") && is_file("/boot/config/plugins/community.applications/proxy.cfg") ) {
    $proxyCFG = parse_ini_file("/boot/config/plugins/community.applications/proxy.cfg");
    curl_setopt($ch, CURLOPT_PROXYPORT,intval($proxyCFG['port']));
    curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL,intval($proxyCFG['tunnel']));
    curl_setopt($ch, CURLOPT_PROXY,$proxyCFG['proxy']);
  }
  $out = curl_exec($ch);
  if ( curl_errno($ch) == 23 ) {
    curl_setopt($ch,CURLOPT_ENCODING,"deflate");
    $out = curl_exec($ch);
  }
  curl_close($ch);
  if ( $path )
    file_put_contents($path,$out);

  return $out ?: false;
}
function download_json($url,$path="",$bg=false,$timeout=45) {
  return json_decode(download_url($url,$path,$bg,$timeout),true);
}
function writeJsonFile($filename,$jsonArray) {
  return file_put_contents($filename,json_encode($jsonArray, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
function readJsonFile($filename) {
  $json = json_decode(@file_get_contents($filename),true);

  return is_array($json) ? $json : array();
}

function logger($msg) {
  global $option;

  echo $msg;

  if ( $option !== "boot" )
    exec("logger ".str_replace("\n","",escapeshellarg($msg)));
}


###MAIN

@mkdir($paths['tmp']);
$unraidVersion = parse_ini_file($paths['version']);

$action = $argv[1] ?? "";
$option = $argv[2] ?? $unraidVersion['version'];

switch($action) {
  case "install":
    install();
    break;
  case "check":
    check();
    break;
  default:
    break;
}

function install() {
  global $paths, $unraidVersion;

  if ( ! file_exists($paths['accepted']) ) {
    logger("Installation of Unraid patches not accepted.  You must go to Tools - Unraid Patch and accept the disclaimer\n");
    exit();
  }
  $installedUpdates = readJsonFile($paths['installedUpdates']);

  $installDir = $paths['flash'].$unraidVersion['version'];
  if ( ! is_dir($installDir) ) {
    logger("Installation directory not found.  Aborting patch installation\n");
    exit(1);
  }

  $updates = readJsonFile("{$paths['flash']}/{$unraidVersion['version']}/patches.json");
  if ( ! is_array($updates['patches']) && ! is_array($updates['prescripts']) && ! is_array($updates['scripts']) ) {
    logger("Could not read patches.json.  Aborting\n");
    exit(1);
  }
  if ( version_compare($unraidVersion['version'],$updates['unraidVersion'],"!=") ) {
    logger("Unraid version mismatch in patches.json.  Aborting Installation\n");
    exit(1);
  }
  // install each update in order
  foreach($updates['prescripts'] ?? [] as $script) {
    $filename = "{$paths['flash']}{$unraidVersion['version']}/".basename($script['url']);
    if ( $installedUpdates[basename($script['url'])] ?? false ) {
      logger("Skipping $filename... Already Installed\n");
      continue;
    }

    logger("Executing $filename...\n");
    logger("\n");
    copy($filename,$paths['tmp']."/script");
    if ( md5_file($paths['tmp']."/script") !== $script['md5'] ) {
      logger("MD5 verification failed.  Aborting\n");
      exit(1);
    }
    chmod($paths['tmp']."/script",777);
    passthru($paths['tmp']."/script",$exitCode);
    @unlink($paths['tmp']."/script");
    if ( ! $exitCode ) {
      $installedUpdates[basename($script['url'])] = true;
    } else {
      logger("\n\nFailed to install script ".basename($script['url'])."   Aborting\n");
      exit(1);
    }
  }
  foreach($updates['patches'] as $script) {
    $filename = "{$paths['flash']}{$unraidVersion['version']}/".basename($script['url']);
    if ( $installedUpdates[basename($script['url'])] ?? false ) {
      logger("Skipping $filename... Already Installed\n");
      continue;
    }

    logger("Installing $filename...\n");
    logger("\n");
    if ( md5_file($filename) !== $script['md5'] ) {
      logger("MD5 verification failed.  Aborting\n");
      exit(1);
    }
    $baseDir = $script['dir'] ?? "/usr/local/";

    passthru("/usr/bin/patch -d $baseDir -p1 -i ".escapeshellarg($filename),$exitCode);
    if ( ! $exitCode ) {
      $installedUpdates[basename($script['url'])] = true;
    } else {
      logger("\n\nFailed to install patch ".basename($script['url'])."   Aborting\n");
      logger("\n\n<b><font color='crimson'>The failure to install is likely due to a 3rd party plugin modifying a system file.  The patches have been partially installed.  A reboot will be necessary to fully install the patches</font></b>\n",true);
      exit(1);
    }
  }
  foreach($updates['scripts'] ?? [] as $script) {
    $filename = "{$paths['flash']}{$unraidVersion['version']}/".basename($script['url']);
    if ( $installedUpdates[basename($script['url'])] ?? false ) {
      logger("Skipping $filename... Already Installed\n");
      continue;
    }

    logger("Executing $filename...\n");
    logger("\n");
    copy($filename,$paths['tmp']."/script");
    if ( md5_file($paths['tmp']."/script") !== $script['md5'] ) {
      logger("MD5 verification failed.  Aborting\n");
      exit(1);
    }
    chmod($paths['tmp']."/script",777);
    passthru($paths['tmp']."/script",$exitCode);
    @unlink($paths['tmp']."/script");
    if ( ! $exitCode ) {
      $installedUpdates[basename($script['url'])] = true;
    } else {
      logger("\n");
      logger("Failed to install script ".basename($script['url'])."   Aborting\n");
      exit(1);
    }
  }
  logger("Patches Installed\n");
  writeJsonFile($paths['installedUpdates'],$installedUpdates);
}

function check() {
  global $option, $paths, $unraidVersion;

  if ( !is_file($paths['override']) ) {
    $patchesAvailable = $paths['github']."/$option/patch/patches.json";
  } else {
    $patchesAvailable = trim(file_get_contents($paths['override']));
  }

  logger("Checking for patches for OS version $option\n");
  $updates = download_json($patchesAvailable);
  if (! $updates || empty($updates) ) {
    logger("No patches found\n");
    return;
  }

  if ( is_file($paths['override']) ) {
    writeJsonFile($paths['overridePatch'],$updates);
    $option = $updates['unraidVersion'];
  }

  $downloadFailed = false;
  $updatesAvailable = false;

  //if ( ! $option && is_file($paths['override']) ) {
  //  $option = $updates['unraidVersion'];
  //} else {
  //  $option = $option ?: $unraidVersion['version'];
  //}
  $installedUpdates = readJsonFile($paths['installedUpdates']);
  $newPath = "{$paths['flash']}$option/";
  exec("mkdir -p ".escapeshellarg($newPath));
  foreach ($updates['patches'] ?? [] as $patches) {
    if ( isset($installedUpdates[basename($patches['url'])]) ) {
      logger("Skipping ".basename($patches['url'])."-- Already installed\n");
      continue;
    }
    logger("Downloading patches for $option...");
    if ( is_file("$newPath/".basename($patches['url']))) {
      if (md5_file("$newPath/".basename($patches['url'])) == $patches['md5']) {
        logger("\nPatch file ".basename($patches['url'])." already exists.   Skipping\n");
        continue;
      }
    }

    download_url($patches['url'],"$newPath/".basename($patches['url']));
    if (md5_file("$newPath/".basename($patches['url'])) !== $patches['md5']) {
      logger("MD5 verification failed!\n");
      $downloadFailed = true;
      @unlink("$newPath/".basename($patches['file']));
      break;
    }
  }
  foreach ($updates['scripts'] ?? [] as $scripts) {
    if ( isset($installedUpdates[basename($scripts['url'])]) ) {
      logger("Skipping ".basename($scripts['url'])." -- Already installed\n");
      continue;
    }
    logger("Downloading {$scripts['url']}...");
    if ( is_file("$newPath/".basename($scripts['url']))) {
      if (md5_file("$newPath/".basename($scripts['url'])) == $scripts['md5']) {
        logger("Script file already exists.  Skipping\n");
        continue;
      }
    }

    download_url($scripts['url'],"$newPath/".basename($scripts['url']));
    if (md5_file("$newPath/".basename($scripts['url'])) !== $scripts['md5']) {
      logger("MD5 verification failed!\n");
      $downloadFailed = true;
      @unlink("$newPath/".basename($scripts['file']));
      break;
    }
  }
  foreach ($updates['prescripts'] ?? [] as $scripts) {
    if ( isset($installedUpdates[basename($scripts['url'])]) ) {
      logger("Skipping {$scripts['url']} -- Already installed\n");
      continue;
    }
    logger("Downloading {$scripts['url']}...");
    if ( is_file("$newPath/".basename($scripts['url']))) {
      if (md5_file("$newPath/".basename($scripts['url'])) == $scripts['md5']) {
        logger("Script file already exists.  Skipping");
        continue;
      }
    }

    download_url($scripts['url'],"$newPath/".basename($scripts['url']));
    if (md5_file("$newPath/".basename($scripts['url'])) !== $scripts['md5']) {
      logger("MD5 verification failed!\n");
      $downloadFailed = true;
      @unlink("$newPath/".basename($scripts['file']));
      break;
    }
  }
  if ( $downloadFailed ) {
    logger("\n");
    logger("Downloads aborted.\n");
    // only delete files that haven't already been installed
    $alreadyInstalled = glob("{$paths['flash']}/$option/");
    foreach ( $alreadyInstalled as $file) {
      if ( !isset($installedUpdates[basename($file)]) ) {
        @unlink($file);
      }
    }
    exit(1);
  } else {
    writeJsonFile("{$paths['flash']}/$option/patches.json",$updates);
    logger("\n");
  }
}

