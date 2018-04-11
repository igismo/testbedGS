<?php
# 
#namespace PhpJsonMarshaller;
require "/var/www/vendor/phpJsonMarshaller/vendor/autoload.php";
use PhpJsonMarshaller\Decoder\ClassDecoder;
use PhpJsonMarshaller\Marshaller\JsonMarshaller;
use PhpJsonMarshaller\Reader\DoctrineAnnotationReader;
#use Testbed\TbMarshall;


#spl_autoload_register(function ($class_name) {
#    include $class_name . '.php';
#});

#.\vendor\phpJsonMarshaller\source::
#TbMarshall();

\Doctrine\Common\Annotations\AnnotationRegistry::registerAutoloadNamespace(
'PhpJsonMarshaller\Annotations', "/var/www/vendor/phpJsonMarshaller/source/Annotations");
\Doctrine\Common\Annotations\AnnotationRegistry::registerAutoloadNamespace(
'PhpJsonMarshaller\Annotations\MarshallProperty', "/var/www/vendor/phpJsonMarshaller/source/Annotations");

## GORAN TEST
$vendorDir = dirname(dirname(__FILE__));
$baseDir = dirname($vendorDir);
echo "<h3> VENDOR=$vendorDir/vendor  BASEDIR=$baseDir </h3>";

$json = '{
    "id": 12345,
    "name": "Anuj"
}';
##echo $rpc->send($marshalled);  ### ("Antony");
#
$marshaller = new JsonMarshaller(new ClassDecoder(new DoctrineAnnotationReader()));
#
#// Notice the fully qualified namespace!
##$user = $marshaller->unmarshall($json, 'TbUser');
$user = $marshaller->unmarshall($json, '\\PhpJsonMarshaller\\TbUser1');
#// Use the new class
echo $user->getName(); // (string) 'Anuj'
#
#// Marshall the class
$marshalled = $marshaller->marshall($user);
#
#// $json and $marshalled are both json_encoded string holding the same data
$json == $marshalled;
echo "<h3> zzzzzzz USER=$marshalled</h3>";
