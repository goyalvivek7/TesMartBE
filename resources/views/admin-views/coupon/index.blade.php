@extends('layouts.admin.app')

@section('title','Add new coupon')

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title"><i class="tio-add-circle-outlined"></i> {{\App\CentralLogics\translate('add')}} {{\App\CentralLogics\translate('new')}} {{\App\CentralLogics\translate('coupon')}}</h1>
                </div>
            </div>
        </div>
        <!-- End Page Header -->
        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <form action="{{route('admin.coupon.store')}}" method="post">
                    @csrf
                   <div class="row">
                       <div class="col-4">
                           <div class="form-group">
                               <label class="input-label" for="exampleFormControlInput1">{{\App\CentralLogics\translate('title')}}</label>
                               <input type="text" name="title" class="form-control" placeholder="New coupon" required>
                           </div>
                       </div>
                       <div class="col-4">
                           <div class="form-group">
                               <label class="input-label" for="exampleFormControlInput1">{{\App\CentralLogics\translate('coupon')}} {{\App\CentralLogics\translate('type')}}</label>
                               <select name="coupon_type" class="form-control" onchange="coupon_type_change(this.value)">
                                   <option value="default">{{\App\CentralLogics\translate('default')}}</option>
<!--                                   <option value="first_order">{{\App\CentralLogics\translate('first')}} {{\App\CentralLogics\translate('order')}}</option>-->
                               </select>
                           </div>
                       </div>
                       <div class="col-4" id="limit-for-user">
                           <div class="form-group">
                               <label class="input-label" for="exampleFormControlInput1">{{\App\CentralLogics\translate('limit')}} {{\App\CentralLogics\translate('for')}} {{\App\CentralLogics\translate('same')}} {{\App\CentralLogics\translate('user')}}</label>
                               <input type="number" name="limit" class="form-control" placeholder="EX: 10">
                           </div>
                       </div>
                   </div>

                    <div class="row">
                        <div class="col-md-4 col-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{\App\CentralLogics\translate('code')}}</label>
                                <input type="text" name="code" class="form-control"
                                       placeholder="{{\Illuminate\Support\Str::random(8)}}" required>
                            </div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{\App\CentralLogics\translate('start')}} {{\App\CentralLogics\translate('date')}}</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{\App\CentralLogics\translate('expire')}} {{\App\CentralLogics\translate('date')}}</label>
                                <input type="date" name="expire_date" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 col-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{\App\CentralLogics\translate('min')}} {{\App\CentralLogics\translate('purchase')}}</label>
                                <input type="number" step="0.01" name="min_purchase" value="0" min="0" max="100000" class="form-control"
                                       placeholder="100">
                            </div>
                        </div>
                        <div class="col-md-3 col-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{\App\CentralLogics\translate('max')}} {{\App\CentralLogics\translate('discount')}}</label>
                                <input type="number" step="0.01" min="0" value="0" max="1000000" name="max_discount" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-4 col-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{\App\CentralLogics\translate('discount')}}</label>
                                <input type="number" step="0.01" min="1" max="10000" name="discount" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-2 col-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{\App\CentralLogics\translate('discount')}} {{\App\CentralLogics\translate('type')}}</label>
                                <select name="discount_type" class="form-control">
                                    <option value="amount">{{\App\CentralLogics\translate('amount')}}</option>
                                    <option value="percent">{{\App\CentralLogics\translate('percent')}}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                       <div class="col-md-6 col-6">
                           <div class="form-group">
                               <label class="input-label" for="exampleFormControlInput1">Coupon Text</label>
                               <input type="text" name="valid_text" class="form-control" placeholder="Coupon Text" required>
                           </div>
                       </div>
                       <div class="col-md-6 col-6">
                           <div class="form-group">
                               <label class="input-label" for="exampleFormControlInput1">Coupon Details</label>
                               <textarea name="valid_detail" class="form-control" placeholder="Coupon Detail"></textarea>
                           </div>
                       </div>
                    </div>
                    <button type="submit" class="btn btn-primary">{{\App\CentralLogics\translate('submit')}}</button>
                </form>
            </div>

            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <hr>
                <div class="card">
                    <div class="card-header">
                        <div class="row">
                            <div class="col-md-4">
                                <h5>Coupon Table <span style="color: red;">({{ $coupons->total() }})</span></h5>
                            </div>
                            <div class="col-md-8 float-right" style="width: 30vw">
                                <form action="{{url()->current()}}" method="GET">
                                    <!-- Search -->
                                    <div class="input-group input-group-merge input-group-flush">
                                        <div class="input-group-prepend">
                                            <div class="input-group-text">
                                                <i class="tio-search"></i>
                                            </div>
                                        </div>
                                        <input id="datatableSearch_" type="search" name="search" class="form-control"
                                            placeholder="Search by Title or Code" value="{{ $search }}" aria-label="Search"  required>
                                        <button type="submit" class="btn btn-primary">search</button>

                                    </div>
                                    <!-- End Search -->
                                </form>
                            </div>
                        </div>
                    </div>
                    <!-- Table -->
                    <div class="table-responsive datatable-custom">
                        <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                            <thead class="thead-light">
                            <tr>
                                <th>{{\App\CentralLogics\translate('#')}}</th>
                                <th>{{\App\CentralLogics\translate('title')}}</th>
                                <th>{{\App\CentralLogics\translate('code')}}</th>
                                <th>{{\App\CentralLogics\translate('min')}} {{\App\CentralLogics\translate('purchase')}}</th>
                                <th>{{\App\CentralLogics\translate('max')}} {{\App\CentralLogics\translate('discount')}}</th>
                                <th>{{\App\CentralLogics\translate('discount')}}</th>
                                <th>{{\App\CentralLogics\translate('discount')}} {{\App\CentralLogics\translate('type')}}</th>
                                <th>{{\App\CentralLogics\translate('start')}} {{\App\CentralLogics\translate('date')}}</th>
                                <th>{{\App\CentralLogics\translate('expire')}} {{\App\CentralLogics\translate('date')}}</th>
                                <th>{{\App\CentralLogics\translate('status')}}</th>
                                <th>{{\App\CentralLogics\translate('action')}}</th>
                            </tr>
                            </thead>

                            <tbody>
                            @foreach($coupons as $key=>$coupon)
                                <tr>
                                    <td>{{$coupons->firstItem()+$key}}</td>
                                    <td>
                                    <span class="d-block font-size-sm text-body">
                                        {{$coupon['title']}}
                                    </span>
                                    </td>
                                    <td>{{$coupon['code']}}</td>
                                    <td>{{$coupon['min_purchase']." ".\App\CentralLogics\Helpers::currency_symbol()}}</td>
                                    <td>{{$coupon['max_discount']." ".\App\CentralLogics\Helpers::currency_symbol()}}</td>
                                    <td>{{$coupon['discount']}}</td>
                                    <td>{{$coupon['discount_type']}}</td>
                                    <td>{{$coupon['start_date']}}</td>
                                    <td>{{$coupon['expire_date']}}</td>
                                    <td>
                                        @if($coupon['status']==1)
                                            <div style="padding: 10px;border: 1px solid;cursor: pointer"
                                                 onclick="location.href='{{route('admin.coupon.status',[$coupon['id'],0])}}'">
                                                <span class="legend-indicator bg-success"></span>{{\App\CentralLogics\translate('active')}}
                                            </div>
                                        @else
                                            <div style="padding: 10px;border: 1px solid;cursor: pointer"
                                                 onclick="location.href='{{route('admin.coupon.status',[$coupon['id'],1])}}'">
                                                <span class="legend-indicator bg-danger"></span>{{\App\CentralLogics\translate('disabled')}}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <!-- Dropdown -->
                                        <div class="dropdown">
                                            <button class="btn btn-secondary dropdown-toggle" type="button"
                                                    id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true"
                                                    aria-expanded="false">
                                                <i class="tio-settings"></i>
                                            </button>
                                            <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                <a class="dropdown-item"
                                                   href="{{route('admin.coupon.update',[$coupon['id']])}}">{{\App\CentralLogics\translate('edit')}}</a>
                                                <a class="dropdown-item" href="javascript:"
                                                   onclick="form_alert('coupon-{{$coupon['id']}}','Want to delete this coupon ?')">{{\App\CentralLogics\translate('delete')}}</a>
                                                <form action="{{route('admin.coupon.delete',[$coupon['id']])}}"
                                                      method="post" id="coupon-{{$coupon['id']}}">
                                                    @csrf @method('delete')
                                                </form>
                                            </div>
                                        </div>
                                        <!-- End Dropdown -->
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                        <hr>
                        <table>
                            <tfoot>
                            {!! $coupons->links() !!}
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <!-- End Table -->
        </div>
    </div>

@endsection

@push('script_2')
    <script>
        function coupon_type_change(order_type) {
            if(order_type=='first_order'){
                $('#limit-for-user').hide();
            }else{
                $('#limit-for-user').show();
            }
        }
    </script>
@endpush
