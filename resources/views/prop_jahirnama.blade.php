<!DOCTYPE html>

<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Receipt</title>
  <style>
        @font-face {
            font-family: Hindi-Light;
            src: url("{{storage_path('Fonts/Noto_Sans/NotoSans-Light.ttf')}}");
        }
        @font-face {
            font-family: Hindi-Bold;
            src: url("{{storage_path('Fonts/Noto_Sans/NotoSans-Bold.ttf')}}");
        }
        #container {            
            padding: 20px;
            margin-top: 0.5px;
            font-family: Hindi-Light;
            position: relative;
        }
 </style>
</head>
<body class="container" style=" margin: 20px; font-family: Hindi-Bold;">

  <div style=" font-size: 14px;">
    <p style="text-align:right; margin-right: 26px; ">जाक्रं. अमनपा/कर/___/___</p>

    <p style="text-align:left; margin-left: 444px; ">कर वसुली विभाग <br> अकोला महानगरपालिका, अकोला</p>

    <p style="text-align:right; margin-right: 190px; ">दिनांक :- {{$safData["jahirnamaDate"]}}</p>
 
    <p style="text-align:center;font-size: 22px;">जाहीरनामा</p>

    <p style="text-align:left; margin-left: 60px; ">मालमत्ता विकत घेणाऱ्याचे नांव    :-   
      <span style="border-bottom: 1px solid black;"> श्री {{$safData["previousOwnerName"]}}</span>
    </p>

    <p style="text-align:center;  margin-left: 28px ;">पत्ता :
      <span style="border-bottom: 1px solid black;">{{$safData["prop_address"]}}</span>
    </p>

    <p style="text-align:left; margin-left: 60px ; "> मालमत्ता विकरणाऱ्याचे नांव :-    श्री
      <span style="border-bottom: 1px solid black;">{{$safData["newOwnerName"]}}</span>
    </p>

    <p style="text-align:center; ">पत्ता :
      <span style="border-bottom: 1px solid black;">{{$safData["prop_address"]}}</span>
    </p>

    <p style="text-align:justify; margin-left: 20px; margin-right: 20px; font-weight: normal ; font-size: 18px;">
      यांनी सदरहु मालमत्ता गट क्रं. {{$safData["property_no"]}} घर क्रमांक. {{$safData["holding_no"]}} हि मालमत्ता १) खरेदी खताद्वारे २) बक्षीस पत्राद्वारे ३) वारसा हक्काद्वारे विकत घेतली असुन सदर मालमत्ता हस्तांतरण करण्याबाबत चा अर्ज केला असुन त्यावर नियमाप्रमाणे कार्यवाही प्रस्तावित आहे. याबाबत कोणालाही आक्षेप नोंदवायचा असल्यास पंधरा दिवसाच्या आत म. न.पा. चे कर वसुली विभागामध्ये संबंधीत लिपीकाकडे / अर्थ लिपीकाकडे प्रत्यक्ष येवुन हस्तांतरीत होणाऱ्या मालमत्तेचा दस्ताऐवजासह आक्षेप नोंदवावा- तसे न केल्यास संबंधीत मालमत्ता विकत घेणाऱ्यांच्या नांवे हस्तांतरीत करण्यात येईल व नंतर कोणत्याही प्रकारचे आक्षेप विचारात घेतले जाणार नाही
    </p>

    <p style="text-align:left; margin-top: 100px; margin-left: 20px;">दिनांक :{{$safData["jahirnamaDate"]}}</p>

    <p style="text-align:center; ">
      सहा. कर अधिक्षक<br>अकोला महानगरपालिका, अकोला
    </p>

  </div>

</body>
</html>