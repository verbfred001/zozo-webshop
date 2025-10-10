<?php
require_once 'vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// De URL waarvoor je een QR-code wilt maken
$url = 'https://maxice.be/9523aP/MyRi@m/8_xr';

// Maak een nieuwe QR-code instantie
$qrCode = new QrCode($url);

// Stel de grootte in (optioneel)
$qrCode->setSize(300);

// Stel de foutcorrectie niveau in (optioneel)
$qrCode->setMargin(10);

// Genereer de QR-code als PNG
$writer = new PngWriter();
$result = $writer->write($qrCode);

// Output de QR-code als afbeelding
header('Content-Type: image/png');
echo $result->getString();
