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

$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: "/usr/local/emhttp";

require_once "$docroot/plugins/unraid.patch/include/paths.php";
require_once "$docroot/plugins/dynamix/include/Wrappers.php";
if (is_file("$docroot/webGui/include/MarkdownExtra.inc.php") ) {
  require_once "$docroot/webGui/include/MarkdownExtra.inc.php";
}
require_once "$docroot/webGui/include/Markdown.php";

$unraidVersion = parse_ini_file($paths['version']);

$_POST['action'] = $_POST['action'] ?? $argv[1];
$background = ($argv[2]??"") == "background";

switch ($_POST['action']) {
  case "accepted": 
    accepted();
    break;
  case "check":
    check();
    break;
  case "install":
    install();
    break;
}

function my_echo($message) {
  global $background;

  if ( $background ){
    return;
  } else {
    echo $message;
  }
}

function accepted() {
  global $paths;

  touch($paths['accepted']);
  echo "ok";
}

function check() {
  global $paths,$unraidVersion, $background;

  $downgradeVersion = downgradeVersion();
  $msg1 = "";
  if ( $downgradeVersion !== $unraidVersion['version'] ) {
    $msg1 = " - Note: This OS version and patches will be installed boot time";
    $unraidVersion['version'] = $downgradeVersion;
  }
  exec("/usr/local/emhttp/plugins/unraid.patch/scripts/patch.php check $downgradeVersion",$output,$error);
  if ( $error ) {
    my_echo("<script>$('#displayError').show();</script>".implode("<br>",$output));
    return;
  }

  $installedUpdates = readJsonFile($paths['installedUpdates']);
  $availableUpdates = readJsonFile($paths['flash'].$unraidVersion['version']."/patches.json");
  if ( is_file($paths['override']) ) {
    $installedUpdates = [];
    $availableUpdates = readJsonFile($paths['overridePatch']);
  }

  $unraidVersion['version'] = $downgradeVersion;
  $updatesAvailable = false;
  foreach ($availableUpdates['patches'] ?? [] as $update) {
    if ( ! ($installedUpdates[basename($update['url'])] ?? false) ) {
      $updatesAvailable = true;
      break;
    }
  }
  if ( ! $updatesAvailable ) {
    foreach ($availableUpdates['scripts'] ?? [] as $update) {
      if ( ! ($installedUpdates[basename($update['url'])] ?? false) ) {
        $updatesAvailable = true;
        break;
      }
    }
  }
  if ( ! $updatesAvailable ) {
    foreach ($availableUpdates['prescripts'] ?? [] as $update) {
      if ( ! ($installedUpdates[basename($update['url'])] ?? false) ) {
        $updatesAvailable = true;
        break;
      }
    }
  }
  if ($background && ! $updatesAvailable) {
    echo "none";
    return;
  }
  if ($background && $updatesAvailable) {
    echo "Patches Available!";
    return;
  }
  if ( ! $updatesAvailable && ! is_file($paths['override']) ) {
    if ( ! empty($availableUpdates) ) {
      my_echo("<script>$('#displayInstalled').show();</script>".markdown($availableUpdates['changelog']));
    } else {
      if ( $msg1 ) {
        my_echo("<script>$('#displayNone,#disp4').show();$('#disp3,#displayNew,#displayInstalled').hide();</script>");
      } else {
        my_echo("<script>$('#displayNone').show();</script>");
      }
    }
    return;
  } else {
    if ( is_file($paths['override'] ) )
      my_echo(markdown("#Override File Present\n"));
    }
    $msg = version_compare($unraidVersion['version'],$availableUpdates['unraidVersion'],"!=") ? "  * MISMATCH" : "";
    my_echo(markdown("#Unraid Version: {$availableUpdates['unraidVersion']}$msg$msg1\n\n{$availableUpdates['changelog']}"));
    if ( $msg )
      my_echo( "<script>$('#displayNew').show();$('#installButton').prop('disabled',true);</script>");
    else {
      if ( ! $msg1 ) {
        my_echo( "<script>$('#displayNew').show();</script>");
      } else {
        if ( empty($availableUpdates) )
          my_echo("<script>$('#displayNone,#disp4').show();$('#disp3').hide();</script>");
        else {
          my_echo("<script>$('#displayNone,#disp5').show();$('#disp3').hide();</script>");
        }
      }
  }
}

function install() {
  exec("/usr/local/emhttp/plugins/unraid.patch/scripts/patch.php install");
  echo "installed";
}


function readJsonFile($filename) {
  $json = json_decode(@file_get_contents($filename),true);

  return is_array($json) ? $json : array();
}

function downgradeVersion() {
  $changes = '/boot/changes.txt';
  if (file_exists($changes)) {
    exec("head -n4 $changes",$rows);
    foreach ($rows as $row) {
      $i = stripos($row,'version');
      if ($i !== false) {
        [$version,$date] = explode(' ',trim(substr($row,$i+7)));
        break;
      }
    }
  }
  return $version;
}
?>

