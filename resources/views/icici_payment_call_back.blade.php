<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .container {
            text-align: center;
            margin: 100px auto;
            max-width: 600px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        .message {
            font-weight: 600;
            font-size: 28px;
            color: #333;
            margin-bottom: 20px;
        }

        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007BFF;
            color: #fff;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .button:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <div class="container">
        <p class="message">Thank you for your payment.</p>
        <br>
        <p><b>Your Unique Ref No:</b>{{ $UniqueRefNumber }} </p>
        <p><b>You have made payment from </b>{{ $PaymentMode }}</p>
        <p>Click the button below to redirect and view your payment history.</p>

        <a href="{{ $callBack }}" class="button">Get Payment History</a>
    </div>
</body>

</html>