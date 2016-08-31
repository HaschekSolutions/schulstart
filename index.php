<?php session_start(); include_once('inc/functions.php');include_once('inc/html.php');
  $html = new HTML;
  
  include_once('inc/Encoding.php');
		use \ForceUTF8\Encoding;
    
    function toISO($s)
    {
      return Encoding::toLatin1($s);
    }
    
    function toUTF8($s)
    {
      return Encoding::fixUTF8($s);
    }
    
  
  //error_reporting(E_ALL & ~E_NOTICE);
  //ini_set('display_errors','On');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">

<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/> 
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>CSV 2 AD</title>

    <!-- Bootstrap -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    
    <link href="css/csvgen.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body>
    

    <div class="container theme-showcase" role="main">

      <!-- Main jumbotron for a primary marketing message or call to action -->
      <div class="jumbotron">
        <h1>CSV 2 AD</h1>
        <p>CSV2 AD ist eine Hilfestellung für Schuladmins, die jedes Jahr viele Schüler und Gruppen einpflegen müssen. Man läd das CSV (Export aus Sokrates) hinauf, wählt einige Einstellungen und erhält dann mehrere Scripts, die man auf den Servern einfach ausführen kann um benutzer/gruppen usw zu setzen.</p>
      </div>
      
      <?php
        
        if($_POST['submit']=='Berechnung starten')
        {
            echo controller();
        }
        else include('form.html');
        
        ?>
      
      




    </div>

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="js/bootstrap.min.js"></script>
  </body>
</html>