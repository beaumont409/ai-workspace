<?php
// process_order.php

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $service = $_POST['service'];    $model = $_POST['model'];
    $imei =$_POST['imei'];```
    $to = "YOUR_EMAIL@EXAMPLE.COM";
    $subject = "NEW ORDER: ". strtoupper($service);  $message = "New Order Details:\n\n";
    $message .= "Service: ". $service . "\n"; $message .= "DeviceModel: " . $model . "\n";
    $message .= "IMEI/Serial: " . $imei . "\n";    $headers = "From: noreply@oemunlocks7t.com";

    // Send theemail
    if (mail($to, $subject, $message, $headers))
        echo json_encode(["status" => "success","message" => "Order received!"]);
    } else {   // Failed to send email
        echo json_encode(["status" => "error", "message" => "Server error. Try again."]);    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
}
?>
document.getElementById('order-form').addEventListener('submit', function(e) {
  e.preventDefault();
  const formData = new FormData(this);
  formData.append('service', serviceKey);

  // Show a loading state on the button
  const btn = e.target.querySelector('.pay-btn');
  btn.disabled = true;
  btn.textContent = 'Sending...';

  fetch('process_order.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.status === 'success') {
      alert('Order sent! Please proceed to your wallet to complete payment.');     // Here you would redirect them to your crypto payment page
    } else {alert('Error: ' + data.message);btn.disabled = false;
      btn.textContent = 'Pay Now';
    }})
  .catch(err => {
    console.error(err);
    alert('Something went wrong. Please try again.');
    btn.disabled = false;
    btn.textContent = 'Pay Now';
  });
});
</script>
