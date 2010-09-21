<?php
require 'db.php';

$db = new db('mysql:host=127.0.0.1;dbname=test', 'root', 'root');

var_dump(count($db['t1']->where(array('id:>'=>0))));


