<?php
require_once('./fonctionsMongo.php');
require_once('./config.php');
require_once("./reponses.php");

if((isset($_POST['filtre'])) && (isset(($_POST['collection'])))){
    $cnx= ouvrirCnxMongo();
    $collection= selectionCollection($cnx,DATABASE, $_POST['collection']);
    $reponse= selectionTypeRequete($collection, $_POST['filtre']);
    echo json_encode($reponse);
}else{
    echo ReturnReponse(999,"Pas de parametres _POST");
}