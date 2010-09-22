<?php
require 'db.php';

$db = new db('mysql:host=127.0.0.1;dbname=test', 'root', 'root');
$db['t1']->replace(array('id'=>7, 'value'=>14));


