<?php
const VIEWER_PASSWORD = 'viewer';
const DRIVER_PASSWORD = 'driver';

$controller = true;
if ($_POST['logout']) {
	include('login.php');
} else if (validateViewer()) {
	include('viewer.php');
} else if (validateDriver()) {
	include('driver.php');
} else {
	include('login.php');
}

function validateViewer()
{
	return ($_POST['login'] == 'As Viewer' && $_POST['password'] == VIEWER_PASSWORD);
}

function validateDriver()
{
	return ($_POST['login'] == 'As Driver' && $_POST['password'] == DRIVER_PASSWORD);
}