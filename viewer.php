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

var lReadings = [];
var tReadings = [];

var logoutForm = document.getElementById("logout");
var temperature = document.getElementById("temperature");
var light = document.getElementById("light");
var graphCanvas = document.getElementById("graph");
var menu = document.getElementById("menu");
var menuBtn = document.getElementById("menuBtn");
var closeBtn = document.getElementById("closeBtn");

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

socket = new WebSocket("ws://<?= $_SERVER['SERVER_ADDR'] ?>:9000");
socket.binaryType = "arraybuffer";

socket.onopen = function() {
   	console.log("Connected!");
   	isopen = true;
};

socket.onmessage = function(e) {
   	if (typeof e.data == "string")
      		display(e.data);
};

socket.onclose = function(e) {
   	console.log("Connection closed.");
   	socket = null;
   	isopen = false;
};

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

