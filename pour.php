<html>
	<head>
		<link rel="stylesheet" type="text/css" href="theme.css">
		<script src="pour.js"></script>	
	</head>
	<?php 
		if (session_id() == "") {
      		session_start();
      	}
		echo "<body onload=\"updateUser(" . $_SESSION['name'] . ", " . $_SESSION['email'] . ", '1111')\">"; ?>
		
		<div class="background">
    		<img src="banner_small.gif" alt="Banner Image"/>
    		<div class="text_over">
    			<!--<p class="tight_text" id="user"></p>-->
    			<p class="tight_text"1>
    				<?php 
    					echo $_SESSION['name'] . "<br>" . $_SESSION['email']; 
    				?>
    			</p>
				<a href="logout.php" class="login_button3" style="    padding-right: 40px;
    padding-left: 54px;">Logout</a>
			</div>
		</div>
		
		<?php 
			if(isset($_GET['send'])) {
				$color = $_GET["color"];
				$amt = $_GET["amt"];
				$user = $_GET["euid"];
				$token = $_GET["token"];
		?> 
			<div class="center">
				<p>Thank you for your order: <br>
		<?php
			echo $user . "<br>Type: " . $color . 
				"&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Amount: " . $amt . " oz.";
		?>
				</p>
				<form action="http://localhost/pour.php">
    				<input type="submit" value="Pour More" class="sub_button">
				</form>
				<p>Drink responsibly</p>
			</div>
			<!--<script language='javascript'> <?php reloadGlass('$amt', '$color'); ?></script>-->
		<?php
			} else {
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
						<td></td>
					</tr>
					<tr>
						<td>
							<button class="full_button" onmousedown="updateBottle(0,0)" 
						  	  onmouseup="updateBottle(1,0)">
						  		<img src="upBottle.gif" id="upBottle" />
						  	</button>
						</td>
						<form method="GET" action="pour.php?=$_SERVER['PHP_SELF'];?>" onsubmit>
							<td>
								<input type="submit" id="sub" class="sub_button" value="Pour&#10;8 oz!" 
									align="center" name="send" onclick="updateGlass()">
								<input type="text" id="color"style="display: none;" value="Light" name="color">
								<input type="text" id="amt" style="display: none;" value="8" name="amt">
								<input type="text" id="euid" style="display: none;" name="euid">
								<input type="text" id="token" style="display: none;" name="token">
							</td>
						</form>	
					</tr>
					<tr>
						<td>
							<button class="full_button" onmousedown="updateBottle(0,1)" onmouseup="updateBottle(1,1)">
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