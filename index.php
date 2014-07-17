<html>
	<head>
		<link rel="stylesheet" type="text/css" href="theme.css">
	</head>
	<body>
		
		<div class="background">
    		<img src="banner_small.gif" alt="Banner Image"/>
		</div>

		<?php
      		$currentDomain = $_SERVER['HTTP_HOST'];
      		//start the session
      		if (session_id() == "") {
      			session_start();
      		}

      		//if the user has access, move to Pour
      		if (isset($_SESSION['access'])) {
       			echo "<h3>Loading, please wait...</h3>";
        		echo "<script> window.location = '/pour.php' </script>";
        		die();
      		}

      		//if the state is not done (to be set later)
      		else if (!isset($_GET['state']) || $_GET['state'] != "done") {

        		//the first call to google, this recieves the code to be used later
        		$state = 'done';
        		$cli_id = '1006161612314-1qct7m1r0bqt5ecb2sntrci253dv41s1.apps.googleusercontent.com';
        		$sec = 'Uka8meQZbY0KMFCnQ6nYb0Tw';
        		$call = 'http://' . $currentDomain . '/index.php';
        		$scope = 'email%20profile%20https://www.googleapis.com/auth/admin.directory.user';
        		$url = "https://accounts.google.com/o/oauth2/auth?state=$state&scope=$scope&redirect_uri=$call&response_type=code&client_id=$cli_id&approval_prompt=force&access_type=offline";
        		//echo "<a href=$url>Log In With Google</a>";
        		echo 	"<div style=\"width: 200px; height: 46px; position: absolute; top:0; bottom: 0; left: 0; right: 0; margin: auto;\">
        					<a href=$url class=\"login_button3\" style=\"height:46px; line-height:46px; width:200px\">Log In With Google</a>
						</div>";
      		}
      		else if(isset($_GET['code'])) {
        		
	        	//this step takes a bit...
	        	echo "<h3>Loading, please wait.... </h3>";

	        	//use the code in the url to get the access token try to get an access token
	        	$code = $_GET['code'];
	        	$url = 'https://accounts.google.com/o/oauth2/token';
	        	$params = array("code" => $code,
	            	"client_id" => "1006161612314-1qct7m1r0bqt5ecb2sntrci253dv41s1.apps.googleusercontent.com",
	            	"client_secret" => "Uka8meQZbY0KMFCnQ6nYb0Tw",
	            	"redirect_uri" => "http://" . $currentDomain . "/index.php",
	            	"grant_type" => "authorization_code"
	        	);
	        	$options = array(
	            	'http' => array(
	                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
	                'method' => 'POST',
	                'content' => http_build_query($params),
	            	),
	        	);
	        	$context = stream_context_create($options);
	        	$result = file_get_contents($url, false, $context);

	        	$access_obj = json_decode($result);
	        	$_SESSION['access'] = $access_obj->{'access_token'};
	        	
	        	//get the refresh token, used in refresh.php -----------------------------------------------------------
	        	//$_SESSION['refresh'] = $access_obj->{'refresh_token'};

		        //get the user's information from the access_token
		        $ac_tok = $_SESSION['access'];
		        $getUrl = "https://www.googleapis.com/oauth2/v1/userinfo?access_token=$ac_tok";
		        $getResponse = file_get_contents($getUrl);
		        $get = json_decode($getResponse);
		        $_SESSION['name'] = $get->{'name'};
		        $_SESSION['email'] = $get->{'email'};

		        //redirect to this url with the params state=done
		        echo "<script> window.location ='\?state=done' </script>";
		        die();
      		}
      		else {
        		//if there is no code, no access, and state is done... then just return to the homepage without a state
        		echo "<script> window.location ='\' </script>";
        		die();
      		}
      	?>
</html>