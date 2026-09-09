<style>
    @media print {
                @page {
                  margin: 0!important;
                }
                body {
                  margin-top: 0mm !important;
                }
    
</style>
<div>
    <table class="ctable" border="0" cellpadding="0" cellspacing="0" style="width:125px; height:80px" > 
       <td>
		   <table>
			   <tr>
				   <td align="center" style="font-size:14px"><b>{{name}}</b></td>
			   </tr>           
            <tr>
				<td align="left" style="font-size:14px">
					{{#isarticle}}
					{{article}}
					{{/isarticle}}                
                 </td>
				<td align="right" style="font-size:14px">
					{{#isprice}}
					<b>{{price}}</b>
					{{/isprice}}
                </td>
			   </tr>
			   {{#isbarcode}}
			   <tr style="font-size:14px">
				   <td align="center">
					   <img style="width:110px" {{{barcodeattr}}}>
					   <br>{{barcode}}</td>
			   </tr>
			   {{/isbarcode}}
			   {{#isqrcode}}
			   <tr><td align="center">
				   <img style="width:110px" {{{qrcodeattr}}}>
				   </td>
			   </tr>
			   {{/isqrcode}}
		   </table>
		</td>
	</table>
</div>