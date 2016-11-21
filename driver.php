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
<title>Robot Driver</title>
</head>
<body>
<div class="wrapper">
	<!-- options here -->
	<form id="logout" action="index.php" method="post">
		<input type="submit" name="logout" value="Logout" />
	</form>
	<canvas id='stream'></canvas>
	<section id='readings'>
		<p>Temperature: <span id='temperature'></span></p>
		<p>Light: <span id='light'></span></p>
	</section>
	<!-- <section id='controls'>
		<button id="left">&larr;</button>
		<button id="right">&rarr;</button>
		<button id="accel">A</button>
		<button id="brake">B</button>
	</section> -->
</div>
<script type="text/javascript">
var socket = null;
var isopen = false;
var accepted = false;
var input = {A:0, B:0, L:0, R:0};
var prevCommand = JSON.stringify([0,0]);

var logoutForm = document.getElementById("logout");
var temperature = document.getElementById("temperature");
var light = document.getElementById("light");
// var leftBtn = document.getElementById("left");
// var rightBtn = document.getElementById("right");
// var accelBtn = document.getElementById("accel");
// var brakeBtn = document.getElementById("brake");

document.addEventListener('keydown', function(e) {
	e = e || window.event;
    if (e.keyCode == '38') { // up arrow
    	input.A = 1;
    } else if (e.keyCode == '40') { // down arrow
        input.B = 1;
    } else if (e.keyCode == '37') { // left arrow
       	input.L = 1;
    } else if (e.keyCode == '39') { // right arrow
       	input.R = 1;
    }
});

document.addEventListener('keyup', function(e) {
	e = e || window.event;
    if (e.keyCode == '38') { // up arrow
    	input.A = 0;
    } else if (e.keyCode == '40') { // down arrow
        input.B = 0;
    } else if (e.keyCode == '37') { // left arrow
       	input.L = 0;
    } else if (e.keyCode == '39') { // right arrow
       	input.R = 0;
    }
});

socket = new WebSocket("ws://<?= $_SERVER['SERVER_ADDR'] ?>:9000");
socket.binaryType = "arraybuffer";

socket.onopen = function() {
   	console.log("Connected!");
   	isopen = true;
   	sendDriverRequest("<?= DRIVER_PASSWORD ?>");
};

socket.onmessage = function(e) {
   	if (typeof e.data == "string") {
   		if (accepted) {
      		display(e.data);
      	} else if (e.data == "ACK") {
			accepted = true;
			setInterval(drive, 100);
			console.log("Accepted!");
      	} else if (e.data == "DIE") {
			console.log("Rejected");
			this.close();
      	}
   	}
};

socket.onclose = function(e) {
   	console.log("Connection closed.");
   	socket = null;
   	isopen = false;
   	accepted = false;
};

function sendDriverRequest(request)
{
	socket.send(request);
	console.log("Driver Request Sent!");
}

function drive() 
{
	var command = [0,0];
	command[0] += input.A;
	command[0] -= input.B;
	command[1] -= input.L;
	command[1] += input.R;
	command = JSON.stringify(command);

   	if (isopen && command != prevCommand) {
    	socket.send(command);
    	prevCommand = command;
    	console.log("Command Sent: "+command);             
   	}
}

function display(data)
{
	var readings = JSON.parse(data);
	temperature.innerHTML = readings.t;
	light.innerHTML = readings.l;
}
</script>
</body>
</html>