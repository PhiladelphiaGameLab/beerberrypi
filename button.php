<?php 
	include 'header.php';
?>
<html>
	<head>
		<title>BeerBerry Pi</title>
		<link rel="stylesheet" type="text/css" href="theme.css">
	</head>

	<body >	
		<?php 
			if(isset($_GET['send'])) {
				$handle = fopen("/home/pi/pour.bool", "w");
				fwrite($handle, 12);
				fclose($handle);
			}	
		?>	
		<div style="width: 200px; height: 46px; position: absolute; top:0; bottom: 0; left: 0; right: 0; margin: auto;">
        	<form method="GET" action="button.php?=$_SERVER['PHP_SELF'];?>" onsubmit>
				<td>
					<input type="submit" class="round_button" value="POUR" 
						align="center" name="send">
				</td>
			</form>	
		</div>
    </body>
</html>