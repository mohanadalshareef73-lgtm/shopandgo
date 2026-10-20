<!DOCTYPE html>

@if (session()->get('locale') == 'en')
    <html lang="en">
@else
    <html lang="ar" dir="rtl">
@endif
<!-- [Head] start -->

<head>

    @yield('title')
    <!-- [Meta] -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="SHOP AND GO PORTAL">
    <meta name="keywords" content="SHOP AND GO PORTAL">
    <meta name="author" content="MAlik Al Nabouli">

    <style type="text/css">
        @import url(https://fonts.googleapis.com/css2?family=Cairo:wght@500&display=swap);

        * {
            font-family: 'Cairo', sans-serif;
        }



        #loading-overlay {
          position: fixed;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background-color: rgba(255, 255, 255, 0.883);
          z-index: 9999; /* ensure the overlay appears on top of other elements */
          display: flex;
          justify-content: center;
          align-items: center;
        }

        .spinner {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

    </style>



    @include('layout.style')
    @yield('style')
</head>
<!-- [Head] end -->
<!-- [Body] Start -->

<body>

    <div id="loading-overlay">
        <img src="{{ asset('assets/images/favicon.png') }}" width="90" alt="" class="img-fluid spinner">
    </div>

    <!-- [ Pre-loader ] start -->
    <div class="loader-bg">
        <div class="loader-track">
            <div class="loader-fill"></div>
        </div>
    </div>
    <!-- [ Pre-loader ] End -->


    <!-- [ Sidebar Menu ] start -->
    <nav class="pc-sidebar">
        <div class="navbar-wrapper">
            <div class="m-header justify-content-center">
                <a href="{{ route('dashboard.index') }}" class="b-brand text-primary">
                    <!-- ========   Change your logo from here   ============ -->
                    <img src="{{ asset('assets/images/logo.png') }}" width="220px" />
                    {{-- <span class="badge bg-light-success rounded-pill ms-2 theme-version"></span> --}}
                </a>
            </div>
            <div class="navbar-content">
                @include('layout.navbar_profile')

                @include('layout.menu')

            </div>
        </div>
    </nav>
    <!-- [ Sidebar Menu ] end -->
    <!-- [ Header Topbar ] start -->
    @include('layout.header')

    <!-- [ Header ] end -->


    <!-- [ Main Content ] start -->
    @yield('content')

    <!-- [ Main Content ] end -->
    @include('layout.footer')

    {{-- <div class="pct-c-btn">
  <a href="#" data-bs-toggle="offcanvas" data-bs-target="#offcanvas_pc_layout">
    <svg class="pc-icon">
      <use xlink:href="#custom-setting-2"></use>
    </svg>
  </a>
</div> --}}

    @include('layout.side_bar_setting')
    @include('layout.category_image')
    @include('layout.navbar_cards')
    @include('layout.PaymentImageModal')
    @include('layout.classificationPaymentModal')
    @include('layout.ProductDataModal')
    @include('layout.customerPhones')




    @include('layout.script')
    @yield('script')

    <script>
        function showAdImage(notif) {
    var exampleUrl = "{{ route('home') }}";
    var fileUrl = exampleUrl + "/storage/" + notif['img'];
    var imgElement = document.getElementById("notificationImage");
    var pdfElement = document.getElementById("notificationPdf");

    // Reset the display of both elements
    imgElement.style.display = "none";
    pdfElement.style.display = "none";

    if (notif['img'].endsWith('.pdf')) {
        pdfElement.src = fileUrl;
        pdfElement.style.display = "block";
    } else {
        imgElement.src = fileUrl;
        imgElement.style.display = "block";
    }
}
    </script>
</body>
<!-- [Body] end -->

</html>
