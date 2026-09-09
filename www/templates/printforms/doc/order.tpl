<table class="ctable" border="0" cellspacing="0" cellpadding="2">

    <tr>
        <td></td>
        <td><b>Замовник</b></td>
        <td colspan="8">{{customer_name}}</td>
    </tr>
    <tr>
        <td></td>
        <td>Телефон</td>
        <td colspan="8">{{phone}}</td>
    </tr>
    <tr>
        <td></td>
        <td>E-mail</td>
        <td colspan="8">{{email}}</td>
    </tr>
    <tr>
        <td></td>
        <td>Одержувач</td>
        <td colspan="8">{{payment_name}}</td>
    </tr>
    <tr>
        <td></td>
        <td>Доставка</td>
        <td colspan="8">{{delivery}}</td>
    </tr>
    <tr>
        <td></td>
        <td>Адреса</td>
        <td colspan="8">{{ship_address}}</td>
    </tr>

    {{#isoutnumber}}
    <tr>
        <td></td>
        <td>Зовнiшнiй номер</td>
        <td colspan="8">{{outnumber}}</td>
    </tr>
    {{/isoutnumber}}

    {{#isfirm}}
    <tr>
        <td></td>
        <td><b>Продавець</b></td>
        <td colspan="8"><b>{{firm_name}}</b></td>
        </tr>  
     {{#iban}}
    <tr>

        <td></td>
        <td> IBAN</td>
        <td colspan="8">{{iban}}   </td>

    </tr>
    {{/iban}}
	
    {{/isfirm}}

    <tr>
    <td></td>
    <td>Примітка</td>
    <td colspan="8" style="padding: 6px; text-align: left;">{{{notes}}}</td>
    </tr>

    <tr>
        <td style="font-weight: bolder; font-size: larger;" align="center" colspan="11" valign="middle">
            Замовлення № {{document_number}} від {{date}}
        </td>
    </tr>

    <tr style="font-weight: bolder;">
        <th style="border-top:1px #000 solid; border-bottom:1px #000 solid;" width="30">№</th>
        <th colspan="2" style="border-top:1px #000 solid; border-bottom:1px #000 solid; text-align: left;">Найменування</th>
        <th colspan="2" style="border-top:1px #000 solid; border-bottom:1px #000 solid; text-align: left;">Код</th>
        <th style="border-top:1px #000 solid; border-bottom:1px #000 solid; text-align: left;">Од.</th>
        <th style="border-top:1px #000 solid; border-bottom:1px #000 solid; text-align: left;"></th>
        <th style="border-top:1px #000 solid; border-bottom:1px #000 solid;" align="center;">Кіл.</th>
        <th style="border-top:1px #000 solid; border-bottom:1px #000 solid;" align="center;">Вага</th>
        <th style="border-top:1px #000 solid; border-bottom:1px #000 solid;" align="center;">Ціна</th>
        <th style="border-top:1px #000 solid; border-bottom:1px #000 solid;" align="right">Сума</th>
    </tr>

    {{#_detail}}
    <tr>
        <td align="right" valign="top">{{no}}</td>
        <td colspan="2">{{{tovar_name}}}</td>
        <td colspan="2" valign="top">{{tovar_code}}</td>
        <td valign="top">{{msr}}</td>
        <td valign="top">{{desc}}</td>
        <td align="center">{{quantity}}</td>
        <td align="center">{{weight}}</td>
        <td align="center">{{price}}</td>
        <td align="right" valign="top">{{amount}}</td>
    </tr>
    {{/_detail}}

    <tr style="font-weight: bolder;">
        <td style="border-top:1px #000 solid;" colspan="10" align="right">На суму:</td>
        <td style="border-top:1px #000 solid;" align="right">{{total}}</td>
    </tr>

    {{#totaldisc}}
    <tr style="font-weight: bolder;">
        <td colspan="10" align="right">Знижка:</td>
        <td align="right">{{totaldisc}}</td>
    </tr>
    {{/totaldisc}}

    {{#delbonus}}
    <tr style="font-weight: bolder;">
        <td colspan="10" align="right">Списано бонусiв:</td>
        <td align="right">{{delbonus}}</td>
    </tr>
    {{/delbonus}}

    {{#payamount}}
    <tr style="font-weight: bolder;">
        <td colspan="10" align="right">До сплати:</td>
        <td align="right">{{payamount}}</td>
    </tr>
    {{/payamount}}

    {{#payed}}
    <tr style="font-weight: bolder;">
        <td colspan="10" align="right">Оплата:</td>
        <td align="right">{{payed}}</td>
    </tr>
    {{/payed}}

</table>
