<html>
	<head>
		<link rel="shortcut icon" href="favicon.ico">
    <link rel="stylesheet" type="text/css" href="theme.css">
	</head>
	<body>
		<div class="background">
    		<img src="banner_small.gif" alt="Banner Image"/>
    		<div class="text_over">
    			<p class="tight_text"1>
    				<?php 
    					if (session_id() == "") {
      						session_start();
      					}
      					if (isset($_SESSION['access'])) {
    						echo $_SESSION['name'] . "<br>" . $_SESSION['email'];
    				?> 
    			</p>
    				<a href="https://www.google.com/accounts/Logout?continue=https://appengine.google.com/_ah/logout?continue=http://localhost/logout.php"
                    class="black_button" style="padding-right: 40px; padding-left: 54px;">Logout</a>
					<?php
    					} else echo "</p>";
    				?>	
			</div>
		</div>
	</body>
</html>