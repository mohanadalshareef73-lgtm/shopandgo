<div class="offcanvas border-0 pct-offcanvas offcanvas-end" tabindex="-1" id="offcanvas_pc_layout_customer_phones">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="customerName"></h5>
      <button type="button" class="btn btn-icon btn-link-danger" data-bs-dismiss="offcanvas" aria-label="Close"
        ><i class="ti ti-x"></i
      ></button>
    </div>
    <div class="pct-body" style="height: calc(100% - 85px)">
      <div class="offcanvas-body py-0">
        <ul class="list-group list-group-flush">

              
          <li class="list-group-item" style="display: none;">
            <h6 class="mb-1">Custom Theme</h6>
            <p class="text-muted text-sm">Choose your Primary color</p>
            <div class="theme-color preset-color">
              <a href="#!" class="active" data-value="preset-1"><i class="ti ti-check"></i></a>
              <a href="#!" data-value="preset-2"><i class="ti ti-check"></i></a>
              <a href="#!" data-value="preset-3"><i class="ti ti-check"></i></a>
              <a href="#!" data-value="preset-4"><i class="ti ti-check"></i></a>
              <a href="#!" data-value="preset-5"><i class="ti ti-check"></i></a>
              <a href="#!" data-value="preset-6"><i class="ti ti-check"></i></a>
              <a href="#!" data-value="preset-7"><i class="ti ti-check"></i></a>
              <a href="#!" data-value="preset-8"><i class="ti ti-check"></i></a>
              <a href="#!" data-value="preset-9"><i class="ti ti-check"></i></a>
              <a href="#!" data-value="preset-10"><i class="ti ti-check"></i></a>
            </div>
          </li>

          
          <li class="list-group-item">
            <div class="pc-dark">
              {{-- <h6 class="mb-1">{{ __('messages.phoneNumber') }}</h6> --}}
              <p class="text-muted text-sm" id="orderName"></p>
              <div class="row theme-layout">




                  <table id="autofill"
                  class="table table-striped table-bordered nowrap dataTable"
                  aria-describedby="autofill_info">

                  <thead>
                    <tr>
                      <td><b>{{ __('messages.phoneNumber') }}</b></td>
                    </tr>
                  </thead>
                  <tbody id="bodyrow44">
      


                  </tbody>

              </table>
            
            

              </div>
            </div>
          </li>
          



        </ul>
      </div>
    </div>
  </div>
