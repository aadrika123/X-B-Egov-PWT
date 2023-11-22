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
    <div class="main">
       <table style="border:none">
       <tbody>
            <tr>
                <td style="border:none" class="container">
                  <div class="content">
                    <img src="https://mahabharti.co.in/wp-content/uploads/2020/09/Akola-Municipal-Corporation.png" alt="" />
                  </div>
                </td>
                <td style="border:none"><p style="color: red;" > AKOLA MUNICIPAL CORPORATION</p>
                <p style="color: blue ;" >Akola Water Supply (Water Bill)</p></td>
                <td style="border:none" class="container">
                  <div class="content">
                    <img  src="https://pngimg.com/uploads/qr_code/qr_code_PNG25.png" alt="" />
                  </div>
                </td>
             </tr>
           </tbody>
    </table>
    <table>
        <tbody>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
                <td>Data 3</td>
                <td>Data 3</td>
            </tr>
            <tr>
                <td>Data 4</td>
                <td rowspan="2">Data 5</td>
                <td rowspan="2">Data 6</td>
                <td>Data 6</td>
                <td>Data 6</td>
            </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 3</td>
                <td>Data 3</td>
            </tr>
            <tr>
                <td>Data 4</td>
                <td colspan="3">Data 5</td>
                <td>Data 6</td>
            </tr>
            <tr>
                <td>Data 4</td>
                <td colspan="3">Data 5</td>
                <td>Data 6</td>
            </tr>
         </tbody>
    </table>
    <table>
      <tbody>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
                <td>Data 3</td>
                <td>Data 3</td>
                <td>Data 3</td>
            </tr>
            <tr>
                <td>Data 4</td>
                <td>Data 5</td>
                <td>Data 6</td>
                <td>Data 6</td>
                <td>Data 6</td>
                <td>Data 6</td>
            </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
                <td>Data 3</td>
                <td>Data 3</td>
                <td>Data 3</td>
            </tr>
       </tbody>
    </table>
    <table>
        <tbody>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
                <td>Data 3</td>
                <td>Data 3</td>
            </tr>
            <tr>
                <td>Data 4</td>
                <td>Data 5</td>
                <td>Data 6</td>
                <td>Data 6</td>
                <td>Data 6</td>
            </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
                <td>Data 3</td>
                <td>Data 3</td>
            </tr>
          </tbody>
    </table>
    
    <table>
       <tbody>
            <tr>
              <td>  <table class="paddingtd">
         <tbody>
            <tr>
                <td colspan="3">Meater Photo22</td>
             </tr>
           <tr>
                <td style="height:150px ; width:70px; border:none;">
                  
                  <div class="content">
                    <img class="colimages" src="https://mahabharti.co.in/wp-content/uploads/2020/09/Akola-Municipal-Corporation.png" alt="" />
                  </div>
               
                  </td>
                
             </tr>
            
           
         </tbody>
         </table></td>
                <td>
        <table class="paddingtd">
         <tbody>
            <tr>
                <td colspan="3">Meater Photo2</td>
                
             </tr>
           <tr>
                <td>Data 4</td>
                <td>Data 5</td>
                <td>Data 6</td>
             </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
             </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
             </tr>
            <tr>
                <td>Data 4</td>
                <td>Data 5</td>
                <td>Data 6</td>
             </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
             </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
             </tr>
           <tr>
                <td>Data 4</td>
                <td>Data 5</td>
                <td>Data 6</td>
             </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
             </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
             </tr>
            <tr>
                <td>Data 4</td>
                <td>Data 5</td>
                <td>Data 6</td>
             </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
             </tr>
         </tbody>
    </table>
    </td>
                <td>
                  <table class="paddingtd">
         <tbody>
            <tr>
                <td colspan="3">Meater Photo</td>
             </tr>
           <tr>
                <td  colspan="2">Data 4 ghghjhgj</td>
                <!--<td>Data 5</td>-->
                <td>Data 6</td>
             </tr>
            <tr>
                <td  colspan="2">Data 1</td>
                <!--<td>Data 2</td>-->
                <td>Data 3</td>
             </tr>
            <tr>
                <td  colspan="2">Data 1</td>
                <!--<td>Data 2</td>-->
                <td>Data 3</td>
             </tr>
            <tr>
                <td  colspan="2">Data 4</td>
                <!--<td>Data 5</td>-->
                <td>Data 6</td>
             </tr>
            <tr>
                <td  colspan="2">Data 1</td>
                <!--<td>Data 2</td>-->
                <td>Data 3</td>
             </tr>
            <tr>
                <td  colspan="2">Data 1</td>
                <!--<td>Data 2</td>-->
                <td>Data 3</td>
             </tr>
           <tr>
                <td  colspan="2">Data 4</td>
                <!--<td>Data 5</td>-->
                <td>Data 6</td>
             </tr>
            <tr>
                <td  colspan="2">Data 1</td>
                <!--<td>Data 2</td>-->
                <td>Data 3</td>
             </tr>
            <tr>
                <td  colspan="2">Data 1</td>
                <!--<td>Data 2</td>-->
                <td>Data 3</td>
             </tr>
            <tr>
                <td  colspan="2">Data 4</td>
                <!--<td>Data 5</td>-->
                <td>Data 6</td>
             </tr>
            <tr>
                <td  colspan="2">Data 1</td>
                <!--<td>Data 2</td>-->
                <td>Data 3</td>
             </tr>
         </tbody>
         </table>
    </td>
                
             </tr>
           </tbody>
    </table>
    <table>
       <tbody >
            <tr>
                <td>Datafdfgfdg 1</td>
                <td>Data fgfggfg2</td>
            </tr>
           </tbody>
    </table>
   <div class="dasheddiv"></div>
   <table>
       <tbody >
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
                <td rowspan="3" class="container">
                  <div class="content">
                  <img src="https://pngimg.com/uploads/qr_code/qr_code_PNG25.png" alt="" />
                </div></td>
               </tr>
            <tr>
                <td>Data 4</td>
                <td>Data 5</td>
                <td>Data 6</td>
               </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
               </tr>
        </tbody>
    </table>
    <table class="gap">
       <tbody>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
                <td>Data 3</td>
                </tr>
            <tr>
                <td>Data 4</td>
                <td>Data 5</td>
                <td>Data 6</td>
                <td>Data 6</td>
              </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
                <td>Data 3</td>
              </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
                <td>Data 3</td>
               </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
                <td>Data 3</td>
              </tr>
            <tr>
                <td>Data 1</td>
                <td>Data 2</td>
                <td>Data 3</td>
                <td>Data 3</td>
               </tr>
         </tbody>
    </table>
    </div>
     <script src="script.js"></script>
  </body>
</html>