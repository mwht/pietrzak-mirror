<?php
set_time_limit(0);
error_reporting(0);

function get_headers_x($url,$format=0, $user='', $pass='', $referer='') {
    if (!empty($user)) {
        $authentification = base64_encode($user.':'.$pass);
        $authline = "Authorization: Basic $authentification\r\n";
    }

    if (!empty($referer)) {
        $refererline = "Referer: $referer\r\n";
    }

    $url_info=parse_url($url);
    $port = isset($url_info['port']) ? $url_info['port'] : 80;
    $fp=fsockopen($url_info['host'], $port, $errno, $errstr, 30);
    if($fp) {
        $head = "GET ".@$url_info['path']."?".@$url_info['query']." HTTP/1.0\r\n";
        if (!empty($url_info['port'])) {
            $head .= "Host: ".@$url_info['host'].":".$url_info['port']."\r\n";
        } else {
            $head .= "Host: ".@$url_info['host']."\r\n";
        }
        $head .= "Connection: Close\r\n";
        $head .= "Accept: */*\r\n";
        $head .= $refererline;
        $head .= $authline;
        $head .= "\r\n";

        fputs($fp, $head);
        while(!feof($fp) or ($eoheader==true)) {
            if($header=fgets($fp, 1024)) {
                if ($header == "\r\n") {
                    $eoheader = true;
                    break;
                } else {
                    $header = trim($header);
                }

                if($format == 1) {
                $key = array_shift(explode(':',$header));
                    if($key == $header) {
                        $headers[] = $header;
                    } else {
                        $headers[$key]=substr($header,strlen($key)+2);
                    }
                unset($key);
                } else {
                    $headers[] = $header;
                }
            }
        }
        return $headers;

    } else {
        return false;
    }
}


function getDirectoryListing($dir,$d=0,$f=0) { // $d and $f for tracking array indices
 global $result;
 $ctx = stream_context_create(array(
    'http' => array(
        'header'  => "Authorization: Basic " . base64_encode("student:std2013")
    )
 ));
 $data = file_get_contents("http://pietrzak.pw/std?directory=".urlencode($dir),false,$ctx);
 preg_match_all("/<a href=\"\?directory=(.*)\" id=\"(.*)\">/",$data,$m);
 preg_match_all("/<a href=\"[^\?](.*)\" id=\"(.*)\">/",$data,$mf);
 foreach($mf[1] as $matchFile) {
 	file_put_contents("files.log",urldecode($matchFile).PHP_EOL,FILE_APPEND);
 }
 foreach($m[1] as $match) {
  file_put_contents("dirs.log",urldecode($match).PHP_EOL,FILE_APPEND);
  getDirectoryListing(urldecode($match));
 }
 return $result;
}

function realURL($url) {
	return str_replace(array("+","%2F"),array("%20","/"),urlencode($url)); // TODO: optimize me
}

function progressBar($percent) {
 if($percent > 100 || $percent < 0) {
	echo "[xxxxxxxxxx] ";
	return;
 }
 echo "[";
 for($i=0;$i<$percent/10;$i++) {
  echo "*";
 }
 for($i=0;$i<10-($percent/10);$i++) {
  echo " ";
 }
 echo "] ";
}

function downloadFile($fname) {
	 $ctx = stream_context_create(array(
    	'http' => array(
        	'header'  => "Authorization: Basic " . base64_encode("student:std2013")
    	)
 	));
	$f = fopen("http://pietrzak.pw/std/".realURL(substr($fname,1)),"r",TRUE,$ctx);
	if(!$f) {
		echo "[!] Could not download file \"".$fname."\" !".PHP_EOL;
		return;
	}
	$g = fopen(".".$fname,"w");
	if(!$g) {
		echo "[!] Could not create file \"".$fname."\" !".PHP_EOL;
		return;
	}
	echo "[*] Downloading file \"".$fname."\" ... ".PHP_EOL;
	$progress = 0;
	$head = array_change_key_case(get_headers_x("http://pietrzak.pw/std/".realURL(substr($fname,1)),1,'student','std2013','http://pietrzak.pw/std'));
	$fsize = $head['content-length'];
	while(!feof($f)) {
		$buf = fread($f,8192);
		fwrite($g,$buf);
		$progress += strlen($buf);
		progressBar(round(($progress/$fsize)*100.0));
		echo $progress." B/".$fsize." B (".round(($progress/$fsize)*100.0)."%)\r";
	}
	fclose($f);
	fflush($g);
	fclose($g);

}

echo "+--------------------------------------+".PHP_EOL;
echo "| pietrzak.pw mirror tool ver 15.12.17 |".PHP_EOL;
echo "+--------------------------------------+".PHP_EOL;
echo PHP_EOL;

if($_SERVER["argc"] <= 1) {
	echo "usage: php -f ".$_SERVER["argv"][0]." -- <folder name or -r for resume download>".PHP_EOL.
	     "	examples:".PHP_EOL.
	     "		php -f ".$_SERVER["argv"][0]." -- Semestr_1 	- download files from Semestr_1".PHP_EOL.
	     "		php -f ".$_SERVER["argv"][0]." -- -r 		- resume download".PHP_EOL.
	     "		php -f ".$_SERVER["argv"][0]." -- .		- full mirror (carefully!)".PHP_EOL.PHP_EOL;
	exit;
}

$startdir = "";
if($_SERVER["argc"] > 1) $startdir = $_SERVER["argv"][1];
if($startdir == "-r") {
 if(file_exists("files.log") {
 	echo "[*] Resuming file download from files.log".PHP_EOL;
 	$filelist = explode(PHP_EOL,trim(file_get_contents("files.log")));
 } else {
	echo "[-] files.log not found".PHP_EOL;
 }
} else {
	if(file_exists("files.log")) unlink("files.log");
	if(file_exists("dirs.log")) unlink("dirs.log");
	echo "[*] Scanning for files and directories, please wait, this might take a while...".PHP_EOL;
	getDirectoryListing("./".$startdir);
	if(strlen($startdir) > 0) mkdir("./".$startdir);
	echo "[*] ".count($result["dirs"])." directories and ".count($result["files"])." files found.".PHP_EOL;
	echo "[*] Creating directories for files...".PHP_EOL;
	$dirlist = explode(PHP_EOL,trim(file_get_contents("dirs.log")));
	$filelist = explode(PHP_EOL,trim(file_get_contents("files.log")));
	foreach($dirlist as $dir) {
		mkdir($dir);
	}
}
echo "[*] Beginning file download...".PHP_EOL;
foreach($filelist as $file) {
	downloadFile($file);
}
echo "[+] Job finished.".PHP_EOL;
?>
