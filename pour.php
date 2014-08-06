<?php 
	include 'header.php'; 
	include "BpiMongoConn.php";
	if (!isset($_SESSION['access'])) {
			echo "<script> window.location = '/index.php' </script>";
        	die();
    } else {
?>

<html>
	<head>
		<title>BeerBerry Pi</title>
		<link rel="stylesheet" type="text/css" href="theme.css">
		<script src="pour.js"></script>	
	</head>

	<body >		
		
		<?php 
			$conn = new BpiMongoConn($_SESSION['email']);
			if(isset($_GET['send'])) {
				$color = $_GET["color"];
				$amt = $_GET["amt"];
				$user = $_SESSION['email'];
				$token = $_SESSION['token'];
				$conn = new BpiMongoConn($user);
				if($conn->drink($amt)) {
					
		?> 
			<div class="center">
				<p>Thank you for your order: <br>
		<?php
					echo $_SESSION['name'] . "<br>Type: " . $color . 
						"&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Amount: " . $amt . " oz.";
				} else {				
		?>
			<div class="center">
				<p>Insufficient funds. Your current balance is:
		<?php
					echo $conn->get_time_balance(). " oz.";
				}
		?>
				</p>
				<form action="http://localhost/pour.php">
    				<input type="submit" value="Return" class="sub_button">
				</form>
				<p>Drink responsibly</p>
			</div>
			<!--<script language='javascript'> <?php reloadGlass('$amt', '$color'); ?></script>-->
		<?php
			} else {
				$balance = $conn->get_time_balance();
		?>
				<table class="center_block">
					<tr>	
						<td>
							<button type="button" class="half_button" id="light_button" 
							  onclick="updateColor('Light')" style="text-decoration:underline">
								<b>Light<br>Beer</b>
							</button>
							<button type="button" class="half_button" id="dark_button" 
							  onclick="updateColor('Dark')">
								<b>Dark<br>Beer</b>
							</button>
						</td>	
						<td rowspan="3"><img src="glass/light/8.gif" id="glass"/></td>
						<td style="text-align:center"><b>Account balance:<br>
							<span id="bal"><?php echo $balance ?></span> oz.</b>
						</td>
					</tr>
					<tr>
						<td>
							<button class="full_button" onmousedown="updateBottle(0,0)" 
						  	  onmouseup="updateBottle(1,0)" onmouseleave="updateBottle(1,0)">
						  		<img src="upBottle.gif" id="upBottle" />
						  	</button>
						</td>
						<form method="GET" action="pour.php?=$_SERVER['PHP_SELF'];?>" onsubmit>
							<td>
								<?php if($balance >= 8) { ?>
								<input type="submit" id="sub" class="sub_button" value="Pour&#10;8 oz!" 
									align="center" name="send" onclick="updateGlass()">
								<?php } else { ?>
								<input type="submit" id="sub" class="sub_button" value="Pour&#10;8 oz!" 
									align="center" name="send" disabled>
								<?php } ?>
								<input type="text" id="color"style="display: none;" value="Light" name="color">
								<input type="text" id="amt" style="display: none;" value="8" name="amt">
							</td>
						</form>	
					</tr>
					<tr>
						<td>
							<button class="full_button" onmousedown="updateBottle(0,1)" 
								onmouseup="updateBottle(1,1)" onmouseleave="updateBottle(1,1)">
								<img src="downBottle.gif" id="downBottle"/>
							</button>
						</td>
						<td></td>
					</tr>
				</table>
		<?php
			}
		?>
	</body>
</html>

<?php
	}
?>