<?php
//Inclusion du driver Mongo
require_once '../vendor/autoload.php';
use MongoDB\Client as Mongo;

//Inclusion des fichiers necessaires au programme
require_once ('./config.php');
require_once("./reponses.php");

function ouvrirCnxMongo(){
    try{
        $clientMongo = new Mongo(CONNEXION_MONGO);
        return $clientMongo;
    }catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e){
        echo ReturnReponse(998, "pb de connexion Base de données");
    }
}

function selectionCollection($cnx, $database, $stringCollection){
    try{
        $collection = $cnx->$database->$stringCollection;
        return $collection;
    }catch(MongoDB\Driver\Exception\ConnectionTimeoutException $e){
        echo ReturnReponse(998, "pb de connexion Base de données", $e);
    }
}

function selectionTypeRequete($collectionMongo, $filterJSON){
    $recherche= json_decode($filterJSON, true);
    if(!$recherche){
        return ReturnReponse(605, "json recu incorrect" );
    }else{
        switch ($recherche){
            case isset($recherche['Requetes']):
                return reqSelect($collectionMongo, $recherche);
                break;
            case isset($recherche['Sommes']):
                return RechercheSomme($collectionMongo, $recherche);
                break;
            default:
                return ReturnReponse(605, "json recu incorrect" );
                break;
        }
    }
}

function recupererEnsemblesDesBases(){
    try{
        $clientMongo = new Mongo(CONNEXION_MONGO);
        $dbs = $clientMongo->listDatabases();
        $listeDbs= array();
        foreach($dbs as $db){
            array_push($listeDbs, $db['name']);
        }
        return $listeDbs;
    }catch(MongoDB\Driver\Exception\ConnectionTimeoutException $e){
        echo ReturnReponse(998, "pb de connexion Base de données", $e);
    }
}

function recupererToutesCollections($cnx, $database){
    try{
        $collections = $cnx->$database->listCollections();
        $listeCollections= array();
        foreach($collections as $collection){
            array_push($listeCollections, $collection['name']);
        }
        return $listeCollections;
    }catch(MongoDB\Driver\Exception\ConnectionTimeoutException $e){
        echo ReturnReponse(998, "pb de connexion Base de données", $e);
    }
}

function reqSelect($collectionMongo, $recherche){
        $requete=array();
        for($i=0; $i<count($recherche['Requetes']); $i++){
            if(!isset($recherche['Requetes'][$i]['operateur']) || !isset($recherche['Requetes'][$i]['rubrique']) || !isset($recherche['Requetes'][$i]['valeur'])){
                return ReturnReponse(605, "json recu incorrect" );
            }else{
                if($recherche['Requetes'][$i]['operateur']=="="){
                    if($i==0){
                        $requete=array($recherche['Requetes'][$i]['rubrique'] => $recherche['Requetes'][$i]['valeur']);
                    }else{
                        //inclure le AND
                        $requete=array('$and' => array($requete, array($recherche['Requetes'][$i]['rubrique'] => $recherche['Requetes'][$i]['valeur'])));
                    }
                }elseif($recherche['Requetes'][$i]['operateur']=="%"){
                    //Inclure une regex
                    if($i==0){
                        $requete=array($recherche['Requetes'][$i]['rubrique'] => new MongoDB\BSON\Regex($recherche['Requetes'][$i]['valeur'],'i'));
                    }else{
                        //inclure le AND
                        $requete=array('$and' => array($requete, array($recherche['Requetes'][$i]['rubrique'] => new MongoDB\BSON\Regex($recherche['Requetes'][$i]['valeur'],'i')) ));
                    }
                }else{
                    return ReturnReponse(605, "operateur inconnu" );
                }
            }
        }
        try{
            $resultat = $collectionMongo->find($requete);
            if($resultat){
                $tableauRetour=array();
                foreach($resultat as $res){
                    array_push($tableauRetour, $res);
                }
                return ReturnReponse(0, "Reponse OK", $tableauRetour);
            }else{
                return ReturnReponse(0, "Aucune donnée");
            }
        }catch(MongoDB\Driver\Exception\ConnectionTimeoutException $e){
            echo ReturnReponse(998, "pb de connexion Base de données", $e);
        }
}

function rechercheSomme($collectionMongo, $recherche){
    $match=recursiveCreeAnd($recherche, 0, array());
    $match=['$match' => $match];
    return $match;

}

function recursiveCreeAnd($recherche, $compteur, $tableau){
    if($compteur == count ($recherche['Sommes'])){
        return $tableau;
    }else{
        if($recherche['Sommes'][$compteur]['operateur']=="="){
            if(isset($recherche['Sommes'][$compteur+1]['operateur']) && $recherche['Sommes'][$compteur+1]['operateur']=="="){
                //Si le suivant est un egal alors on crée un and
                $tableau= [$recherche['Sommes'][$compteur]['rubrique'] => [ $recherche['Sommes'][$compteur]['valeur']]];
                return ['$and' => [$tableau, recursiveCreeAnd($recherche, $compteur+1, $tableau)] ];
            }else if(isset($recherche['Sommes'][$compteur+1]['operateur']) && ($recherche['Sommes'][$compteur+1]['operateur']=="OU" || ($recherche['Sommes'][$compteur+1]['operateur']=="ou"))){
                //Gestion des OU
                $tableau= [$recherche['Sommes'][$compteur]['rubrique'] => [ $recherche['Sommes'][$compteur]['valeur']]];
                return ['$or' => [$tableau, recursiveCreeAnd($recherche, $compteur+2, $tableau)] ];
            }else if(isset($recherche['Sommes'][$compteur+1]['operateur']) && ($recherche['Sommes'][$compteur+1]['operateur']==">" ||($recherche['Sommes'][$compteur+1]['operateur']=="gte"))){
                $tableau= [$recherche['Sommes'][$compteur]['rubrique'] => [ $recherche['Sommes'][$compteur]['valeur']]];
                $gte= [$recherche['Sommes'][$compteur+1]['rubrique'] => ['gte' => [ $recherche['Sommes'][$compteur+1]['valeur']]]];
                return ['$and' => [$tableau, recursiveCreeAnd($recherche, $compteur+1, $gte)]];
            }else{
                $tableau= [$recherche['Sommes'][$compteur]['rubrique'] => [ $recherche['Sommes'][$compteur]['valeur']]];
                return recursiveCreeAnd($recherche, $compteur+1, $tableau);
            }
        }else{
            if(isset($recherche['Sommes'][$compteur]['operateur']) && ($recherche['Sommes'][$compteur]['operateur']==">" || ($recherche['Sommes'][$compteur]['operateur']=="gte"))){
                $tableau= [$recherche['Sommes'][$compteur]['rubrique'] => ['gte' => [ $recherche['Sommes'][$compteur]['valeur']]]];
                return recursiveCreeAnd($recherche, $compteur+1, $tableau);
            }else{
                return recursiveCreeAnd($recherche, $compteur+1, $tableau);
            }
        }
    }
}