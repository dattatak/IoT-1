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
<link rel="stylesheet" type="text/css" href="styles.css">
</head>
<body>
<div id='menu', class="wrapper">
	<button id='closeBtn'>Close</button><br>
	<fieldset>Alert When Light Drops Below: 
		<input type="text" id="lAlertMin" onchange="applySetting(this, alerts, 'lMin')"/>
	</fieldset>
	<fieldset>Alert When Light Exceeds: 
		<input type="text" id="lAlertMax" onchange="applySetting(this, alerts, 'lMax')"/>
	</fieldset>
	<fieldset>Alert When Temperature Drops Below: 
		<input type="text" id="tAlertMin" onchange="applySetting(this, alerts, 'tMin')"/>
	</fieldset>
	<fieldset>Alert When Temperature Exceeds: 
		<input type="text" id="tAlertMax" onchange="applySetting(this, alerts, 'tMax')"/>
	</fieldset>
	<fieldset>Graph Minimum Light Value: 
		<input type="text" id="lMin" onchange="applySetting(this, graphLimits, 'lMin')"/>
	</fieldset>
	<fieldset>Graph Maximum Light Value: 
		<input type="text" id="lMax" onchange="applySetting(this, graphLimits, 'lMax')"/>
	</fieldset>
	<fieldset>Graph Minimum Temperature Value: 
		<input type="text" id="tMin" onchange="applySetting(this, graphLimits, 'tMin')"/>
	</fieldset>
	<fieldset>Graph Maximum Temperature Value: 
		<input type="text" id="tMax" onchange="applySetting(this, graphLimits, 'tMax')"/>
	</fieldset>
</div>
<div class="wrapper">
	<form id="logout" action="index.php" method="post">
		<input type="submit" name="logout" value="Logout" />
	</form>
  	<button id='menuBtn'>Menu</button>
	<canvas id='stream'></canvas>
	<section id='readings'>
		<p class='temperature'>Temperature: <span id='temperature'></span></p>
		<p class='light'>Light: <span id='light'></span></p>
	</section>
  	<canvas id='graph' width='1080px' height='270px'></canvas>
	<table id='controls'>
		<tr><td></td><td id='accel'>A</td><td></td></tr>
		<tr><td id='left'>L</td><td></td><td id='right'>R</td></tr>
		<tr><td></td><td id='brake'>B</td><td></td></tr>
	</table>
</div>
<script type="text/javascript">
const GRAPH_POINTS = 10;

var alerts = {
	lMin: 0,
	lMax: 9999,
	tMin: 0,
	tMax: 9999
}

var graphLimits = {
	lMin: 0,
	lMax: 1,
	tMin: 0,
	tMax: 1
}

var alertLimit = 0;
var socket = null;
var isopen = false;
var accepted = false;
var mousedown = false;
var input = {A:0, B:0, L:0, R:0};
var prevCommand = JSON.stringify([0,0]);

var lReadings = [];
var tReadings = [];

var logoutForm = document.getElementById("logout");
var temperature = document.getElementById("temperature");
var light = document.getElementById("light");
var graphCanvas = document.getElementById("graph");
var menu = document.getElementById("menu");
var menuBtn = document.getElementById("menuBtn");
var closeBtn = document.getElementById("closeBtn");

var accel = document.getElementById("accel");
var brake = document.getElementById("brake");
var left = document.getElementById("left");
var right = document.getElementById("right");

accel.addEventListener('mousedown', function() {
	input = {A:1, B:0, L:0, R:0};
});
brake.addEventListener('mousedown', function() {
	input = {A:0, B:1, L:0, R:0};
});
left.addEventListener('mousedown', function() {
	input = {A:0, B:0, L:1, R:0};
});
right.addEventListener('mousedown', function() {
	input = {A:0, B:0, L:0, R:1};
});
document.addEventListener('mouseup', function() {
	input = {A:0, B:0, L:0, R:0};
});
accel.addEventListener('touchstart', function() {
	input = {A:1, B:0, L:0, R:0};
});
brake.addEventListener('touchstart', function() {
	input = {A:0, B:1, L:0, R:0};
});
left.addEventListener('touchstart', function() {
	input = {A:0, B:0, L:1, R:0};
});
right.addEventListener('touchstart', function() {
	input = {A:0, B:0, L:0, R:1};
});
document.addEventListener('touchend', function() {
	input = {A:0, B:0, L:0, R:0};
});

function applySetting(input, obj, setting)
{
	if (!isNaN(Number(input.value))) {
		obj[setting] = Number(input.value);
		console.log("Changed "+setting+" to "+input.value);
	} else {
		console.log("Invalid input");
	}
}

menuBtn.addEventListener('click', function() {
	menu.className = "wrapper open"
});
closeBtn.addEventListener('click', function() {
	menu.className = "wrapper"
});


var graphCtx = graphCanvas.getContext("2d");

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
			logoutForm.submit();
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
	temperature.innerHTML = Number(readings.t).toPrecision(6);
	light.innerHTML = Number(readings.l).toPrecision(6);

	tReadings.push(readings.t);
	lReadings.push(readings.l);
	if (tReadings.length > GRAPH_POINTS+1)
		tReadings.shift();
	if (lReadings.length > GRAPH_POINTS+1)
		lReadings.shift();
  
	graphCtx.clearRect(0, 0, graphCanvas.width, graphCanvas.height);
	drawGraph(graphCtx, graphCanvas.width, graphCanvas.height, tReadings, '#C01232', graphLimits.tMin, graphLimits.tMax);
	drawGraph(graphCtx, graphCanvas.width, graphCanvas.height, lReadings, '#0B6287', graphLimits.lMin, graphLimits.lMax);
	
	// check alerts
	if (alertLimit <= 0) {
		if (readings.t < alerts.tMin) {
			alert("Attention: Temperature is " + readings.t);
			alertLimit = 10;
		}
		if (readings.t > alerts.tMax) {
			alert("Attention: Temperature is " + readings.t);
			alertLimit = 10;
		}
		if (readings.l < alerts.lMin) {
			alert("Attention: Light is " + readings.l);
			alertLimit = 10;
		}
		if (readings.l > alerts.lMax) {
			alert("Attention: Light is " + readings.l);
			alertLimit = 10;
		}
	} else {
		alertLimit -= 1;
	}
}

function drawGraph(ctx, width, height, points, color, min, max)
{
	ctx.beginPath();
	ctx.strokeStyle = color;
	ctx.lineWidth = 10;
	ctx.lineJoin = 'round';
	for (let i = 0; i < points.length; i++) {
		let x = i*width/GRAPH_POINTS;
		let y = height - (height/(max - min))*(points[i] - min);
		if (i == 0)
			ctx.moveTo(x,y);
		else
			ctx.lineTo(x,y);
		ctx.stroke();
	}
}
</script>
</body>
</html>

