<?php
define('CLI', PHP_SAPI === 'cli');
openlog("$prog", LOG_PID | LOG_PERROR, LOG_LOCAL0);

function isTimeout(&$counter, $timeout)
{
  $current = time();
  $counter = $counter ? $counter : time();
  if ( ($current - $counter) >= $timeout  )
  {
    $counter = null;
    return true;
  }
  return false;
}

$docroot = $docroot ?: @$_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$relative_path = str_replace($docroot, '', __FILE__);

if (CLI) {
	set_time_limit(0);
	require_once "$docroot/webGui/include/ColorCoding.php";
	foreach ($argv as $key => $value) {
		switch ($key) {
			case 1: $socket_name = $value; break;
			case 2: $file_name   = $value; break;
			case 3: $file_search = $value; break;
		}
	}

	if (is_file($file_name)) {
		if (strlen($file_search)) {
			$cmd = "/usr/bin/tail -n +1 -f ".escapeshellarg($file_name)."";
		} else {
			$cmd = "/usr/bin/tail -n 30 -f ".escapeshellarg($file_name)."";
		}
	}

	if (! isset($cmd)) {
		exit(1);
	}

	$socket_file = "/tmp/.".$socket_name.".sock";
	
	@ unlink($socket_file);

	$socket = stream_socket_server("unix://".$socket_file, $errno, $errstr);
	if (!$socket) {
	  echo "$errstr ($errno)<br />\n";
	  exit(1);
	}

	$descriptorspec = array(
	    0 => array("pipe", "r"),
	    1 => array("pipe", "w"),
	    2 => array("pipe", "w")
	);

	// syslog(LOG_INFO, "CMD: ".$cmd);

	$process = proc_open($cmd, $descriptorspec, $pipes, NULL, NULL);
	stream_set_blocking($pipes[1], 0);

	$return = "";
	$line = 0;
	$lock = true;
	$timer = time();

	sleep(1);
	if (is_resource($process)) {
		// syslog(LOG_INFO, "INITIATING SERVER: ".$socket_name);

		while ($lock) {
			while ($line = fgets($pipes[1])) {

				if (strpos($line,'tail_log')!==false) continue;
				if ($file_search && strpos($line, $file_search)==false) continue;
				$span = "span";
				foreach ($match as $type) foreach ($type['text'] as $text) if (preg_match("/$text/i",$line)) {$span = "span class='{$type['class']}'"; break 2;}
				$line = preg_replace("/ /i", "&nbsp;", htmlspecialchars($line));
				$return .= "<$span>".$line."</span>";
			}

			if (@ $conn = stream_socket_accept($socket, 5)) {
				// syslog(LOG_INFO, "INIT RESPONSE: ".$socket_name);
				// syslog(LOG_INFO, "RESPONSE: ".$m);
			    fwrite($conn, $return, strlen($return));
			    fclose($conn);
				$return = "";
				$timer = time();
			}
			if (isTimeout($timer,15)) break;
		}
	}
	// syslog(LOG_INFO, "KILLING SERVER: ".$socket_name);

	fclose($socket);
	@ unlink($socket_file);
	exit(0);
} else if (isset($_POST["socket_name"]) ) {
	if ( strlen($_POST["socket_name"]) == 0 ) {
		$log_file   = $_POST["file"];
		$log_search = $_POST["search"];
		$random_name = mt_rand();
		exec("php ".__FILE__." ".escapeshellarg($random_name)." ".escapeshellarg($log_file)." ".escapeshellarg($log_search)." 1>/dev/null 2>&1 &");
		echo json_encode(["socket_name" => $random_name]);
	} else {
		$socket_name = $_POST["socket_name"];
		$socket_file = "/tmp/.".$socket_name.".sock";

		@ $socket = stream_socket_client("unix://".$socket_file, $errno, $errstr, 30);
		if (!$socket) {
		    echo json_encode(["error" =>"$errstr ($errno)\n"]);
		} else {
			// fwrite($socket, 'SOME COMMAND'."\r\n");
			$output = array();
			while ($s = fgets($socket)) {
				$output[] = $s;
			}
			echo json_encode($output,JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			fclose($socket);
		}
	}

} else if (isset($_GET["file"]) && $_GET["action"] == "download") {
    
    $file = file($_GET["file"], FILE_IGNORE_NEW_LINES);
	$file_name = pathinfo($_GET["file"], PATHINFO_FILENAME);
    if (strlen($_GET["search"])) {
    	$file = preg_grep("/".$_GET["search"]."/i",$file);
    	$file_name = $_GET["search"];
    }
    $tmpfile = "/tmp/${file_name}.txt";

    file_put_contents($tmpfile, implode("\r\n", $file));

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename='.basename($tmpfile));
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($tmpfile));
    readfile($tmpfile);
    unlink($tmpfile);

} else if (isset($_GET["file"])) {
	if (!isset($var)) {
	  if (!is_file("$docroot/state/var.ini")) shell_exec("wget -qO /dev/null localhost:$(lsof -nPc emhttp | grep -Po 'TCP[^\d]*\K\d+')");
	  $var = @parse_ini_file("$docroot/state/var.ini");
	}
?>

<!DOCTYPE html>
<html>
<head>
	<title>
	<?if (isset($_GET["title"])):?>
	<?=$_GET["title"]?>
	<?endif;?>
	</title>
	<style>
		@font-face{
		font-family:'clear-sans';font-weight:normal;font-style:normal;
		src:url('/webGui/styles/clear-sans.eot');src:url('/webGui/styles/clear-sans.eot?#iefix') format('embedded-opentype'),url('/webGui/styles/clear-sans.woff') format('woff'),url('/webGui/styles/clear-sans.ttf') format('truetype'),url('/webGui/styles/clear-sans.svg#clear-sans') format('svg');
		}
		@font-face{
		font-family:'clear-sans';font-weight:bold;font-style:normal;
		src:url('/webGui/styles/clear-sans-bold.eot');src:url('/webGui/styles/clear-sans-bold.eot?#iefix') format('embedded-opentype'),url('/webGui/styles/clear-sans-bold.woff') format('woff'),url('/webGui/styles/clear-sans-bold.ttf') format('truetype'),url('/webGui/styles/clear-sans-bold.svg#clear-sans-bold') format('svg');
		}
		@font-face{
		font-family:'clear-sans';font-weight:normal;font-style:italic;
		src:url('/webGui/styles/clear-sans-italic.eot');src:url('/webGui/styles/clear-sans-italic.eot?#iefix') format('embedded-opentype'),url('/webGui/styles/clear-sans-italic.woff') format('woff'),url('/webGui/styles/clear-sans-italic.ttf') format('truetype'),url('/webGui/styles/clear-sans-italic.svg#clear-sans-italic') format('svg');
		}
		@font-face{
		font-family:'clear-sans';font-weight:bold;font-style:italic;
		src:url('/webGui/styles/clear-sans-bold-italic.eot');src:url('/webGui/styles/clear-sans-bold-italic.eot?#iefix') format('embedded-opentype'),url('/webGui/styles/clear-sans-bold-italic.woff') format('woff'),url('/webGui/styles/clear-sans-bold-italic.ttf') format('truetype'),url('/webGui/styles/clear-sans-bold-italic.svg#clear-sans-bold-italic') format('svg');
		}
		@font-face{
		font-family:'bitstream';font-weight:normal;font-style:normal;
		src:url('/webGui/styles/bitstream.eot');src:url('/webGui/styles/bitstream.eot?#iefix') format('embedded-opentype'),url('/webGui/styles/bitstream.woff') format('woff'),url('/webGui/styles/bitstream.ttf') format('truetype'),url('/webGui/styles/bitstream.svg#bitstream') format('svg');
		}
		html{font-family:clear-sans;font-size:62.5%;height:100%}
		body{font-size:1.2rem;color:#1c1c1c;background:#f2f2f2;padding:0;margin:0;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
		.logLine{font-family:bitstream;font-size:1.2rem;margin:0 8px;padding:0}
		.logLine.spacing{margin:10px}
		input[type=button],input[type=reset],input[type=submit],button,button[type=button],a.button{font-family:clear-sans;font-size:1.1rem;font-weight:bold;letter-spacing:2px;text-transform:uppercase;margin:10px 12px 10px 0;padding:9px 18px;text-decoration:none;white-space:nowrap;cursor:pointer;outline:none;border-radius:4px;border:0;color:#ff8c2f;background:-webkit-gradient(linear,left top,right top,from(#e22828),to(#ff8c2f)) 0 0 no-repeat,-webkit-gradient(linear,left top,right top,from(#e22828),to(#ff8c2f)) 0 100% no-repeat,-webkit-gradient(linear,left bottom,left top,from(#e22828),to(#e22828)) 0 100% no-repeat,-webkit-gradient(linear,left bottom,left top,from(#ff8c2f),to(#ff8c2f)) 100% 100% no-repeat;background:linear-gradient(90deg,#e22828 0,#ff8c2f) 0 0 no-repeat,linear-gradient(90deg,#e22828 0,#ff8c2f) 0 100% no-repeat,linear-gradient(0deg,#e22828 0,#e22828) 0 100% no-repeat,linear-gradient(0deg,#ff8c2f 0,#ff8c2f) 100% 100% no-repeat;background-size:100% 2px,100% 2px,2px 100%,2px 100%}
		input:hover[type=button],input:hover[type=reset],input:hover[type=submit],button:hover,button:hover[type=button],a.button:hover{color:#f2f2f2;background:-webkit-gradient(linear,left top,right top,from(#e22828),to(#ff8c2f));background:linear-gradient(90deg,#e22828 0,#ff8c2f)}
		input[type=button][disabled],input[type=reset][disabled],input[type=submit][disabled],button[disabled],button[type=button][disabled],a.button[disabled]
		input:hover[type=button][disabled],input:hover[type=reset][disabled],input:hover[type=submit][disabled],button:hover[disabled],button:hover[type=button][disabled],a.button:hover[disabled]
		input:active[type=button][disabled],input:active[type=reset][disabled],input:active[type=submit][disabled],button:active[disabled],button:active[type=button][disabled],a.button:active[disabled]{cursor:default;color:#808080;background:-webkit-gradient(linear,left top,right top,from(#404040),to(#808080)) 0 0 no-repeat,-webkit-gradient(linear,left top,right top,from(#404040),to(#808080)) 0 100% no-repeat,-webkit-gradient(linear,left bottom,left top,from(#404040),to(#404040)) 0 100% no-repeat,-webkit-gradient(linear,left bottom,left top,from(#808080),to(#808080)) 100% 100% no-repeat;background:linear-gradient(90deg,#404040 0,#808080) 0 0 no-repeat,linear-gradient(90deg,#404040 0,#808080) 0 100% no-repeat,linear-gradient(0deg,#404040 0,#404040) 0 100% no-repeat,linear-gradient(0deg,#808080 0,#808080) 100% 100% no-repeat;background-size:100% 2px,100% 2px,2px 100%,2px 100%}
		.centered{text-align:center}
		span.error{color:#F0000C;background-color:#FF9E9E;display:block;width:100%}
		span.warn{color:#E68A00;background-color:#FEEFB3;display:block;width:100%}
		span.system{color:#00529B;background-color:#BDE5F8;display:block;width:100%}
		span.array{color:#4F8A10;background-color:#DFF2BF;display:block;width:100%}
		span.login{color:#D63301;background-color:#FFDDD1;display:block;width:100%}
		span.label{padding:4px 8px;margin-right:10px;border-radius:4px;display:inline;width:auto}
		#button_receiver {position: fixed;left: 0;bottom: 0;width: 100%;text-align: center;background: #f2f2f2;}
	</style>
	<script src="/webGui/javascript/dynamix.js"></script>
	<script type="text/javascript">
		var progressframe = parent.document.getElementById('progressFrame');
		if (progressframe) progressframe.style.zIndex = 10;
		var lastLine = 0;
		var cursor;
		var logSocketName = "";
		var timers = {};
		var log_file = "<?=$_GET["file"];?>";
		var log_search = "<?=$_GET["search"];?>";

		function addLog(logLine) {
		  var scrollTop = (window.pageYOffset !== undefined) ? window.pageYOffset : (document.documentElement || document.body.parentNode).scrollTop;
		  var clientHeight = (document.documentElement || document.body.parentNode).clientHeight;
		  var scrollHeight = (document.documentElement || document.body.parentNode).scrollHeight;
		  var isScrolledToBottom = scrollHeight - clientHeight <= scrollTop + 1;
		  var receiver = window.document.getElementById( 'log_receiver');
		  if (lastLine == 0) {
		    lastLine = receiver.innerHTML.length;

		    cursor = lastLine;
		  }
		  if (logLine.slice(-1) == "\n") {
		    receiver.innerHTML = receiver.innerHTML.slice(0,cursor) + logLine.slice(0,-1) + "<br>";
		    lastLine = receiver.innerHTML.length;
		    cursor = lastLine;
		  }
		  else if (logLine.slice(-1) == "\r") {
		    receiver.innerHTML = receiver.innerHTML.slice(0,cursor) + logLine.slice(0,-1);
		    cursor = lastLine;
		  }
		  else if (logLine.slice(-1) == "\b") {
		    if (logLine.length > 1)
		      receiver.innerHTML = receiver.innerHTML.slice(0,cursor) + logLine.slice(0,-1);
		    cursor += logLine.length-2;
		  }
		  else {
		    receiver.innerHTML += logLine;
		    cursor += logLine.length;
		  }
		  if (isScrolledToBottom) {
		    window.scrollTo(0,receiver.scrollHeight);
		  }
		}
		function addCloseButton() {
		  var done = location.search.split('&').pop().split('=')[1];
		  window.document.getElementById( 'button_receiver').innerHTML += "<button class='logLine' type='button' onclick='" + (inIframe() ? "top.Shadowbox" : "window") + ".close()'>"+decodeURI(done)+"</button>";		}
		function getLogContent()
		{
		  clearTimeout(timers.getLogContent);
		  $.post("<?=$relative_path;?>",{action:'get_log',file:log_file,csrf_token:"<?=$var['csrf_token'];?>", socket_name:logSocketName,search:log_search},function(data) 
		  {
		  	if (data.socket_name) {
		  		logSocketName = data.socket_name;
		  		timers.getLogContent = setTimeout(getLogContent, 100);
		  	} else if (data.error) {
		  		addLog(data.error);
		  	} else {
	    		$.each(data, function(k,v) { 
	    			if(v.length) {
	    				addLog(v);
	    			}
	    		});
		  		timers.getLogContent = setTimeout(getLogContent, 500);
		  	}
		  },'json');
		}
		function inIframe () {
			try {
 		   		return window.self !== window.top;
 		   	} catch (e) {
 		   		return true;
 		   	}
 		}

		$(function() { getLogContent() });
	</script>
</head>

<body class="logLine" onload="addCloseButton();">
	<div id="log_receiver" style="padding-bottom: 60px;">
		<p style='text-align:center'><span class='error label'>Error</span><span class='warn label'>Warning</span><span class='system label'>System</span><span class='array label'>Array</span><span class='login label'>Login</span></p>
	</div>
	<div id="button_receiver">
		<button onclick="window.location = '<?=$relative_path;?>?action=download&file='+log_file+'&search='+log_search">Download</button>	
	</div>
</body>
</html>
<?};?>