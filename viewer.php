<?php
if (!$controller) {
	echo 'Nice try!';
	exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
<title>Robot Viewer</title>
</head>
<body>
<div class="wrapper">
	<!-- options here -->
	<form action="index.php" method="post">
		<input type="submit" name="logout" value="Logout" />
	</form>
	<canvas id='stream'></canvas>
	<section id='readings'>
		<p>Temperature: <span id='temperature'></span></p>
		<p>Light: <span id='light'></span></p>
	</section>
</div>
<script type="text/javascript">
var socket = null;
var isopen = false;

var logoutForm = document.getElementById("logout");
var temperature = document.getElementById("temperature");
var light = document.getElementById("light");

socket = new WebSocket("ws://<?= $_SERVER['SERVER_ADDR'] ?>:9000");
socket.binaryType = "arraybuffer";

socket.onopen = function() {
   	console.log("Connected!");
   	isopen = true;
};

socket.onmessage = function(e) {
   	if (typeof e.data == "string") {
      	display(e.data);
   	}
};

socket.onclose = function(e) {
   	console.log("Connection closed.");
   	socket = null;
   	isopen = false;
};

function display(data)
{
	var readings = JSON.parse(data);
	temperature.innerHTML = readings.t;
	light.innerHTML = readings.l;
}
</script>
</body>
</html>