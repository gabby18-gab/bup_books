<?php
// user/order_success.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Successful - BUP Platform Book Resale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #0A3143, #1C4E6C);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 20px;
        }
        
        .success-card {
            background: white;
            border-radius: 30px;
            padding: 50px;
            text-align: center;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            animation: fadeIn 0.6s ease-out;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(145deg, #FF914D, #FFC107);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: bounce 0.6s ease-out;
        }
        
        .success-icon i {
            color: #0A3143;
            font-size: 50px;
        }
        
        h1 {
            color: #0A3143;
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 15px;
        }
        
        p {
            color: #5A6C74;
            font-size: 16px;
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .order-id {
            background: rgba(255,145,77,0.1);
            padding: 15px;
            border-radius: 15px;
            margin: 20px 0;
            border-left: 4px solid #FF914D;
        }
        
        .order-id strong {
            color: #FF914D;
        }
        
        .btn-home {
            display: inline-block;
            background: linear-gradient(145deg, #FF914D, #FFC107);
            color: #0A3143;
            padding: 15px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 18px;
            margin-top: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 0 #C7511E;
        }
        
        .btn-home:hover {
            transform: translateY(2px);
            box-shadow: 0 2px 0 #C7511E;
            color: #0A3143;
        }
        
        .countdown {
            color: #5A6C74;
            font-size: 14px;
            margin-top: 20px;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="success-icon">
            <i class="bi bi-check-lg"></i>
        </div>
        
        <h1>Order Successful!</h1>
        
        <p>
            Thank you for your purchase! Your order has been successfully placed and is being processed.
        </p>
        
        <?php if ($order_id): ?>
        <div class="order-id">
            <strong>Order ID:</strong> #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?>
        </div>
        <?php endif; ?>
        
        <p>
            You will receive a confirmation email shortly. You can track your order in your account.
        </p>
        
        <a href="index.php" class="btn-home">
            <i class="bi bi-house-door me-2"></i>Back to Home
        </a>
        
        <div class="countdown">
            Redirecting to home in <span id="countdown">5</span> seconds...
        </div>
    </div>

    <script>
        // Countdown timer
        let countdown = 5;
        const countdownElement = document.getElementById('countdown');
        const countdownInterval = setInterval(function() {
            countdown--;
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                window.location.href = 'index.php';
            }
        }, 1000);
    </script>
</body>
</html>