<?php

$sname= "localhost";
$unmae= "KAADMIN";
$password= "KeretaApiJaya34";

$db_name= "kereta_api";

$conn= mysqli_connect($sname, $unmae, $password, $db_name);

if (!$conn) {
    "Koneksi GAGAL : " . mysqli_connect_error();
}

?>