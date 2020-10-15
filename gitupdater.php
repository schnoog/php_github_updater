<?php

/**
 * Small script to keep a local installation from a github repo up to date
 * 
 * How it works:
 * Place it in the root directory of the project (if it's cloned the directory where .git is located)
 * Change the $user and $repo variables to fit the relevant repository 
 * 
 * GitHub limits the unauthorized requests to 60 per hour, afterwards you'll get blocked
 * For develeopment reasons it's proposed to set the $github_account variables to fit your GitHub account data
 * 
 * The script captures a list of commits from api.github.com made to the branch defined below
 * It will than start with the newest commit and caputure the checksums of the files changed
 * If the checksums of at least one file from the commit doesn't match with the one of the local available file, 
 * the file will be added to the list and the next (older) commit will be inspected and so on.
 * 
 * This will end as soon as all files of a commit match with the local one (Version A) or all commits supplied by the api query have
 * unmet files (Version B).
 * 
 * If files need to be updatd, the script will (provided $do_update is set to true )
 * Version A: Copy each single file from github and replaces the local version
 * Version B: Download the branch.zip from the Github repository and unzip it in the directory
 * 
 * 
 * 
 */

/**
 * General and Repository-Settings
 */
$user = "schnoog";                // The Github user which owns the repository https://github.com/schnoog/
$repo = "testrepo";               // The repository name https://github.com/schnoog/php_github_updater
$branch = "";                     // the branch (keep empty to use the default branch)
$do_update = true;                // Should updates be applied
$target_directory = __DIR__;      // The root directory of the projects local installation __DIR__ if this script is placed along the other files
$write_output = true;             // Should the steps performed be written into $write_output_file
$write_output_file = __DIR__ . DIRECTORY_SEPARATOR . "updatestep.info"; // And the filename
$usage_password = "";   // If  $usage_password isn't empty (""), this password will be required to perform the update check /action
/**
 * Interface-Settings
 */
$capture_requests = true;         // Should get/post requests containing the item "updateaction" with the valie check or update trigger the script
$use_own_gui = true;              // Should the own (included, barebone) GUI be shown

/**
 * GitHub User Account 
 * -Only needed if rate limiter hits,should be no problem with f.e. 1 update per hour
 * -(mygitpw.php contains those 2 (user & pass) lines but isn't part of the repository)
 */
$github_account['user'] = "";          
$github_account['pass'] = "";   
if(file_exists('mygitpw.php')) include_once('mygitpw.php');
/**
 * Branch setting
 */
if(strlen($branch)<1) GetBranch($user,$repo);
/**
 * Let the magic happen and caputre requests
 * 
 */
$hasaction = false;
if ($capture_requests){  
  if(isset($_REQUEST['updateaction'])){

    if(strlen($usage_password)>0){
        if(!isset($_REQUEST['pw'])) {
          DirectOut("Password missing",true,true);
          die ("No password");
        }
        if($_REQUEST['pw'] !== $usage_password) {
          DirectOut("Wrong Password",true,true);
          die ("Wrong password");
        }
    }
          if($_REQUEST['updateaction'] == "check"){
            CheckCommits($user,$repo,false,$target_directory);
            $use_own_gui = false;
            $hasaction = true;
          }
          if($_REQUEST['updateaction'] == "update"){
            CheckCommits($user,$repo,true,$target_directory);
            $use_own_gui = false;
            $hasaction = true;
          }
          if($_REQUEST['updateaction'] == "clear"){
            DirectOut("",true,true);
            $hasaction = true;
          }          
  }

}
if($use_own_gui) {
  $port="";
  if($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) $port = ":" .$_SERVER['SERVER_PORT'];
  $scripturl = "http".(!empty($_SERVER['HTTPS'])?"s":""). "://".$_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI'];
  
  ShowGUI();
}else{
  if(!$hasaction)  CheckCommits($user,$repo,$do_update,$target_directory);
}
/**
 * 
 * 
 */
function QuickStart($user,$repo,$do_update,$dir = __DIR__){
  global $commit_completed;
    $tmp = CheckCommits($user,$repo,$do_update,$dir);
    if(count($tmp)>0){
          if($do_update){
              if($commit_completed){
                  echo "<h1>Updated " . count($tmp) . " files</h1>";
              }else{
                  echo "<h1>Complete update performed</h1>";
              }
          }else{
            if($commit_completed){
              echo "<h1>Update for " . count($tmp) . " files available</h1>";
            }else{
                echo "<h1>Full package update required</h1>";
            }
          }
    }
}



function CheckCommits($user,$repo,$download_if_not_matching,$dir = __DIR__){
  global $commit_completed;
    $not_matching = array();
    DirectOut("##############################",true,true);
    DirectOut("##############################");
    DirectOut("Starting update process from",true);
    DirectOut("https://github.com/$user/$repo");
    DirectOut("##############################");
    DirectOut("Step 1: Get the last commits");
    $LastCommits = GetGitCommits($user,$repo,true);
    $commits_num = count($LastCommits);
    DirectOut("-Github returned $commits_num commits");
    DirectOut("Step 2: Start checking commits");
    DirectOut("   Progress: ");
    $cnt=0;
    $fcnt = 0;
    foreach($LastCommits as $LastSHA => $lastData){
        DirectOut("*",false);
        $cnt++;
        $commit_completed = false;
        $CommFiles = GetGitCommitFiles($user,$repo,$LastSHA,true);
        $thisAll = count($CommFiles);
        $thisOK = 0;
        foreach($CommFiles as $filename => $filesha){
            $fcnt++;
            $localHash = GitFileHash($dir . DIRECTORY_SEPARATOR . $filename);
            if($filesha == $localHash){
                $thisOK++;
                if($thisOK == $thisAll) {
                  $commit_completed = true;
                  break;
                }
            }else{
              $not_matching[$filesha] = $filename;

            }
        }

        if($commit_completed) break;
        
    }
    DirectOut("-Update check completed for $cnt commits and $fcnt files");
    $missfiles = count($not_matching);
    DirectOut("##############################");
    if($missfiles > 299) {
      $tmp = "More than 300 files are outdated";
      DirectOut($tmp);
      $commit_completed = false;
    }elseif($missfiles > 0){
      $tmp = "Number of outdated files: " . $missfiles;
      DirectOut($tmp);
    }else{
      $tmp = "Your installation is up to date";
      DirectOut($tmp);
    }




    if($download_if_not_matching){
        if(count($not_matching) > 0) DownloadMissingFiles($user,$repo,$not_matching,$commit_completed);
    }else{

    }

return $not_matching;
}
/**
 * Download files
 * if $commit_completed = true, download single files listed in the last 10 commits
 * otherwise download the zip and replace all files
 */
function DownloadMissingFiles($user,$repo,$not_matching,$commit_completed,$dir = __DIR__){
  DirectOut("Step 3: Perform the update");
  DirectOut("by ");
      if($commit_completed){
        $branch = GetBranch($user,$repo);
        $toUpdate = count($not_matching);
        DirectOut("replacing individually $toUpdate files",false);
        DirectOut("   Progress: ");
        foreach($not_matching as $sha => $filename){
          DirectOut("*",false);
          $remoteurl = "https://raw.githubusercontent.com/$user/$repo/$branch/$filename";
          $filetmp = getSslPage($remoteurl);
          file_put_contents($dir . DIRECTORY_SEPARATOR . $filename,$filetmp);
          }
      }else{
          DirectOut("replacing all files from zip",false);
          
          DownloadMasterZipAndUnpack($user,$repo,$dir);
      }

}
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
  if(file_exists($tmpfile)) unlink($tmpfile);
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
if(file_exists($tmpfile)) unlink($tmpfile);
return $commits;
}
/**
 *
 */
function DownloadMasterZipAndUnpack($user,$repo,$dir=__DIR__){
  $branch = GetBranch($user,$repo);

  if(!file_exists($branch . ".zip")){
  $remoteurl = "https://github.com/$user/$repo/archive/".$branch.".zip";
  DirectOut("-Downloading $remoteurl");
  $filetmp = getSslPage($remoteurl,true);
  file_put_contents($branch .".zip",$filetmp);
  }
  $len=strlen($repo . "-" .$branch . "/");

  if(is_dir('./unpack_temp_dir')) rrmdir('./unpack_temp_dir');
  mkdir('./unpack_temp_dir');
  $zip = new ZipArchive;
  if ($zip->open($branch .'.zip') === TRUE) {
      DirectOut("-Create temporary directory and unpack the archive");
      $zip->extractTo('./unpack_temp_dir/');
      DirectOut($zip->numFiles . " files unpacked, start to copy them");
      for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        $currfile = "./unpack_temp_dir/" . $filename;
        $newfile = $dir . DIRECTORY_SEPARATOR . substr($filename,$len);

        $lastchar = substr($currfile,-1);
        if($lastchar != "/" && $lastchar != "\\") {
          copy($currfile,$newfile);
        }else{
          if(strlen($newfile) > 0)@mkdir($newfile);
        }
      }
      $zip->close();
      DirectOut("Files copied");
      unlink($branch . '.zip');
      rrmdir('./unpack_temp_dir');
      DirectOut("Update completed");
  } else {
      echo 'Fehler';
  }
}
/**
 * 
 */
function GetBranch($user,$repo){
  global $branch;
  if(strlen($branch)>0) return $branch;
  $callurl = "https://api.github.com/repos/$user/$repo";
  $tmp = getSslPage($callurl );
  $repodata = json_decode($tmp,true);
  $branch = $repodata['default_branch'];
  return $branch;
}



/**
 * Helper
 */
function GitFileHash($file2check){
  if(!file_exists($file2check)) return false;
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
  function getSslPage($url,$nologin = false) {
    global $github_account;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_REFERER, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    if($nologin)$github_account['user'] = "";
    if(isset($github_account['user']) && isset($github_account['pass'])){
          $x = strlen($github_account['user']) * strlen($github_account['pass']);
          if ($x > 0){
            $ulog = $github_account['user'] . ":" . $github_account['pass'];
            curl_setopt($ch, CURLOPT_USERPWD, $ulog);
          }
    }
    curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
  }
/**
 *
 */
function DebugOut($given){
  echo "<hr><pre>" . print_r($given,true). "</pre>";
}
/**
*
*/
function DirectOut($output,$InNewLine = true,$empty_before = false){
  global $write_output_file, $write_output;
  if(!$write_output) return true;
	$tx = "";
	if(file_exists($write_output_file)){
    $tx = file_get_contents($write_output_file);
    if(strlen($tx) > 0){
      if($InNewLine) $tx .= "\n";
    }
  }
    if($empty_before)$tx = "";
		file_put_contents($write_output_file,$tx . $output);
}
/**
*
*/
function rrmdir($dir) {
  if (is_dir($dir)) {
    $objects = scandir($dir);
    foreach ($objects as $object) {
      if ($object != "." && $object != "..") {
        if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object))
          rrmdir($dir. DIRECTORY_SEPARATOR .$object);
        else
          unlink($dir. DIRECTORY_SEPARATOR .$object);
      }
    }
    rmdir($dir);
  }
  }
/**
 ****************************************************************************************************************************************
 ****************************************************************************************************************************************
 **************************************************************************************************************************************** 
 */

/**
 * 
 * And now to our ugly GUI
 * 
 * 
 */
function ShowGUI(){
  global $write_output_file, $write_output, $user,$repo,$scripturl,$usage_password, $branch;
  ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>php Github Updater - github.com/<?php echo $user . "/" . $repo ;?></title>

    <meta name="description" content="Update local installed apps directly from github repository">
    <meta name="author" content="Schnoog">

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">

  </head>
  <body>

    <div class="container-fluid">
	<div class="row">
		<div class="col-md-12">
			<div class="jumbotron">
      <center>
				<h2>
					php Github updater<br />Repository <a href="https://github.com/<?php echo $user . "/" . $repo . '/tree/' . $branch ;?>" target="_blank">github.com/<?php echo $user . "/" . $repo ;?> branch: <?php echo $branch;?></a>
				</h2>
				<p>
					Update your local installation from the  repository
        </p>
<?php
  if(strlen($usage_password)>0){
    ?>
      <table><tr>
        <td></td>
        <td>
          <table border="1"><tr><td>
            <label for="pw">Your installation password</label>  
            <input name="pw" id="pw" type="password" class="form-control" required>
          </td></tr></table>
        </td>
        <td></td>
      </tr></table>
    <?php
  }


?>
				<p>
					<button class="btn btn-primary btn-large btnaction" id="btn1" onclick="UpdateFlow('0')">Check for updates</button>
					<button class="btn btn-success btn-large btnaction" id="btn2" onclick="UpdateFlow('1')">Check for and perform updates</button>
        </p>
        <p id="update_result"></p>
      </center>
			</div>
		</div>
  </div>
</div>

<!-- Footer -->
<footer class="page-footer font-small blue pt-4">

  <!-- Footer Links -->
  <!-- Copyright -->
  <div class="footer-copyright text-center py-3">
    <a href="https://github.com/schnoog/php_github_updater">php_github_updater </a>by Schnoog
  </div>
  <!-- Copyright -->

</footer>
<!-- Footer -->

<script
  src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>
<script type="text/javascript">
let RunUpdate = 0;
let WasRun = 0;
let Btn1 = "";
let Btn2 = "";
let WaitMsg = "In progress...please wait";
let IsAuthorized = 1;
let GivenPW = "";
function CheckForPW(){
<?php if(strlen($usage_password)>0){ ?>
    GivenPW = $('#pw').val();
    if (GivenPW == ""){
      alert("Password required");
      IsAuthorized = 0;
    }else{
      GivenPW = "&pw=" + GivenPW;
    }
<?php }else{ ?>
    IsAuthorized = 1;
<?php } ?>
}


function UpdateFlow(DoUpdate){
  CheckForPW();
  if(IsAuthorized == 1){
    Btn1 = $('#btn1').html();
    Btn2 = $('#btn2').html();
    $('#btn1').html(WaitMsg);
    $('#btn2').html(WaitMsg);
    $('.btnaction').prop('disabled', true);
    $.get(
          '<?php echo $scripturl; ?>?updateaction=clear' + GivenPW ,
          function(response) {
            PrepareUpdate(DoUpdate);
          }
        );  
  }
}


setInterval(function() { // this code is executed every 500 milliseconds:
    if (RunUpdate == 1) {
      WasRun = 1;
      GetState(RunUpdate);
    }else{
      if(WasRun == 1){
        GetState(0);
        WasRun = 0;
      }

    }
}, 1000);

function PrepareUpdate(DoUpdate){
      let DoAction = "check";
      if(DoUpdate == 1){
        DoAction = "update";
      }
      let DoTag = "updateaction";
      RunUpdate = 1;
      $.get(
        '<?php echo $scripturl; ?>?updateaction=' + DoAction + GivenPW,
        function(response) {
          <?php if(!$write_output){ ?>
            $('#update_result').html(response);
          <?php } ?>
            RunUpdate = 0;
            GetState(0);
            $('.btnaction').prop('disabled', false);
            $('#btn1').html(Btn1);
            $('#btn2').html(Btn2);
        }
      );
      GetState(1);
}
///////////
<?php if($write_output){ ?>
function GetState(StatVal){
  RunUpdate = StatVal;
  $.get(
  '<?php echo basename($write_output_file); ?>',
    function(response) {
      console.log("> ", response);
      $('#update_result').html(response.replace(/\n/g, "<br />"));
    }
  );
}
<?php }else{ ?>
function GetState(StatVal){
}


<?php } ?>

</script>

  </body>
</html>

  <?php
}







