<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاتورة ديون</title>
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
            margin-bottom: 20px;
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
            padding-top: 30px;
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
            padding-top: 10px;
            border-top: 2px solid #000; /* Adding a top border */
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{asset('assets/images/loggo.png')}}" alt="Shop and Go Logo">
            <h5>SHOP AND GO</h5>
        </div>

            <div class="info" style="margin-bottom: 15px;">
            {{-- <div><span style="font-size: 24px;">#الفاتورة :</span> <span style="font-size: 24px; color: #282E4D;">{{ $paid->order->code }}</span></div> --}}

            @if ($paid->dept->customer)
            <td class="">{{ $paid->dept->customer->name }}</td>
            <div><span style="font-size: 24px;">اسم العميل او الجهة : </span> <span style="font-size: 24px; color: #282E4D;">{{ $paid->dept->customer->name }} - {{ $paid->dept->customer->code }}</span></div>
        @else
            <td class="">{{ $paid->dept->org }}</td>
            <div><span style="font-size: 24px;">اسم العميل او الجهة : </span> <span style="font-size: 24px; color: #282E4D;">{{ $paid->dept->customer->name }} - {{ $paid->dept->customer->code }}</span></div>
        @endif

        </div>

        <div class="dates" style="font-size: 14px;">
            <div>
                <p>تاريخ الفاتورة: <span style=" color: #282E4D;">{{ now()->format('Y-m-d') }}</span></p>
            </div>
            <div>
                <p>تاريخ اخر دفع:  <span style=" color: #282E4D;">{{ $paid->paids->last()->created_at->format('Y-m-d') }}</span></p>
            </div>
        </div>

        <div class="details"  style="font-size: 14px;">
            <table>
                <tr>
                    <th colspan="2" >النوع</th>
                    <th colspan="2" >اجمالي القيمة</th>
                </tr>
                <tr style=" color: #282E4D;">
                    <td colspan="2" > تسديد الديون للعميل </td>
                        <td colspan="2" >{{ $paid->dept->total }} د.ل</td>
                </tr>

                <tr>
                    <td colspan="2" style="border: none;"></td>
                    <td style="color: white; background-color: #282E4D;">القيمة المدفوعة</td>
                    <td style="color: white; background-color: #282E4D;">{{ $paid->paids->sum('paid_price') }} د.ل</td>
                </tr>


                @foreach ($paid->paids as $paidss)
                <tr>
                    <td colspan="2" style="border: none;"></td>
                    <td>تم الدفع في {{ $paidss->created_at->format('Y-m-d') }}</td>
                    <td>{{ $paidss->paid_price }} د.ل</td>
                </tr>
                @endforeach


{{--                @if ($paid->order->paids->count() > 0)
                    <tr>
                        <td colspan="2" style="border: none;"></td>
                        <td>تم الدفع في {{ $paid->order->paids->last()->created_at->format('Y-m-d') }}</td>
                        <td>{{ $paid->order->paids->last()->paid_price }} د.ل</td>
                    </tr>
                @endif --}}

                <tr style=" color: #282E4D;">
                    <td colspan="2" style="border: none;"></td>
                    <td style="background-color: lightgrey;">القيمة المتبقية للعميل</td>
                    @if ($paid->dept->total <= $paid->paids->sum('paid_price'))
                    <td style="background-color: lightgrey;">0 د.ل</td>
                    @else
                    <td style="background-color: lightgrey;">{{ $paid->dept->total - $paid->paids->sum('paid_price') }} د.ل</td>
                    @endif
                </tr>

            </table>
        </div>

        <div class="info" style="font-size: 14px; padding-bottom: 10px;">
            <div><span>شكرا على طلبك, انه لمن دواعي سرورنا ان نعمل على توفير الطلب الخاص بكم.</span></div>
        </div>

        <div class="notes" style="font-size: 14px;">

            <div style=" color: #282E4D;">ملاحظات هامة :</div>
            <ul>
                <li style="padding-bottom: 10px;">تم تسديد القيمة او البعض منها المتبقية للعميل وذلك وفق البنود المتفق عليها مسبقاً </li>
                <li>تم إصدار هذه الفاتورة استناداً إلى طلبكم. نحن سعداء لخدمتكم.</li>
            </ul>

            <div style=" color: #282E4D; margin-top: 10px;">نقدم ضمانات الاتية:</div>
            <ul>
                <li style="padding-bottom: 10px;">مطابقة للمواصفات و الكمية المتفق عليها مسبقا</li>
                <li>عدم مطالبة بتكلفة اضافية من الزبون بعد الاتفاق و دفع الفاتورة</li>
            </ul>
            <div style="padding-bottom: 15px;"><span>نحن سعداء لخدمتكم.</span></div>
        </div>

        <div class="footer" style="font-size: 14px;">
            <p>العنوان / الرحبة شارع قصر عبد إسماعيل 0920703777 - 0920403444</p>
        </div>
    </div>

    <script>

    </script>
</body>
</html>
