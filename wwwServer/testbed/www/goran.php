
<?php
# 

### GORAN ...
##use Spiral\Goridge;
use PhpJsonMarshaller\Goran;
require "./vendor/autoload.php";
###--------------------------------
use PhpJsonMarshaller\Decoder\ClassDecoder;
use PhpJsonMarshaller\Marshaller\JsonMarshaller;
use PhpJsonMarshaller\Reader\DoctrineAnnotationReader;
#use PhpJsonMarshaller\Marshaller;
#require "./vendor/php-json-marshaller/vendor/autoload.php";


## GORAN END




## GORAN TEST
$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);
$adir= dirname("Goridge\SocketRelay");
$xdir = dirname(__DIR__);
echo "<h3> VENDOR=$vendorDir XDIR=$xdir....  BASEDIR=$baseDir ADIR=$adir </h3>";

###############$rpc = new Goridge\RPC(new Goridge\SocketRelay("172.18.0.3", 1200)); ### 6001));
################echo $rpc->call("App.Hi", "Antony");
$rpc = new Goridge\SocketRelay("172.18.0.3", 1200);
## next is 354
##$doctr = new PhpJsonMarshaller\Reader\DoctrineAnnotationReader();

$doctr = new Reader\DoctrineAnnotationReader();
$dcdr  = new PhpJsonMarshaller\ClassDecoder($doctr);
$mrsh  = new Marshaller\JsonMarshaller($dcdr);

$marshaller = new Marshaller\JsonMarshaller(new ClassDecoder(new DoctrineAnnotationReader()));
$json = '{
    "id": 12345,
    "name": "Anuj"
}';
// Marshall the class
$marshalled = $marshaller->marshall($user);

echo $rpc->send($marshalled);  ### ("Antony");
