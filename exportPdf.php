<?php
require 'vendor/autoload.php';

use Dompdf\Dompdf;

$dompdf = new Dompdf();
$html = "
  <h2 style='color:#2a0076;'>Venue Booking Invoice</h2>
  <p><strong>Date:</strong> {$_POST['date']}</p>
  <p><strong>Time Slot:</strong> {$_POST['slot']}</p>
  <p><strong>Guests:</strong> {$_POST['guests']}</p>
  <p><strong>Food Package:</strong> {$_POST['package']}</p>
  <hr>
  <p><strong>Food Cost:</strong> ৳{$_POST['food_total']}</p>
  <p><strong>Service Charge:</strong> ৳{$_POST['service']}</p>
  <p><strong>Add-On Cost:</strong> ৳{$_POST['addons']}</p>
  <h3 style='color:#2a0076;'>Grand Total: ৳{$_POST['grand']}</h3>
  <p><strong>Advance Paid:</strong> ৳{$_POST['paid']}</p>
  <p>Thank you for booking with us!</p>
";

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("invoice.pdf", ["Attachment" => true]);
