<?php

$dirname = "exports";
if(!file_exists($dirname)) {
   mkdir($dirname, 0777, true);
}
else
{
    $filesindir = scandir($dirname);

    if(count($filesindir) != 0) {
	foreach ($filesindir as &$file) {
	   if(is_file("$dirname/$file")
		&&is_writeable("$dirname/$file")) {
		
		if(time()-filemtime("$dirname/$file") > 3600)
		   unlink("$dirname/$file");
	   }
	
	}
    }
}

ini_set('max_execution_time', 60*60);

if(isset($_GET["username"]))
{
    include "include/functions.php";
    $time1 = microtime(true);
    $result = exportuser($_GET["username"]);
    $timeelapsed = microtime(true) - $time1;
}
?>

<!DOCTYPE html>
<html>
    <head>
        <title>Kitsu->MAL exporter</title>
    </head>

    <body>
        This website tries to convert your kitsu account so you can use it in MAL, or any other sites that have support for MAL's exporter.<br>
        Note: this will take <i>forever</i> (meaning a couple minutes depending on how large your list is), because I wrote this in PHP and it fucking sucks.<br><br>
        <form id="userform" method="GET" action="index.php">
            <input name="username" type="text" placeholder="Kitsu Username" width="200px" autofocus><br>
            <input id="submitbrn" name="submitted" type="submit" value="Export">
        </form><br>

        <div id="appendhere"></div>

        Made by <a href="https://kitsu.io/users/stepper">stepper</a> (if you want to make this site pretty, please do so.)

        <?php
            // echos the link here.
            if(isset($timeelapsed))
            echo "<br>Time elapsed: " . strval($timeelapsed);

            if(isset($result))
            {
                echo <<<HTML
<br>Download: <a href="exports/$result" download>this thing here</a>
HTML;
            }

        ?>


    <script src="main.js"></script>
    </body>
</html>
