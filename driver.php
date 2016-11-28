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
	<fieldset>Alert When Light Drops Below: <input type="text" id="lAlertMin" /></fieldset>
	<fieldset>Alert When Light Exceeds: <input type="text" id="lAlertMax" /></fieldset>
	<fieldset>Alert When Temperature Drops Below: <input type="text" id="tAlertMin" /></fieldset>
	<fieldset>Alert When Temperature Exceeds: <input type="text" id="tAlertMax" /></fieldset>
	<fieldset>Graph Minimum Light Value: <input type="text" id="lMin" /></fieldset>
	<fieldset>Graph Maximum Light Value: <input type="text" id="lMax" /></fieldset>
	<fieldset>Graph Minimum Temperature Value: <input type="text" id="tMin" /></fieldset>
	<fieldset>Graph Maximum Temperature Value: <input type="text" id="tMax" /></fieldset>
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
	<canvas id="controls">
		<div id='shadow'></div>
	</canvas>
</div>
<script type="text/javascript">
var GRAPH_POINTS = 10;
var L_MIN = 0;
var L_MAX = 1;
var T_MIN = 0;
var T_MAX = 1;

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
var controls = document.getElementById("controls");

function mousePos(e) {
    var mouseX, mouseY;

    if (e.offsetX) {
        mouseX = e.offsetX;
        mouseY = e.offsetY;
    } else if (e.layerX) {
        mouseX = e.layerX;
        mouseY = e.layerY;
    }

	if (mouseX > 0 && mouseX <= controls.width && mouseY > 0 && mouseY <= controls.height)
		return [mouseX, mouseY];
	else
		return false; 
}

document.addEventListener('mousedown', function() {
	mousedown = true;
	console.log(mousePos(e));
});

document.addEventListener('mouseup', function() {
	mousedown = false;
});

document.addEventListener('mousemove', function() {
	if (mousedown)
		console.log(mousePos(e));
});

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
	drawGraph(graphCtx, graphCanvas.width, graphCanvas.height, tReadings, '#C01232', T_MIN, T_MAX);
	drawGraph(graphCtx, graphCanvas.width, graphCanvas.height, lReadings, '#0B6287', L_MIN, L_MAX);
}

function drawGraph(ctx, width, height, points, color, min, max)
{
	ctx.beginPath();
	ctx.strokeStyle = color;
	ctx.lineWidth = 10;
	ctx.lineJoin = 'round';
	for (let i = 0; i < points.length; i++) {
		let x = i*width/GRAPH_POINTS;
		let y = height - (height*points[i]/max);
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

