<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إيصال عربون {{ $r['code'] }}</title>
    <style>

        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@500&display=swap');

        * {
            font-family: 'Cairo', sans-serif;
        }

        body {
            direction: rtl;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            display: flex;
            flex-direction: column;
            min-height: 100vh; /* Ensures the body takes full height of the viewport */
        }

        .container {
            width: 210mm;
            margin: 0 auto;
            padding: 5mm 20mm;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
            flex: 1; /* Allows the container to grow and take up available space */
            display: flex;
            flex-direction: column;
        }

        .header, .footer {
            text-align: center;
            margin-bottom: 5px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #545353; /* Adding a bottom border */
        }

        .header img {
            height: 60px;
        }

        .header h5 {
            margin: 0;
            padding-left: 15px;
            font-size: 18px;
            flex-grow: 1;
            text-align: left;
        }

        .details {
            width: 100%;
            margin-bottom: 20px;
        }

        .details table {
            width: 100%;
            border-collapse: collapse;
        }

        .details th, .details td {
            border: 1px solid #000;
            text-align: center;
        }

        .info span, .notes span {
            font-weight: bold;
        }

        .info {
        }

        .dates {
            display: flex;
            justify-content: space-between;
        }

        .dates div {
            text-align: right;
        }

        .footer {
            margin-top: auto; /* Ensures the footer is pushed to the bottom */
            border-top: 2px solid #000; /* Adding a top border */
        }

    </style>
    <style>@media print { body { background: #fff; } .container { box-shadow: none; } }</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{asset('assets/images/loggo.png')}}" alt="Shop and Go Logo">
            <h5>SHOP AND GO</h5>
        </div>

        <h4 style="text-align: right; margin-top: 0px;  margin-bottom: -5px;"> # {{ $r['code'] }} </h4>

        @if (!$r['ship'])
        <div class="info">
        @else
        <div class="info" style="margin-bottom: 15px;">
        @endif
            <div><span style="font-size: 24px;">اسم العميل : </span> <span style="font-size: 24px; color: #282E4D;">{{ $r['name'] }}{{ $r['ccode'] ? ' - ' . $r['ccode'] : '' }}</span></div>
        </div>

        @if (!$r['ship'])
        <span
            class="text-warning" style="color: rgb(187, 122, 0); font-size: 14px;">ملاحطة
            :: اجمالي القيمة بدون سعر
            الشحن</span>
        @endif

        <div class="dates" style="font-size: 14px;">
            <div>
                <p>تاريخ الفاتورة: <span style=" color: #282E4D;">{{ now()->format('Y-m-d') }}</span></p>
            </div>
            <div>
                <p>تاريخ الطلب:  <span style=" color: #282E4D;">{{ $r['date'] }}</span></p>
            </div>
        </div>

        <div class="details"  style="font-size: 14px;">
            <table>
                <tr>
                    <th>المنصة</th>
                    <th>الكمية</th>
                    <th>سعر الشحن</th>
                    <th>اجمالي القيمة</th>
                </tr>
                <tr style=" color: #282E4D;">
                    <td>{{ $r['platform'] }}</td>
                    <td>{{ $r['qty'] }}</td>

                    <td>{{ $r['ship'] ? $f($r['ship']) . ' د.ل' : ' - ' }}</td>
                    <td>{{ $f($r['total'] + $r['ship']) }} د.ل
                    @if ($r['discount'])<span style="color: red;">(تخفيض: {{ $f($r['discount']) }} د.ل)</span>@endif
                    </td>

                </tr>


                <tr>
                    <td colspan="2" style="border: none;"></td>
                    <td style="color: white; background-color: #282E4D;">القيمة المدفوعة</td>
                    <td style="color: white; background-color: #282E4D;">{{ $f($r['paid']) }} د.ل</td>
                </tr>

                <tr>
                    <td colspan="2" style="border: none;"></td>
                    <td>عربون {{ now()->format('Y-m-d') }}</td>
                    <td>{{ $f($r['deposit']) }} د.ل</td>
                </tr>

                <tr style=" color: #282E4D;">
                    <td colspan="2" style="border: none;"></td>
                    <td style="background-color: lightgrey;">القيمة المستحقة</td>
                    <td style="background-color: lightgrey;">{{ $f($r['due']) }} د.ل</td>
                </tr>

            </table>
        </div>

        <div class="info" style="font-size: 14px; padding-bottom: 10px;">
            <div><span>{{ optional($invoice)->welcome }}</span></div>
        </div>

        <div class="notes" style="font-size: 14px;">
            <div style=" color: #282E4D;">الشروط:</div>
            <ul>
                <li>دفع قيمة الشراء فوراً</li>
                <li>دفع تكلفة الشحن عند وصول الاصناف اعلاه</li>
            </ul>

            <div><span style=" color: #282E4D;">{{ optional($invoice)->note }}</span></div>
            <div style=" padding-top: 10px;">{{ optional($invoice)->note2 }}</div>

            <div style=" color: #282E4D; margin-top: 10px;">نقدم ضمانات الاتية:</div>
            <ul>
                <li>مطابقة للمواصفات و الكمية المتفق عليها مسبقا</li>
                <li>عدم مطالبة بتكلفة اضافية من الزبون بعد الاتفاق و دفع الفاتورة</li>
            </ul>
            <div><span>نحن سعداء لخدمتكم.</span></div>

            <div style="text-align: left; margin-top: -30px; padding-left: 35px;"><span>الـتـوقـيـع : </span></div>
            <div style="text-align: left; padding-left: 20px; margin-top: 15px;"><span>............................</span></div>


        </div>

        <div class="footer" style="font-size: 14px;">
            <p>{{ optional($invoice)->address }}</p>
        </div>
    </div>

    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
