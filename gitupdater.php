<?php
/**
 * Idee: Alle lokalen Files einlesen
 * Git Commits Laden
 * ->beim neusten anfange
 * -->Geänderte Dateien -> sha -----> in localen files
 *                                              vorhanden ---> wir haben diesen Stand
 *                                        nicht vorhanden ---> auf zum nächsten Commit
 */



/**
 * Settings, files and folder to exclude (no wildcard, only if substr())
 */



$user="schnoog";
$repo="sqstorage";
$startingDirectory = __DIR__ ; // __DIR__ if this script is in the project directory
$performUpdate = false;  // Should update be performed?
$debugOutput = true; // Some output maybe

$excluded = array(
  "old/",
  "smartyfolders/cache/",
  "smartyfolders/templates_c/",
  ".git/",
  "languages/locale/en_GB/LC_MESSAGES/messages_1",
  "languages/locale/de_DE/LC_MESSAGES/messages_1",

);
/**
 *  Initialisation and Run that thing
 *
 */
error_reporting(E_ALL);
$lastmime = "";
set_time_limit(0);
CheckAndUpdateFromGithub($user,$repo,$startingDirectory,$performUpdate,$debugOutput);

/**
 *
 * A lot of functions
 * Some good, some bad, but most are at least ugly
 *
 *
 */

/**
 * Main function, does all the workload
 */
function CheckAndUpdateFromGithub($user,$repo,$basedirectory = __DIR__,$performUpdate = false,$debug = false){
  if($debug)echo "<h1>Search for last missing commits</h1>";
  $tmp = GetMissingFileList($user,$repo,$basedirectory);
  if($debug){
    if(count($tmp)>0){
      echo "<h3>Following files are missing</h3>";
      DebugOut($tmp);
    }else{
      echo "<h3>No files missing from last commit</h3>";
    }
  }
  if ($performUpdate) UpdateLocalFilesFromGithub($tmp,$debug);
  if($debug)echo "<h1>Done</h1>";
  if(file_exists("git.commitfiles.tmp")) unlink('git.commitfiles.tmp');
  if(file_exists('git.commits.tmp')) unlink( 'git.commits.tmp');
}
/**
 * Copy files from $copylist to local file system
 */
function UpdateLocalFilesFromGithub($copylist,$debug = false){
    foreach($copylist as $localname => $remotename){
        $source = getSslPage($remotename);
        file_put_contents($localname,$source);
        if($debug)echo "Copy $remotename to $localname <br/>";
    }
}
/**
 * Generated the missing file list
 */
function GetMissingFileList($user,$repo,$dir = __DIR__){
  $copylist = array();
  $MissCommits = GetMissingCommit($user,$repo,$dir);
  foreach($MissCommits as $Commit => $MissingFiles){
    for($x=0;$x< count($MissingFiles);$x++){
        $mf = $MissingFiles[$x];
        $copylist[$mf] = "https://raw.githubusercontent.com/$user/$repo/master/$mf";
    }
  }
  return $copylist;
}
/**
 * Goes through the commits (starting with newest)
 * As long as a hash of the file changed in the commit differes from local file hash, the commit is deemed no applied locally
 * The newest commit of which all changed files have the same hash as the relevant local files will stop the back cycling
 * Will return all missing files and commits
 */
function GetMissingCommit($user,$repo,$dir){
    $missingCommits = array();
    $localfiles = GetLocalHashes($dir);
    $CommitLoaded = false;
    $GitCommits = GetGitCommits("schnoog","sqstorage",true);
    foreach($GitCommits as $GitCommitSHA => $CommitData){
      $missing_files = array();
          $CommitFiles = GetGitCommitFiles($user,$repo,$GitCommitSHA,true);
          foreach($CommitFiles as $CommitFile => $Filehash){
            //echo "Check $CommitFile with  $Filehash <br />";
            if(isset($localfiles[$Filehash])) {
              $CommitLoaded = true;
              break;
            }else{
              $missing_files[] = $CommitFile;
            }
          }
          if(!$CommitLoaded){
            $missingCommits[$GitCommitSHA] = $missing_files ;
          }else{
            break;
          }
    }
    return $missingCommits;
}

/**
 * Some function for generating the local file-hash table
 *
 *
 */
function GetLocalHashes($dir){
  $hashes = array();
  $allfiles = getClearDirContents($dir);
  for($x=0;$x<count($allfiles);$x++){
    if(file_exists($allfiles[$x])){
      if(!is_dir($allfiles[$x])) $hashes[GitFileHash($allfiles[$x])] = $allfiles[$x];
    }
  }
  return $hashes;
}
/**
 *
 */
function getClearDirContents($dir){
  global $excluded;
  $retfiles = array();
  $startpos = strlen($dir) + 1;
  $allfiles = getDirContents($dir);
  $allfiles = str_replace(DIRECTORY_SEPARATOR,"/",$allfiles);
  for($x=0;$x<count($allfiles);$x++){
      $work = substr($allfiles[$x],$startpos);
      $do = true;
      foreach($excluded as $exclude){
          if(strpos("X" . $work,$exclude)){
            $do = false;
            break;
          }
      }
      if(!is_dir($work)){
        if($do)$retfiles[] = $work;
      }
  }
  return $retfiles;
}
/**
 *
 */
function getDirContents($dir, &$results = array()) {
  $files = scandir($dir);
  foreach ($files as $key => $value) {
      $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
      if (!is_dir($path)) {
          $results[] = $path;
      } else if ($value != "." && $value != "..") {
          getDirContents($path, $results);
          $results[] = $path;
      }
  }
  return $results;
}
/**
 * Get the Data from Github
 *
 */
/**
 *
 */
function GetGitCommits($user,$repo,$renew = true){
  $tmpfile = 'git.commits.tmp';
  $commits = array();
  if($renew == true){
      if(file_exists($tmpfile)) unlink($tmpfile);
  }
  if(file_exists($tmpfile)){
      $tmp = file_get_contents($tmpfile);
  }else{
      $tmp = getSslPage("https://api.github.com/repos/$user/$repo/commits");
      file_put_contents($tmpfile,$tmp);
  }
  $tmp = json_decode($tmp,true);
  for($x=0;$x < count($tmp); $x++){
      $comm = $tmp[$x];
      $commits[$comm['sha']]['url'] = $comm['commit']['url'];
      $commits[$comm['sha']]['date'] = $comm['commit']['author']['date'];
  }
  return $commits;
}

/**
*
*/
function GetGitCommitFiles($user,$repo,$commit,$renew = true){
$tmpfile = 'git.commitfiles.tmp';
$commits = array();
if($renew == true){
    if(file_exists($tmpfile)) unlink($tmpfile);
}
if(file_exists($tmpfile)){
    $tmp = file_get_contents($tmpfile);
}else{
    $tmp = getSslPage("https://api.github.com/repos/$user/$repo/commits/" . $commit);
    file_put_contents($tmpfile,$tmp);
}
$tmp = json_decode($tmp,true);
for($x=0;$x < count($tmp['files']); $x++){
    $comm = $tmp['files'][$x];
    $commits[$comm['filename']] = $comm['sha'];

}
return $commits;
}

/**
 *
 *
 */
/**
 *
 */
function DebugOut($given){
  echo "<hr><pre>" . print_r($given,true). "</pre>";
}
/**
*
*/
function GitFileHash($file2check){
global $lastmime;
  $cont=file_get_contents($file2check);
  $file_info = new finfo(FILEINFO_MIME_TYPE);
  $lastmime = $file_info->buffer($cont);
  if(strpos(".". $lastmime,'text/'))  $cont = str_replace("\r","" ,$cont);
  if($lastmime == "application/x-wine-extension-ini") $cont = str_replace("\r","" ,$cont);
  $len = mb_strlen($cont,'8bit');
  $toc ="blob " . $len . chr(0) .  $cont ;
  $tmp = sha1($toc);
  return $tmp;
}


/**
*
*/
function getSslPage($url) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_REFERER, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
  $result = curl_exec($ch);
  curl_close($ch);
  return $result;
}
