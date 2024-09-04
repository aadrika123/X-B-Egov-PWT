<!DOCTYPE html>
<html>
  <head>
    <title>Hello, World!</title>
    <link rel="stylesheet" href="styles.css" />
  </head>
   <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0px;
        }
        th, td {
            border: 1px solid black;
            /*padding: 8px;*/
            text-align: center;
            font-size: 8px;
            color: black;
        }
        th {
            background-color: #f2f2f2;
        }
.gap{
 margin-top: 1px; 
}
 .back{
   background-color: red;
 } 
 .main{
    width: 100%;
    padding: 1px;
    border: 1px dashed  black;
    
 }
 
   .dasheddiv{
     border-bottom: 1px dashed  black;
     margin-top: 2px; 
     margin-bottom: 2px;
    }
  
     .container {
      width: 10%;
      height: 20%;
      overflow: hidden;
      /*border: 1px solid #ccc;*/
    }
     .container1 {
      width: 5%;
      height: 5%;
      overflow: hidden;
      /*border: 1px solid #ccc;*/
    }

    .content {
      width: 100%;
      height: 100%;
      object-fit: cover;  
    }
   img{
     width:80%;
     height: 80%;
     /*padding: 10px;*/
   }
   .logo{
     /*border: 3px solid #ddd;*/
     /*border-collapse: collapse;*/
      border: none;
       border-collapse: collapse;
   }
   .colimages{
     width:90% ;
     height: 90%;
     /*margin-top: 30px;*/
   }
    </style>
  <body>
    
    
    
    
   <div class="dasheddiv">
    <?php print_r($response)?>
   </div>
</body>
   
</html>