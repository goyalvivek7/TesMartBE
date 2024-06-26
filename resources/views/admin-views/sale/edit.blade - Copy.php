@extends('layouts.admin.app')

@section('title','Update banner')

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title"><i class="tio-edit"></i> Edit</h1>
                </div>
            </div>
        </div>
        <!-- End Page Header -->
        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-12 col-lg-12 mb-3 mb-lg-2">
                <form action="{{route('admin.sale.update',[$banner['id']])}}" method="post"
                      enctype="multipart/form-data">
                    @csrf @method('put')

                    <div class="row">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlInput1">{{\App\CentralLogics\translate('title')}}</label>
                                <input type="text" name="title" value="{{$banner['title']}}" class="form-control"
                                       placeholder="New banner" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlSelect1">{{\App\CentralLogics\translate('item')}} {{\App\CentralLogics\translate('type')}}<span
                                        class="input-label-secondary">*</span></label>
                                <select name="item_type" id="item_type" class="form-control" onchange="show_item(this.value)">
                                    <option value="">Select</option>
                                    <option value="product" {{$banner['product_id']==null?'':'selected'}}>{{\App\CentralLogics\translate('product')}}</option>
                                    <option value="category" {{$banner['category_id']==null?'':'selected'}}>{{\App\CentralLogics\translate('category')}}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php //echo '!!!!<pre />'; print_r($banner); ?>
                    <div class="row">
                        <div class="col-6" id="type-category" style="display: none">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlSelect1">Category</label>
                                <select name="cat_id" id="cat_id" class="form-control js-select2-custom" multiple>
                                    <?php $i = 0; ?>
                                    @foreach($categories as $category)
                                        <!-- <option value="{{$category['id']}}" {{$banner['cat_id']==$category['id']?'selected':''}}>{{$category['name']}}</option> -->
                                        <?php if(($i==0 && $banner['cat_id'] != NULL) || ($i==0 && $banner['cat_id'] != "")){
                                            echo '<option value="">Select Category</option>';
                                            $i++;
                                        } ?>
                                        <option value="{{$category['id']}}" <?php if(in_array($category['id'], json_decode($banner['cat_id']))){ echo 'selected'; } ?>>{{$category['name']}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-6" id="type-sub-category" style="display: none;">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlSelect1">Sub Category</label>
                                <select name="sub_category_id" class="form-control js-select2-custom" multiple>
                                    @foreach($subCategories as $category)
                                        <?php if(($i==0 && $banner['sub_cat_id'] != NULL) || ($i==0 && $banner['sub_cat_id'] != "")){
                                            echo '<option value="">Select Sub Category</option>';
                                            $i++;
                                        } ?>
                                        <option value="{{$category['id']}}" <?php if(in_array($category['id'], json_decode($banner['sub_cat_id']))){ echo 'selected'; } ?>>{{$category['name']}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-6" id="type-cat-category" style="display: none;">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlSelect1">Child Category</label>
                                <select name="child_cat_id" class="form-control js-select2-custom" multiple>
                                    @foreach($childCategories as $category)
                                    <?php if(($i==0 && $banner['child_cat_id'] != NULL) || ($i==0 && $banner['child_cat_id'] != "")){
                                            echo '<option value="">Select Sub Category</option>';
                                            $i++;
                                        } ?>
                                        <option value="{{$category['id']}}" <?php if(in_array($category['id'], json_decode($banner['child_cat_id']))){ echo 'selected'; } ?>>{{$category['name']}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-6" id="type-product" style="display: none">
                            <div class="form-group">
                                <label class="input-label" for="exampleFormControlSelect1">{{\App\CentralLogics\translate('product')}} <span
                                        class="input-label-secondary">*</span></label>
                                <select name="product_id" class="form-control js-select2-custom" multiple>
                                    @foreach($products as $product)
                                        <option
                                            value="{{$product['id']}}" {{$banner['product_id']==$product['id']?'selected':''}}>
                                            {{$product['name']}}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <hr>
                    <button type="submit" class="btn btn-primary">{{\App\CentralLogics\translate('update')}}</button>
                </form>
            </div>
        </div>
    </div>

@endsection

@push('script_2')
    <script>

        function show_item(type) {
            if (type === 'product') {
                $("#type-product").show();
                $("#type-category").hide();
            } else {
                $("#type-product").hide();
                $("#type-category").show();
            }
        }

        $("#cat_id").on('change', function(){
            $("#type-sub-category").css('display', 'block');
            alert(1);
        });

        $("#type-sub-category").on('change', function(){
            $("#type-cat-category").css('display', 'block');
            alert(1);
        });

        $("#type-cat-category").on('change', function(){
            $("#type-product").css('display', 'block');
            alert(1);
        });
    </script>
@endpush
